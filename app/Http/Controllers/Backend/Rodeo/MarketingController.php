<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailCategory;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\Market;
use App\Models\MarketingChannel;
use App\Models\PromoCode;
use App\Models\SurveyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    // ---------- Campaigns ----------

    public function campaigns(): View
    {
        return view('backend.rodeo.campaigns', ['campaigns' => Campaign::with('template')->withCount([
            'messages', 'messages as opened_count' => fn ($q) => $q->whereNotNull('opened_at'), 'messages as clicked_count' => fn ($q) => $q->whereNotNull('clicked_at'),
        ])->latest()->get()]);
    }

    public function campaignForm(?Campaign $campaign = null): View
    {
        $campaign ??= new Campaign(['channel' => 'Email', 'audience' => ['statuses' => ['Good - On Flow']]]);

        return view('backend.rodeo.campaign', ['campaign' => $campaign, 'templates' => EmailTemplate::orderBy('name')->get(), 'markets' => Market::orderBy('name')->get(),
            'count' => $campaign->exists ? $campaign->audienceQuery()->count() : null, 'ready' => self::ready($campaign->channel)]);
    }

    public function saveCampaign(Request $request, ?Campaign $campaign = null): RedirectResponse
    {
        abort_if($campaign?->status === 'sent', 422, 'A sent campaign can\'t be changed.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', Rule::in(['Email', 'SMS'])],
            'email_template_id' => ['nullable', 'required_if:channel,Email', 'exists:email_templates,id'],
            'message' => ['nullable', 'required_if:channel,SMS', 'string', 'max:320'],
            'audience.statuses' => ['nullable', 'array'], 'audience.statuses.*' => [Rule::in(array_keys(config('admin.customer_statuses')))],
            'audience.type' => ['nullable', Rule::in(['Residential', 'Small Business'])],
            'audience.market_id' => ['nullable', 'exists:markets,id'],
            'audience.product' => ['nullable', Rule::in(array_keys(config('corral.products')))],
            'audience.without_product' => ['nullable', Rule::in(array_keys(config('corral.products')))],
            'audience.contract_ends_within' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $data['audience'] = array_filter($data['audience'] ?? [], fn ($v) => $v !== null && $v !== []);
        $campaign = $campaign ? tap($campaign)->update($data) : Campaign::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('rodeo.campaigns.edit', $campaign)->with('status', 'Campaign saved. '.number_format($campaign->audienceQuery()->count()).' customers match.');
    }

    /** Creates one message per customer. They go out through Salesforce (email) or Twilio (texts) when those are set up in .env. */
    public function sendCampaign(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->status === 'sent', 422, 'This campaign was already sent.');
        $ready = self::ready($campaign->channel);
        $count = $suppressed = 0;
        DB::transaction(function () use ($campaign, $ready, &$count, &$suppressed) {
            $campaign->audienceQuery()->with('plan')->chunkById(200, function ($customers) use ($campaign, $ready, &$count, &$suppressed) {
                foreach ($customers as $c) {
                    // Rodeo → Emails → Suppression List: no email to these addresses
                    if ($campaign->channel === 'Email' && EmailSuppression::has($c->email)) {
                        $suppressed++;

                        continue;
                    }
                    ContactLog::create(['customer_id' => $c->id, 'campaign_id' => $campaign->id, 'channel' => $campaign->channel,
                        'template' => $campaign->channel === 'Email' ? $campaign->template?->name : 'Campaign: '.$campaign->name,
                        'body' => $campaign->channel === 'Email' ? strip_tags($campaign->template?->render($c) ?? '') : str_replace('{{first_name}}', $c->first_name, $campaign->message),
                        'phone' => $campaign->channel === 'SMS' ? $c->phone : null, 'status' => $ready ? 'queued' : 'not sent']);
                    $count++;
                }
            });
            $campaign->update(['status' => 'sent', 'sent_at' => now(), 'recipients' => $count]);
        });

        return redirect()->route('rodeo.campaigns')->with('status', $count.' messages '.($ready ? 'queued.' : 'logged but not sent: '.($campaign->channel === 'SMS' ? 'Twilio' : 'Salesforce').' is not configured in .env.')
            .($suppressed ? ' '.$suppressed.' skipped: on the suppression list.' : ''));
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

        return view('backend.rodeo.template', ['template' => $template, 'sample' => Customer::with('plan')->first(), 'categories' => EmailCategory::orderBy('position')->orderBy('name')->get()]);
    }

    public function saveTemplate(Request $request, ?EmailTemplate $template = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('email_templates')->ignore($template)],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:100000'],
            'email_category_id' => ['nullable', 'exists:email_categories,id'],
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
