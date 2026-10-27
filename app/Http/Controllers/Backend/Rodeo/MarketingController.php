<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailCategory;
use App\Models\EmailPart;
use App\Models\EmailTemplate;
use App\Models\Market;
use App\Models\MarketingChannel;
use App\Models\PromoCode;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Support\SurveyStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rodeo: marketing. Email and text campaigns to opted-in customers, the email
 * templates Corral and campaigns use, customer surveys, and the MSIDs and
 * promo codes that track where sign-ups come from.
 */
class MarketingController extends Controller
{
    private static function ready(string $channel): bool
    {
        return collect(config('admin.integrations.'.($channel === 'SMS' ? 'sms' : 'salesforce').'.env'))->every(fn ($v) => filled($v));
    }

    public function dashboard(): View
    {
        $sent = ContactLog::whereNotNull('campaign_id');

        return view('backend.rodeo.dashboard', [
            'optedIn' => Customer::where('marketing_opt_in', true)->count(),
            'customers' => Customer::count(),
            'campaigns' => Campaign::where('status', 'sent')->count(),
            'messages' => (clone $sent)->count(),
            'openRate' => ($n = (clone $sent)->where('channel', 'Email')->count()) ? round(100 * (clone $sent)->whereNotNull('opened_at')->count() / $n) : 0,
            'clickRate' => $n ? round(100 * (clone $sent)->whereNotNull('clicked_at')->count() / $n) : 0,
            'responses' => SurveyResponse::count(),
            'byChannel' => Customer::where('created_at', '>=', today()->subDays(90))->selectRaw('msid, count(*) as n')->groupBy('msid')->orderByDesc('n')->get()
                ->map(fn ($r) => [$r->msid, MarketingChannel::where('msid', $r->msid)->value('name') ?? 'Unknown MSID', $r->n]),
            'recent' => Campaign::latest()->limit(5)->get(),
            // Surveys: each one's responses (last 30 days and all), its score and latest answers
            'surveys' => Survey::with(['questions', 'responses'])->withCount(['responses', 'responses as recent_count' => fn ($q) => $q->where('created_at', '>=', now()->subDays(30))])
                ->withMax('responses', 'created_at')->orderByDesc('responses_max_created_at')->limit(10)->get()
                ->each(fn ($s) => $s->setAttribute('score', SurveyStats::surveyNps($s))),
            'latestResponses' => SurveyResponse::with(['survey.questions', 'customer'])->latest('created_at')->latest('id')->limit(10)->get(),
            'responses30' => SurveyResponse::where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    // ---------- Campaigns ----------

    /** Campaigns in three tables: Scheduled (next first), Drafts, Sent (with results). */
    public function campaigns(): View
    {
        $all = Campaign::with('template')->withCount([
            'messages', 'messages as opened_count' => fn ($q) => $q->whereNotNull('opened_at'), 'messages as clicked_count' => fn ($q) => $q->whereNotNull('clicked_at'),
        ])->latest()->get();

        return view('backend.rodeo.campaigns', [
            'scheduled' => $all->where('status', 'scheduled')->sortBy('scheduled_at'),
            'drafts' => $all->where('status', 'draft'),
            'sent' => $all->where('status', 'sent')->sortByDesc('sent_at')->each(fn ($c) => $c->setAttribute('converted_count', $c->converted()->count())),
            'counts' => ['sent30' => $all->where('status', 'sent')->where('sent_at', '>=', now()->subDays(30))->sum('messages_count')],
        ]);
    }

    /** A campaign's page: the builder while it's a draft or scheduled; results once sent. */
    public function campaignForm(?Campaign $campaign = null): View
    {
        $campaign ??= new Campaign(['channel' => 'Email', 'goal' => 'renewal', 'cap_days' => 7, 'audience' => ['statuses' => ['Good - On Flow']]]);
        if ($campaign->status === 'sent') {
            $results = $campaign->results();

            return view('backend.rodeo.campaign-results', ['campaign' => $campaign->load('template'), 'r' => $results,
                'recipients' => $campaign->messages()->with('customer')->latest('id')->paginate(50),
                'convertedIds' => $campaign->converted()->pluck('id')->flip(),
                'daily' => $campaign->messages()->whereNotNull('opened_at')->selectRaw('date(opened_at) as d, count(*) as n')->groupBy('d')->orderBy('d')->pluck('n', 'd')]);
        }
        $sample = $campaign->exists ? $campaign->audienceQuery()->with('plan')->first() : null;

        return view('backend.rodeo.campaign', ['campaign' => $campaign, 'templates' => EmailTemplate::with('category')->orderBy('name')->get(),
            'markets' => Market::orderBy('short')->get(), 'channels' => MarketingChannel::where('active', true)->orderBy('msid')->get(),
            'promos' => PromoCode::orderBy('code')->get()->filter->isLive(), 'ready' => self::ready($campaign->channel),
            'count' => $campaign->exists ? $campaign->audienceQuery()->count() : null, 'excluded' => $campaign->exists ? $campaign->exclusions() : null,
            'preview' => $campaign->exists && $sample ? ($campaign->channel === 'Email' ? $campaign->template?->render($sample) : $campaign->textFor($sample)) : null, 'sample' => $sample]);
    }

    private function campaignRules(): array
    {
        $products = array_keys(config('corral.products'));

        return [
            'name' => ['required', 'string', 'max:120'],
            'goal' => ['required', Rule::in(array_keys(Campaign::GOALS))],
            'description' => ['nullable', 'string', 'max:500'],
            'product' => ['nullable', 'required_if:goal,product', Rule::in($products)],
            'channel' => ['required', Rule::in(['Email', 'SMS'])],
            'email_template_id' => ['nullable', 'required_if:channel,Email', 'exists:email_templates,id'],
            'message' => ['nullable', 'required_if:channel,SMS', 'string', 'max:320'],
            'msid' => ['nullable', 'exists:marketing_channels,msid'],
            'promo_code' => ['nullable', 'exists:promo_codes,code'],
            'cap_days' => ['required', 'integer', 'min:0', 'max:90'],
            'audience.statuses' => ['nullable', 'array'], 'audience.statuses.*' => [Rule::in(array_keys(config('admin.customer_statuses')))],
            'audience.type' => ['nullable', Rule::in(['Residential', 'Small Business'])],
            'audience.market_ids' => ['nullable', 'array'], 'audience.market_ids.*' => ['exists:markets,id'],
            'audience.product' => ['nullable', Rule::in($products)],
            'audience.without_product' => ['nullable', Rule::in($products)],
            'audience.autopay' => ['nullable', Rule::in(['yes', 'no'])],
            'audience.balance' => ['nullable', Rule::in(['owes', 'paid'])],
            'audience.contract_ends_within' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function saveCampaign(Request $request, ?Campaign $campaign = null): RedirectResponse
    {
        abort_if($campaign?->status === 'sent', 422, 'A sent campaign can\'t be changed.');
        $data = $request->validate($this->campaignRules(), ['product.required_if' => 'Pick the product this campaign promotes.']);
        $data['audience'] = array_filter($data['audience'] ?? [], fn ($v) => $v !== null && $v !== []);
        $data['product'] = $data['goal'] === 'product' ? $data['product'] : null;
        $campaign = $campaign ? tap($campaign)->update($data) : Campaign::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('rodeo.campaigns.edit', $campaign)->with('status', 'Campaign saved. '.number_format($campaign->audienceQuery()->count()).' customers match.');
    }

    /** Live audience count for the builder (no save): matches, exclusions and a few names. */
    public function campaignCount(Request $request): JsonResponse
    {
        $data = $request->validate(array_intersect_key($this->campaignRules(), array_flip(['channel', 'cap_days'])) + array_filter($this->campaignRules(), fn ($k) => str_starts_with($k, 'audience'), ARRAY_FILTER_USE_KEY));
        $c = new Campaign(['channel' => $data['channel'], 'cap_days' => (int) $data['cap_days'], 'audience' => array_filter($data['audience'] ?? [], fn ($v) => $v !== null && $v !== [])]);
        $q = $c->audienceQuery();

        return response()->json(['count' => (clone $q)->count(), 'excluded' => $c->exclusions(),
            'sample' => (clone $q)->orderBy('id')->limit(5)->get(['name', 'account'])->map(fn ($x) => $x->name.' · '.$x->account)]);
    }

    /** Send now. */
    public function sendCampaign(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->status === 'sent', 422, 'This campaign was already sent.');
        $ready = self::ready($campaign->channel);
        [$count, $skipped] = $campaign->send($ready);

        return redirect()->route('rodeo.campaigns.edit', $campaign)->with('status', number_format($count).' messages '.($ready ? 'queued.' : 'logged but not sent: '.($campaign->channel === 'SMS' ? 'Twilio' : 'Salesforce').' is not configured in .env.')
            .($skipped ? ' '.$skipped.' skipped (suppressed or contacted in the last '.$campaign->cap_days.' days).' : ''));
    }

    /** Send at a date and time: the et:campaigns job sends it then. */
    public function scheduleCampaign(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->status === 'sent', 422, 'This campaign was already sent.');
        $data = $request->validate(['date' => ['required', 'date', 'after_or_equal:today'], 'time' => ['required', 'date_format:H:i']]);
        $at = Carbon::parse($data['date'].' '.$data['time']);
        if ($at->lte(now()->addMinutes(5))) {
            return back()->withErrors(['time' => 'Pick a time at least a few minutes from now, or use Send Now.']);
        }
        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $at]);

        return redirect()->route('rodeo.campaigns.edit', $campaign)->with('status', 'Scheduled for '.$at->format('l, M j \a\t g:i A').'.');
    }

    public function unscheduleCampaign(Campaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === 'scheduled', 422);
        $campaign->update(['status' => 'draft', 'scheduled_at' => null]);

        return redirect()->route('rodeo.campaigns.edit', $campaign)->with('status', 'Back to draft: it will not send until you schedule or send it.');
    }

    /** Start a new draft from any campaign (handy for resending a sent one to a new audience). */
    public function duplicateCampaign(Request $request, Campaign $campaign): RedirectResponse
    {
        $copy = $campaign->replicate(['status', 'sent_at', 'recipients', 'skipped', 'scheduled_at']);
        $copy->fill(['name' => $campaign->name.' (copy)', 'status' => 'draft', 'recipients' => 0, 'skipped' => 0, 'created_by' => $request->user()->id])->save();

        return redirect()->route('rodeo.campaigns.edit', $copy)->with('status', 'Copied. Change what you need, then send or schedule it.');
    }

    // ---------- Email templates ----------

    public function templates(Request $request): View
    {
        $only = $request->query('category');

        return view('backend.rodeo.templates', [
            'templates' => EmailTemplate::with('category')->withCount(['campaigns', 'testSends'])->withMax('testSends', 'created_at')
                ->when($only === 'none', fn ($q) => $q->whereNull('email_category_id'))
                ->when($only && $only !== 'none', fn ($q) => $q->where('email_category_id', $only))
                ->orderBy('name')->get(),
            'categories' => EmailCategory::orderBy('position')->orderBy('name')->get(),
            'only' => $only,
            'sent' => ContactLog::where('channel', 'Email')->selectRaw('template, count(*) as n')->groupBy('template')->pluck('n', 'template'),
        ]);
    }

    public function templateForm(?EmailTemplate $template = null): View
    {
        $template ??= new EmailTemplate(['body' => '<p>Hi {{first_name}},</p>', 'email_category_id' => request()->query('category')]);

        return view('backend.rodeo.template', ['template' => $template, 'sample' => Customer::with('plan')->first(), 'categories' => EmailCategory::orderBy('position')->orderBy('name')->get(),
            'parts' => collect(['header' => collect(), 'footer' => collect()])->merge(EmailPart::orderByDesc('is_default')->orderBy('name')->get()->groupBy('kind'))]);
    }

    public function saveTemplate(Request $request, ?EmailTemplate $template = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('email_templates')->ignore($template)],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:100000'],
            'email_category_id' => ['nullable', 'exists:email_categories,id'],
            'email_header_id' => ['nullable', Rule::exists('email_parts', 'id')->where('kind', 'header')],
            'email_footer_id' => ['nullable', Rule::exists('email_parts', 'id')->where('kind', 'footer')],
        ]);
        $template = $template ? tap($template)->update($data) : EmailTemplate::create($data);

        return redirect()->route('rodeo.templates.edit', $template)->with('status', 'Template saved');
    }

    // ---------- MSIDs and promo codes ----------

    public function channels(): View
    {
        $orders = Customer::selectRaw('msid, count(*) as n, max(created_at) as last_at')->groupBy('msid')->get()->keyBy('msid');

        return view('backend.rodeo.channels', ['channels' => MarketingChannel::orderBy('msid')->get(), 'orders' => $orders,
            'promos' => PromoCode::orderByDesc('active')->orderBy('code')->get(), 'promoUses' => Customer::whereNotNull('promo_code')->selectRaw('upper(promo_code) as code, count(*) as n')->groupBy('code')->pluck('n', 'code')]);
    }

    public function saveChannel(Request $request): RedirectResponse
    {
        $data = $request->validate(['msid' => ['required', 'regex:/^\d{3,8}$/'], 'name' => ['required', 'string', 'max:100'], 'type' => ['required', Rule::in(['Organic', 'Paid', 'Partner', 'Broker', 'Agent'])], 'active' => ['boolean']]);
        MarketingChannel::updateOrCreate(['msid' => $data['msid']], ['active' => $request->boolean('active', true)] + $data);

        return redirect()->route('rodeo.channels')->with('status', 'MSID '.$data['msid'].' saved. Website links use ?msid='.$data['msid']);
    }

    /** New MSID / Edit MSID: its own page with a simple form. */
    public function channelForm(?MarketingChannel $channel = null): View
    {
        return view('backend.rodeo.channel-form', ['channel' => $channel ?? new MarketingChannel(['type' => 'Organic', 'active' => true])]);
    }

    /** New Promo Code / Edit Promo Code. */
    public function promoForm(?PromoCode $promo = null): View
    {
        return view('backend.rodeo.promo-form', ['promo' => $promo ?? new PromoCode(['credit' => 0, 'active' => true])]);
    }

    public function savePromo(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate(['code' => ['required', 'regex:/^[A-Z0-9_-]{3,30}$/'], 'description' => ['required', 'string', 'max:150'], 'credit' => ['required', 'numeric', 'min:0', 'max:1000'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'], 'active' => ['boolean']]);
        PromoCode::updateOrCreate(['code' => $data['code']], ['active' => $request->boolean('active', true)] + $data);

        return redirect()->route('rodeo.channels')->with('status', 'Promo code '.$data['code'].' saved. Website links use ?promo='.$data['code']);
    }
}
