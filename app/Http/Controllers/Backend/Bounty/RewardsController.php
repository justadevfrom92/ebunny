<?php

namespace App\Http\Controllers\Backend\Bounty;

use App\Http\Controllers\Controller;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\JobRun;
use App\Models\ReferenceRow;
use App\Models\RewardOffer;
use App\Models\RewardRule;
use App\Models\StarEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bounty: the rewards program. Offers customers spend stars on (shown in Corral
 * and My Account), how stars are earned, redemptions and gift card fulfillment,
 * member lookups and adjustments, and the monthly drawing.
 */
class RewardsController extends Controller
{
    public function dashboard(): View
    {
        $month = today()->startOfMonth();

        return view('backend.bounty.dashboard', [
            'members' => Customer::where('stars', '>', 0)->count(),
            'outstanding' => (int) Customer::sum('stars'),
            'earned' => (float) StarEntry::where('stars', '>', 0)->where('created_at', '>=', $month)->sum('stars'),
            'redeemed' => (float) -StarEntry::where('stars', '<', 0)->where('created_at', '>=', $month)->sum('stars'),
            'giftsPending' => StarEntry::where('fulfillment', 'pending')->count(),
            'popular' => StarEntry::whereNotNull('reward_offer_id')->selectRaw('reward_offer_id, count(*) as n')->groupBy('reward_offer_id')->orderByDesc('n')->limit(5)->get()
                ->map(fn ($r) => [RewardOffer::find($r->reward_offer_id)?->name ?? 'Removed offer', $r->n]),
            'top' => Customer::where('stars', '>', 0)->orderByDesc('stars')->limit(8)->get(['account', 'name', 'stars']),
        ]);
    }

    // ---------- Offers ----------

    public function offers(): View
    {
        return view('backend.bounty.offers', ['offers' => RewardOffer::orderBy('position')->orderBy('stars')->get()]);
    }

    public function saveOffers(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'o' => ['nullable', 'array'],
            'o.*.name' => ['required', 'string', 'max:100'],
            'o.*.description' => ['required', 'string', 'max:200'],
            'o.*.stars' => ['required', 'integer', 'min:0', 'max:100000'],
            'o.*.effect' => ['required', Rule::in(array_keys(RewardOffer::EFFECTS))],
            'o.*.value' => ['nullable', 'string', 'max:100'],
            'o.*.active' => ['required', 'boolean'],
        ]);
        foreach ($data['o'] ?? [] as $key => $o) {
            if ($o['effect'] === 'credit' && ! is_numeric($o['value'] ?? null)) {
                return back()->withInput()->withErrors(['o' => $o['name'].': a bill credit needs a dollar amount in Value.']);
            }
            if ($o['effect'] === 'product' && ! array_key_exists($o['value'] ?? '', config('corral.products'))) {
                return back()->withInput()->withErrors(['o' => $o['name'].': Value must be a product name, e.g. ecobee.']);
            }
        }
        DB::transaction(function () use ($data) {
            foreach ($data['o'] ?? [] as $key => $o) {
                $o['value'] = $o['value'] ?? null;
                str_starts_with((string) $key, 'n') ? RewardOffer::create($o + ['position' => (int) RewardOffer::max('position') + 1]) : RewardOffer::whereKey($key)->first()?->update($o);
            }
        });

        return back()->with('status', 'Offers saved. Corral and My Account show them now.');
    }

    // ---------- Earning rules ----------

    public function rules(): View
    {
        return view('backend.bounty.rules', ['rules' => RewardRule::orderBy('id')->get(), 'last' => JobRun::where('command', 'et:rewards-stars')->latest('started_at')->first()]);
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $data = $request->validate(['r' => ['required', 'array'], 'r.*.label' => ['required', 'string', 'max:60'], 'r.*.stars' => ['required', 'numeric', 'min:0', 'max:1000'], 'r.*.active' => ['required', 'boolean']]);
        foreach ($data['r'] as $id => $r) {
            RewardRule::whereKey($id)->first()?->update($r);
        }

        return back()->with('status', 'Earning rules saved');
    }

    public function award(Request $request): RedirectResponse
    {
        Artisan::call('et:rewards-stars', ['--user' => $request->user()->id]);

        return back()->with('status', trim(Artisan::output()) ?: 'Stars awarded');
    }

    // ---------- Redemptions ----------

    public function redemptions(Request $request): View
    {
        $pendingOnly = $request->query('show') !== 'all';

        return view('backend.bounty.redemptions', ['pendingOnly' => $pendingOnly,
            'rows' => StarEntry::with('customer')->whereNotNull('reward_offer_id')->when($pendingOnly, fn ($q) => $q->where('fulfillment', 'pending'))->latest()->paginate(50)->withQueryString()]);
    }

    /** Gift cards are emailed by the gift card vendor; mark them sent once they are. */
    public function fulfil(StarEntry $entry): RedirectResponse
    {
        abort_unless($entry->fulfillment === 'pending', 422);
        $entry->update(['fulfillment' => 'sent']);
        ContactLog::where('customer_id', $entry->customer_id)->where('template', 'E-Gift Card: '.str_replace('Redeemed: ', '', $entry->reason))->where('status', 'queued')
            ->latest('id')->first()?->update(['status' => 'sent', 'sent_at' => now()]);

        return back()->with('status', 'Marked sent');
    }

    // ---------- Members ----------

    public function members(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $member = $request->query('account') ? Customer::where('account', $request->query('account'))->first() : null;

        return view('backend.bounty.members', ['q' => $q, 'member' => $member,
            'results' => $q !== '' ? Customer::where('account', $q)->orWhere('name', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('email', $q)->limit(25)->get() : collect(),
            'history' => $member ? $member->starEntries()->latest()->limit(50)->get() : collect()]);
    }

    public function adjust(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['stars' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'], 'reason' => ['required', 'string', 'max:120']]);
        if ($customer->stars + $data['stars'] < 0) {
            return back()->withErrors(['stars' => 'That would leave the account with negative stars.']);
        }
        DB::transaction(function () use ($request, $customer, $data) {
            StarEntry::create(['customer_id' => $customer->id, 'reason' => 'Adjustment: '.$data['reason'], 'stars' => $data['stars'], 'user_id' => $request->user()->id]);
            $customer->syncStars();
            $customer->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Rewards', 'action' => 'Redeem Stars', 'priority' => 'Low',
                'body' => ($data['stars'] > 0 ? 'Added ' : 'Removed ').abs($data['stars']).' stars: '.$data['reason'].'.']);
        });

        return redirect()->route('bounty.members', ['account' => $customer->account])->with('status', 'Stars adjusted');
    }

    // ---------- Monthly drawing ----------

    /** Entries for a month (this month unless ?month=YYYY-MM), and past winners. */
    public function drawing(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? Carbon::parse($request->query('month').'-01') : now()->startOfMonth();
        $entries = self::entries($month)->with('customer')->latest('created_at')->get();

        return view('backend.bounty.drawing', ['month' => $month,
            'entries' => $entries,
            'people' => $entries->pluck('customer_id')->unique()->count(),
            'months' => collect(range(0, 5))->map(fn ($i) => now()->startOfMonth()->subMonths($i)),
            'winner' => self::winnerRow($month),
            'past' => ReferenceRow::where('table_key', 'monthly-drawing')->orderByDesc('position')->get()]);
    }

    /** Draw a Winner: its own page for this month's drawing. */
    public function drawForm(): View
    {
        $entries = self::entries(now()->startOfMonth())->get(['customer_id']);

        return view('backend.bounty.draw', ['month' => now()->startOfMonth(), 'count' => $entries->count(),
            'people' => $entries->pluck('customer_id')->unique()->count(), 'winner' => self::winnerRow(now()->startOfMonth())]);
    }

    private static function entries(Carbon $month)
    {
        return HistoryItem::where('model', 'ItemDrawingEntry_model')->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()]);
    }

    private static function winnerRow(Carbon $month): ?ReferenceRow
    {
        return ReferenceRow::where('table_key', 'monthly-drawing')->get()->first(fn ($r) => ($r->cells[0] ?? null) === $month->format('Y-m') && filled($r->cells[2] ?? null));
    }

    /** Picks a random entry for the month and records the winner in the Monthly Drawing data table. */
    public function draw(Request $request): RedirectResponse
    {
        $data = $request->validate(['prize' => ['required', 'string', 'max:100']]);
        $month = now()->format('Y-m');
        $rows = ReferenceRow::where('table_key', 'monthly-drawing')->get();
        if ($rows->contains(fn ($r) => ($r->cells[0] ?? null) === $month && filled($r->cells[2] ?? null))) {
            return back()->withErrors(['prize' => 'This month already has a winner.']);
        }
        $winner = HistoryItem::where('model', 'ItemDrawingEntry_model')->where('created_at', '>=', now()->startOfMonth())->whereNotNull('customer_id')->inRandomOrder()->first()?->customer;
        if (! $winner) {
            return back()->withErrors(['prize' => 'No entries this month yet.']);
        }
        DB::transaction(function () use ($request, $rows, $month, $data, $winner) {
            $row = $rows->first(fn ($r) => ($r->cells[0] ?? null) === $month);
            $cells = [$month, $data['prize'], $winner->account, today()->toDateString()];
            $row ? $row->update(['cells' => $cells]) : ReferenceRow::create(['table_key' => 'monthly-drawing', 'position' => (int) $rows->max('position') + 1, 'cells' => $cells]);
            $winner->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Rewards', 'action' => 'Gift Card', 'priority' => 'Medium',
                'body' => 'Won the '.$month.' monthly drawing: '.$data['prize'].'.']);
        });

        return redirect()->route('bounty.drawing')->with('status', 'Winner: account '.$winner->account.' ('.$winner->first_name.')');
    }
}
