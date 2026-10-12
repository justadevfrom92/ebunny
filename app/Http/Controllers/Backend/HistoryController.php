<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\User;
use App\Support\Items;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** History: per customer (Corral), per admin user (Sheriff), and a detail page for each entry. */
class HistoryController extends Controller
{
    /** The account's log tickets that have entries: [key, title, count, first, last]. */
    public static function logsFor(Customer $customer): Collection
    {
        $byGroup = HistoryItem::where('customer_id', $customer->id)->select('group')
            ->selectRaw('count(*) as n, min(created_at) as first_at, max(created_at) as last_at')->groupBy('group')->get()->keyBy('group');

        // Original items on each log ticket count too, so a log with items but no history still shows
        $items = Items::forCustomer($customer);

        return collect(config('history.logs'))->map(function ($log, $key) use ($byGroup, $items) {
            $rows = collect($log[1])->map(fn ($g) => $byGroup[$g] ?? null)->filter();
            $own = Items::onTicket($items, $key);

            return ['key' => $key, 'title' => $log[0], 'count' => (int) $rows->sum('n') + $own->count(),
                'first' => collect([$rows->min('first_at'), $own->min('created')?->toDateTimeString()])->filter()->min(), 'last' => collect([$rows->max('last_at'), $own->max('created')?->toDateTimeString()])->filter()->max()];
        })->filter(fn ($l) => $l['count'] > 0)->values();
    }

    /** The account ticket: every child item on the account, grouped by model, plus its log tickets. */
    public function ticket(Customer $customer): View
    {
        $items = HistoryItem::where('customer_id', $customer->id)->with('user')->oldest('created_at')->oldest('id')->get();

        return view('backend.history.ticket', [
            'c' => $customer,
            'title' => 'Account - '.$customer->account,
            'created' => $customer->created_at,
            'ticketId' => $customer->ticket,
            'groups' => $items->groupBy('model')->sortKeys(),
            'childTickets' => self::logsFor($customer),
            'parents' => collect(),
            'processLogs' => $customer->queueLogs()->get(),
            'currentQueues' => $customer->queueLogs()->whereNull('exited_at')->get(),
            'items' => $items,
            'dataItems' => Items::forTicket($customer, null),
            'log' => null,
        ]);
    }

    /** One log ticket, e.g. "Logs - Products": that log's items grouped by model. */
    public function log(Customer $customer, string $log): View
    {
        abort_unless(config()->has('history.logs.'.$log), 404);
        [$title, $groups] = config('history.logs.'.$log);
        $items = HistoryItem::where('customer_id', $customer->id)->whereIn('group', $groups)->with('user')->oldest('created_at')->oldest('id')->get();

        return view('backend.history.ticket', [
            'c' => $customer,
            'title' => $title,
            'created' => $items->first()?->created_at ?? $customer->created_at,
            'ticketId' => $customer->ticket.'-'.str_pad((string) (array_search($log, array_keys(config('history.logs'))) + 1), 2, '0', STR_PAD_LEFT),
            'groups' => $items->groupBy('model')->sortKeys(),
            'childTickets' => collect(),
            'parents' => collect([['title' => 'Account '.$customer->account, 'created' => $customer->created_at, 'url' => route('corral.customers.ticket', $customer), 'id' => $customer->ticket]]),
            'processLogs' => collect(),
            'currentQueues' => collect(),
            'items' => $items,
            'dataItems' => Items::forTicket($customer, $log),
            'log' => $log,
        ]);
    }

    /** Sheriff → Users → History: everything one admin user did. */
    public function user(Request $request, User $user): View
    {
        $base = HistoryItem::where('user_id', $user->id);
        $filters = $request->only(['hmodel', 'hgroup']);

        return view('backend.history.user', [
            'u' => $user,
            'items' => self::filtered(clone $base, $filters)->with('customer')->latest('created_at')->latest('id')->paginate(100)->withQueryString(),
            'counts' => self::counts(clone $base),
            'filters' => $filters,
            'total' => (clone $base)->count(),
        ]);
    }

    /** One entry: its fields, changes, parent account and related entries. */
    public function show(HistoryItem $item): View
    {
        $item->load(['customer', 'user']);
        $related = HistoryItem::where('model', $item->model)->whereKeyNot($item->id)
            ->where(fn ($q) => $item->customer_id
                ? $q->where('customer_id', $item->customer_id)
                : $q->where('record_id', $item->record_id))
            ->latest('created_at')->limit(50)->get();

        $logKey = collect(config('history.logs'))->search(fn ($l) => in_array($item->group, $l[1], true));

        return view('backend.history.show', ['item' => $item, 'related' => $related, 'logKey' => $logKey ?: null,
            'logTitle' => $logKey ? config('history.logs.'.$logKey)[0] : null]);
    }

    private static function filtered(Builder $q, array $f): Builder
    {
        return $q->when($f['hmodel'] ?? null, fn ($q, $m) => $q->where('model', $m))
            ->when($f['hgroup'] ?? null, fn ($q, $g) => $q->where('group', $g));
    }

    /** Counts per model, grouped and ordered like config('history.groups'). */
    private static function counts(Builder $q): Collection
    {
        $order = array_flip(config('history.groups'));

        return $q->select(['group', 'model'])->selectRaw('count(*) as n, max(created_at) as last_at')
            ->groupBy('group', 'model')->get()
            ->groupBy('group')
            ->sortBy(fn ($rows, $group) => $order[$group] ?? 99)
            ->map(fn ($rows) => $rows->sortBy('model')->values());
    }
}
