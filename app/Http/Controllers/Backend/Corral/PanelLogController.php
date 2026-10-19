<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerFlag;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Log button on the account page's Balances, Actions and Flags panels: every entry, newest first,
 * 25 to a page. The panels themselves show the last 10.
 */
class PanelLogController extends Controller
{
    public const PANELS = ['balances' => 'Balance Log', 'actions' => 'Action Log', 'flags' => 'Flag Log'];

    /** Credits, debits and payments on the account, newest first: [at, type, amount, description, status, by]. */
    public static function balanceRows(Customer $c): Collection
    {
        $ledger = $c->ledgerEntries()->with('user')->get()->map(fn ($e) => ['at' => $e->created_at, 'type' => ucfirst($e->kind), 'amount' => $e->kind === 'credit' ? -abs($e->amount) : abs($e->amount),
            'description' => $e->description, 'status' => ucfirst($e->status), 'by' => $e->user?->name]);
        $payments = $c->payments()->get()->map(fn ($p) => ['at' => $p->paid_on?->copy()->setTimeFromTimeString($p->paid_time ?? '00:00') ?? $p->created_at, 'type' => 'Payment',
            'amount' => -abs($p->amount), 'description' => trim(($p->method ?? '').' '.($p->reference ? '· '.$p->reference : '')), 'status' => $p->status, 'by' => $p->source]);

        return $ledger->concat($payments)->sortByDesc('at')->values();
    }

    /** Actions run from the Actions menus (each one writes an Order note named after the action). */
    public static function actionNotes(Customer $c)
    {
        $labels = collect(config('corral.actions.status'))->concat(config('corral.actions.service'))->pluck(0)->all();

        return Note::where('customer_id', $c->id)->whereIn('action', $labels)->latest('created_at')->latest('id');
    }

    public function show(Request $request, Customer $customer, string $panel): View
    {
        abort_unless(isset(self::PANELS[$panel]), 404);
        $rows = match ($panel) {
            'balances' => $this->paginate(self::balanceRows($customer), $request),
            'actions' => self::actionNotes($customer)->paginate(25)->withQueryString(),
            'flags' => CustomerFlag::where('customer_id', $customer->id)->latest('created_at')->latest('id')->paginate(25)->withQueryString(),
        };

        return view('backend.corral.customers.panel-log', ['c' => $customer, 'panel' => $panel, 'title' => self::PANELS[$panel], 'rows' => $rows]);
    }

    private function paginate(Collection $all, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));

        return (new LengthAwarePaginator($all->forPage($page, 25)->values(), $all->count(), 25, $page, ['path' => $request->url()]))->withQueryString();
    }
}
