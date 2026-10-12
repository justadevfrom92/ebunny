<?php

namespace App\Http\Controllers\Backend\Caboose;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Reports\ReceivablesReport;
use App\Support\Xlsx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Caboose: finance. Cash and payments, pending credits/debits, refunds,
 * deposits, receivables aging and the daily journal for the accounting system.
 * Every change leaves a note on the account and a history entry.
 */
class FinanceController extends Controller
{
    private function note(Request $request, Customer $c, string $body, string $action): void
    {
        $c->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Payment', 'action' => $action, 'priority' => 'Low', 'body' => $body]);
    }

    public function dashboard(): View
    {
        $paid = Payment::where('status', 'Success');

        return view('backend.caboose.dashboard', [
            'today' => (clone $paid)->whereDate('paid_on', today())->sum('amount'),
            'month' => (clone $paid)->whereDate('paid_on', '>=', today()->startOfMonth())->sum('amount'),
            'pendingPayments' => Payment::where('status', 'Pending')->count(),
            'receivable' => Customer::where('balance', '>', 0)->sum('balance'),
            'credits' => Customer::where('balance', '<', 0)->sum('balance'),
            'depositsDue' => Customer::where('deposit_due', '>', 0)->sum('deposit_due'),
            'depositsHeld' => Customer::sum('deposit_held'),
            'ledger' => LedgerEntry::where('status', 'pending')->count(),
            'refunds' => Refund::where('status', 'requested')->count(),
            'byMethod' => Payment::where('status', 'Success')->whereDate('paid_on', '>=', today()->subDays(30))
                ->selectRaw('method, count(*) as n, sum(amount) as total')->groupBy('method')->orderByDesc('total')->get(),
            'aging' => ($r = new ReceivablesReport)->summary($r->query($r->defaults()))[1],
        ]);
    }

    // ---------- Payments ----------

    public function payments(Request $request): View
    {
        $f = $request->validate(['start' => ['nullable', 'date'], 'end' => ['nullable', 'date'], 'status' => ['nullable', 'string'], 'account' => ['nullable', 'string', 'max:20']]);
        $f += ['start' => today()->subDays(30)->toDateString(), 'end' => today()->toDateString(), 'status' => null, 'account' => null];
        $q = Payment::with('customer')->whereDate('paid_on', '>=', $f['start'])->whereDate('paid_on', '<=', $f['end'])
            ->when($f['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($f['account'], fn ($q, $a) => $q->whereHas('customer', fn ($c) => $c->where('account', trim($a))));

        return view('backend.caboose.payments', ['f' => $f, 'payments' => (clone $q)->orderByDesc('paid_on')->orderByDesc('id')->paginate(50)->withQueryString(),
            'total' => (clone $q)->where('status', 'Success')->sum('amount')]);
    }

    /** Record a payment received by mail or in person (check, money order, cash). */
    /** Record a Payment: its own page with a simple form. */
    public function paymentForm(): View
    {
        return view('backend.caboose.payment-form');
    }

    /** Request a Refund: its own page (Credit Balances links prefill the account). */
    public function refundForm(): View
    {
        return view('backend.caboose.refund-form');
    }

    public function recordPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', 'exists:customers,account'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'method' => ['required', Rule::in(['Check', 'Money Order', 'Cash', 'Wire'])],
            'reference' => ['nullable', 'string', 'max:60'],
            'kind' => ['required', Rule::in(['Balance Payment', 'Deposit'])],
        ]);
        $c = Customer::where('account', $data['account'])->firstOrFail();
        DB::transaction(function () use ($request, $c, $data) {
            Payment::create(['customer_id' => $c->id, 'reference' => 'FIN-'.strtoupper(Str::random(8)), 'paid_on' => today(), 'paid_time' => now()->format('H:i:s'),
                'amount' => $data['amount'], 'method' => $data['method'], 'source' => 'Finance', 'kind' => $data['kind'], 'status' => 'Success',
                'confirmation' => $data['reference'] ?? null]);
            if ($data['kind'] === 'Deposit') {
                $c->update(['deposit_held' => $c->deposit_held + $data['amount'], 'deposit_due' => max(0, $c->deposit_due - $data['amount'])]);
            } else {
                $c->decrement('balance', $data['amount']);
            }
            $this->note($request, $c, $data['kind'].' of $'.number_format($data['amount'], 2).' received by '.$data['method'].($data['reference'] ? ' #'.$data['reference'] : '').'.', 'Payment Made');
        });

        return redirect()->route('caboose.payments')->with('status', $data['kind'].' recorded for account '.$c->account);
    }

    /** Needs the "refunds" right (same as Corral's reversal). */
    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('refunds');
        abort_unless($payment->status === 'Success', 422, 'Only successful payments can be reversed.');
        DB::transaction(function () use ($request, $payment) {
            $payment->update(['status' => 'Reversed', 'reversed_at' => now(), 'reversed_by' => $request->user()->id]);
            $payment->customer->increment('balance', $payment->amount);
            $this->note($request, $payment->customer, 'Payment '.$payment->reference.' for $'.number_format($payment->amount, 2).' reversed in Caboose.', 'Payment Dispute');
        });

        return back()->with('status', 'Payment reversed');
    }

    // ---------- Pending credits and debits ----------

    public function ledger(): View
    {
        return view('backend.caboose.ledger', [
            'pending' => LedgerEntry::with(['customer', 'user'])->where('status', 'pending')->oldest()->get(),
            'recent' => LedgerEntry::with(['customer', 'user'])->where('status', '!=', 'pending')->latest('updated_at')->limit(25)->get(),
        ]);
    }

    public function decideLedger(Request $request, LedgerEntry $entry): RedirectResponse
    {
        $do = $request->validate(['do' => ['required', Rule::in(['apply', 'reject'])]])['do'];
        abort_unless($entry->status === 'pending', 422);
        DB::transaction(function () use ($request, $entry, $do) {
            $entry->update(['status' => $do === 'apply' ? 'applied' : 'deleted']);
            if ($do === 'apply') {
                $entry->kind === 'credit' ? $entry->customer->decrement('balance', $entry->amount) : $entry->customer->increment('balance', $entry->amount);
            }
            $this->note($request, $entry->customer, ucfirst($entry->kind).' of $'.number_format($entry->amount, 2).' ('.$entry->description.') '.($do === 'apply' ? 'applied' : 'rejected').' in Caboose.', 'Fees');
        });

        return back()->with('status', ucfirst($entry->kind).' '.($do === 'apply' ? 'applied to the balance' : 'rejected'));
    }

    // ---------- Refunds ----------

    public function refunds(): View
    {
        return view('backend.caboose.refunds', [
            'open' => Refund::with(['customer', 'requester', 'decider'])->whereIn('status', ['requested', 'approved'])->oldest()->get(),
            'done' => Refund::with(['customer', 'requester', 'decider'])->whereIn('status', ['paid', 'rejected'])->latest('updated_at')->limit(25)->get(),
            'credits' => Customer::where('balance', '<', 0)->whereDoesntHave('refunds', fn ($q) => $q->whereIn('status', ['requested', 'approved']))->orderBy('balance')->limit(25)->get(),
        ]);
    }

    public function requestRefund(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', 'exists:customers,account'],
            'kind' => ['required', Rule::in(['balance', 'deposit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'max:200'],
            'method' => ['required', Rule::in(['Check', 'Card', 'ACH'])],
        ]);
        $c = Customer::where('account', $data['account'])->firstOrFail();
        $limit = $data['kind'] === 'deposit' ? $c->deposit_held : -$c->balance;
        if ($data['amount'] > $limit + 0.001) {
            return back()->withInput()->withErrors(['amount' => 'The most that can be refunded is $'.number_format(max(0, $limit), 2).($data['kind'] === 'deposit' ? ' (deposit held)' : ' (credit balance)').'.']);
        }
        Refund::create(['customer_id' => $c->id, 'requested_by' => $request->user()->id] + collect($data)->except('account')->all());

        return redirect()->route('caboose.refunds')->with('status', 'Refund requested for account '.$c->account);
    }

    /** Approve or reject needs the "refunds" right; paying marks the money as sent. */
    public function decideRefund(Request $request, Refund $refund): RedirectResponse
    {
        $do = $request->validate(['do' => ['required', Rule::in(['approve', 'reject', 'paid'])]])['do'];
        Gate::authorize('refunds');
        abort_unless(($do === 'paid' && $refund->status === 'approved') || ($do !== 'paid' && $refund->status === 'requested'), 422);
        DB::transaction(function () use ($request, $refund, $do) {
            $c = $refund->customer;
            if ($do === 'paid') {
                $refund->update(['status' => 'paid', 'paid_at' => now()]);
                $refund->kind === 'deposit' ? $c->update(['deposit_held' => max(0, $c->deposit_held - $refund->amount)]) : $c->increment('balance', $refund->amount);
                $this->note($request, $c, ucfirst($refund->kind).' refund of $'.number_format($refund->amount, 2).' sent by '.$refund->method.'.', 'Deposit Refund Request');
            } else {
                $refund->update(['status' => $do === 'approve' ? 'approved' : 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now()]);
                $this->note($request, $c, 'Refund of $'.number_format($refund->amount, 2).' '.($do === 'approve' ? 'approved' : 'rejected').'.', 'Deposit Refund Request');
            }
        });

        return back()->with('status', 'Refund '.['approve' => 'approved', 'reject' => 'rejected', 'paid' => 'marked paid'][$do]);
    }

    // ---------- Deposits ----------

    public function deposits(): View
    {
        return view('backend.caboose.deposits', [
            'due' => Customer::where('deposit_due', '>', 0)->orderByDesc('deposit_due')->get(),
            'held' => Customer::where('deposit_held', '>', 0)->orderByDesc('deposit_held')->get(),
        ]);
    }

    // ---------- Receivables aging ----------

    public function aging(Request $request): View
    {
        $report = new ReceivablesReport;
        $f = ['as_of' => $request->date('as_of')?->toDateString() ?? today()->toDateString(), 'min' => '0.01'];
        $accounts = $report->query($f);

        return view('backend.caboose.aging', ['f' => $f, 'accounts' => $accounts, 'summary' => $report->summary($accounts)[1], 'bucket' => fn ($d) => ReceivablesReport::bucket($d)]);
    }

    // ---------- Journal ----------

    /** Daily journal (double entry) for the accounting system: on screen or as CSV/XLSX for import. */
    public function journal(Request $request)
    {
        $f = $request->validate(['start' => ['nullable', 'date'], 'end' => ['nullable', 'date', 'after_or_equal:start'], 'format' => ['nullable', Rule::in(['csv', 'xlsx'])]]);
        $start = Carbon::parse($f['start'] ?? today()->startOfMonth())->startOfDay();
        $end = Carbon::parse($f['end'] ?? today())->endOfDay();
        $lines = $this->journalLines($start, $end);

        if ($format = $f['format'] ?? null) {
            $header = ['Date', 'Account', 'Debit', 'Credit', 'Memo'];
            $rows = $lines->map(fn ($l) => [$l['date'], $l['account'], $l['debit'] ?: null, $l['credit'] ?: null, $l['memo']]);
            $name = 'journal-'.$start->toDateString().'-to-'.$end->toDateString();

            return $format === 'xlsx'
                ? response(Xlsx::build($header, $rows, 'Journal'), 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"'])
                : response()->streamDownload(function () use ($header, $rows) {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, $header);
                    $rows->each(fn ($r) => fputcsv($out, $r));
                    fclose($out);
                }, $name.'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('backend.caboose.journal', ['start' => $start, 'end' => $end, 'lines' => $lines,
            'debits' => round($lines->sum('debit'), 2), 'credits' => round($lines->sum('credit'), 2),
            'quickbooks' => collect(config('admin.integrations.quickbooks.env'))->every(fn ($v) => filled($v))]);
    }

    private function journalLines(Carbon $start, Carbon $end): Collection
    {
        $lines = collect();
        $entry = function (string $date, string $dr, string $cr, float $amount, string $memo) use ($lines) {
            if (abs($amount) < 0.005) {
                return;
            }
            $lines->push(['date' => $date, 'account' => $dr, 'debit' => round($amount, 2), 'credit' => 0, 'memo' => $memo]);
            $lines->push(['date' => $date, 'account' => $cr, 'debit' => 0, 'credit' => round($amount, 2), 'memo' => $memo]);
        };

        Bill::whereBetween('billed_on', [$start, $end])->selectRaw('date(billed_on) as d, sum(amount) as total, count(*) as n')->groupBy('d')->get()
            ->each(fn ($r) => $entry($r->d, 'Accounts Receivable', 'Electricity Revenue', (float) $r->total, $r->n.' bills'));
        Payment::where('status', 'Success')->whereBetween('paid_on', [$start, $end])->selectRaw("date(paid_on) as d, coalesce(kind, 'Balance Payment') as k, sum(amount) as total, count(*) as n")
            ->groupBy('d', 'k')->get()->each(fn ($r) => $r->k === 'Deposit'
                ? $entry($r->d, 'Cash', 'Customer Deposits', (float) $r->total, $r->n.' deposits received')
                : $entry($r->d, 'Cash', 'Accounts Receivable', (float) $r->total, $r->n.' payments'));
        LedgerEntry::where('status', 'applied')->whereBetween('updated_at', [$start, $end])->get()->groupBy(fn ($e) => $e->updated_at->toDateString().'|'.$e->kind)
            ->each(function ($g, $k) use ($entry) {
                [$d, $kind] = explode('|', $k);
                $kind === 'credit'
                    ? $entry($d, 'Credits & Promotions', 'Accounts Receivable', (float) $g->sum('amount'), $g->count().' bill credits')
                    : $entry($d, 'Accounts Receivable', 'Fee Revenue', (float) $g->sum('amount'), $g->count().' debits');
            });
        Refund::where('status', 'paid')->whereBetween('paid_at', [$start, $end])->get()->groupBy(fn ($r) => $r->paid_at->toDateString().'|'.$r->kind)
            ->each(function ($g, $k) use ($entry) {
                [$d, $kind] = explode('|', $k);
                $entry($d, $kind === 'deposit' ? 'Customer Deposits' : 'Accounts Receivable', 'Cash', (float) $g->sum('amount'), $g->count().' '.$kind.' refunds');
            });

        return $lines->sortBy('date')->values();
    }
}
