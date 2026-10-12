<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Backend\HistoryController;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /** Customer Search */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['Residential', 'Small Business'])],
            'status' => ['nullable', 'string'],
            'exception' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', Rule::in([0, 10, 25, 50, 100])],
        ]);
        $limit = (int) ($filters['limit'] ?? 25);

        $query = Customer::with(['plan', 'market'])->latest();
        $this->applyFilters($query, $filters);
        $total = (clone $query)->count();
        $results = $limit ? $query->limit($limit)->get() : $query->get();

        $searched = collect($filters)->except('limit')->filter()->isNotEmpty();

        return view('backend.corral.customers.index', [
            'filters' => $filters + ['limit' => $limit],
            'results' => $results,
            'total' => $total,
            'searched' => $searched,
            'bookmarks' => $request->user()->bookmarks()->with(['plan', 'market'])->get(),
            'stats' => [
                'accounts' => Customer::count(),
                'onFlow' => Customer::where('status', 'Good - On Flow')->count(),
                'pending' => Customer::where(fn ($q) => $q->where('status', 'like', 'Pending%')->orWhere('status', 'Submitted'))->count(),
                'exceptions' => Customer::whereNotNull('exception')->count(),
            ],
        ]);
    }

    public static function applyFilters(Builder $query, array $f): void
    {
        if (! empty($f['type'])) {
            $query->where('type', $f['type']);
        }
        if (! empty($f['status'])) {
            $query->where('status', $f['status']);
        }
        if (($f['exception'] ?? '') === '*') {
            $query->whereNotNull('exception');
        } elseif (! empty($f['exception'])) {
            $query->where('exception', $f['exception']);
        }
        if (! empty($f['q'])) {
            $q = trim($f['q']);
            $digits = preg_replace('/\D/', '', $q);
            $query->where(function (Builder $w) use ($q, $digits) {
                foreach (['name', 'email', 'account', 'ticket', 'esiid', 'address', 'city', 'zip'] as $col) {
                    $w->orWhere($col, 'like', '%'.$q.'%');
                }
                if (strlen($digits) >= 4) {
                    $w->orWhere(DB::raw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), ' ', ''), '-', '')"), 'like', '%'.$digits.'%');
                }
            });
        }
    }

    /** The account page: every section of the original Corral account, top to bottom. */
    public function show(Request $request, Customer $customer): View
    {
        $customer->load(['plan', 'market', 'payments.paymentMethod', 'bills', 'notes', 'flags', 'products', 'planTerms.plan', 'addresses',
            'ercotTransactions', 'queueLogs', 'files', 'starEntries', 'paymentMethods']);
        $showAllPayments = $request->boolean('all_payments');
        $showAllMethods = $request->boolean('all_methods');

        // Billing summary: bills and payments together, newest first
        $summary = collect()
            ->concat($customer->bills->map(fn ($b) => ['date' => $b->billed_on, 'billed' => $b->amount, 'paid' => null, 'note' => $b->invoice]))
            ->concat($customer->payments->where('status', 'Success')->map(fn ($p) => ['date' => $p->paid_on, 'billed' => null, 'paid' => $p->amount, 'note' => $p->confirmation]))
            ->sortByDesc(fn ($r) => $r['date']->format('Y-m-d').($r['paid'] === null ? '0' : '1'))->values();

        $usage = $customer->bills->groupBy(fn ($b) => $b->billed_on->copy()->subMonth()->year)
            ->map(fn ($bills) => $bills->mapWithKeys(fn ($b) => [$b->billed_on->copy()->subMonth()->month => $b->kwh]))->sortKeysDesc();

        $net = 0;
        $rewards = $customer->starEntries->sortBy(fn ($e) => $e->created_at->format('U').str_pad((string) $e->id, 8, '0', STR_PAD_LEFT))
            ->map(function ($e) use (&$net) {
                $net += $e->stars;

                return ['date' => $e->created_at, 'reason' => $e->reason, 'stars' => $e->stars, 'net' => $net];
            })->reverse()->values();

        $banners = $customer->flags->map(fn ($f) => config('corral.flag_banners.'.$f->flag))->filter()->unique()->values();
        $hasProtection = $customer->products->contains(fn ($p) => str_contains($p->product, 'Protection') || str_contains($p->product, 'Warranty'));

        return view('backend.corral.customers.show', [
            'c' => $customer,
            'calls' => $customer->phonecalls()->with('user')->latest('started_at')->get(),
            'texts' => $customer->contactLogs()->where('channel', 'SMS')->latest('created_at')->latest('id')->get(),
            'bookmarked' => $request->user()->bookmarks()->whereKey($customer->id)->exists(),
            'canRefund' => Gate::allows('refunds'),
            'banners' => $banners,
            'crossSell' => ! $hasProtection && $customer->type === 'Residential',
            'pending' => LedgerEntry::where('customer_id', $customer->id)->where('status', 'pending')->get(),
            'summary' => $summary,
            'payments' => $showAllPayments ? $customer->payments->sortByDesc('paid_on') : $customer->payments->whereNotIn('status', ['Failed'])->sortByDesc('paid_on'),
            'showAllPayments' => $showAllPayments,
            'methods' => $showAllMethods ? $customer->paymentMethods : $customer->paymentMethods->whereNull('removed_at'),
            'showAllMethods' => $showAllMethods,
            'methodTotals' => $customer->payments->where('status', 'Success')->groupBy('payment_method_id')->map->sum('amount'),
            'usage' => $usage,
            'rewards' => $rewards,
            'currentQueues' => $customer->queueLogs->whereNull('exited_at'),
            'warehouse' => AccountController::warehouse($customer),
            'logs' => HistoryController::logsFor($customer),
        ]);
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(config('admin.customer_statuses')))]]);
        if ($data['status'] !== $customer->status) {
            $this->note($request, $customer, 'Status changed from '.$customer->status.' to '.$data['status'].'.');
            $customer->update(['status' => $data['status']]);
        }

        return redirect(route('corral.customers.show', $customer).'#service')->with('status', 'Status updated');
    }

    public function bookmark(Request $request, Customer $customer): RedirectResponse
    {
        $result = $request->user()->bookmarks()->toggle($customer->id);

        return back()->with('status', $result['attached'] ? 'Bookmarked' : 'Bookmark removed');
    }

    public function addNote(Request $request, Customer $customer): RedirectResponse
    {
        $categories = config('corral.note_categories');
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(array_keys($categories))],
            'action' => ['nullable', 'string', Rule::in($categories[$request->input('category')] ?? [])],
            'priority' => ['nullable', Rule::in(config('corral.priorities'))],
        ]);
        $customer->notes()->create([
            'user_id' => $request->user()->id, 'author' => $request->user()->name, 'body' => $data['body'],
            'category' => $data['category'] ?? null, 'action' => $data['action'] ?? null, 'priority' => $data['priority'] ?? null,
            'disposition' => $data['action'] ?? null,
        ]);

        return redirect(route('corral.customers.show', $customer).'#notes')->with('status', 'Note added');
    }

    /** Needs the "refunds" right. Marks the payment reversed and puts the amount back on the balance. */
    public function reversePayment(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('refunds');
        abort_unless($payment->status === 'Success', 422, 'Only successful payments can be reversed.');

        DB::transaction(function () use ($request, $payment) {
            $payment->update(['status' => 'Reversed', 'reversed_at' => now(), 'reversed_by' => $request->user()->id]);
            $payment->customer->increment('balance', $payment->amount);
            $this->note($request, $payment->customer, 'Payment '.$payment->reference.' for $'.number_format($payment->amount, 2).' reversed.', 'Payment arrangement');
        });

        return redirect(route('corral.customers.show', $payment->customer).'#payments')->with('status', 'Payment reversed');
    }

    private function note(Request $request, Customer $customer, string $body, ?string $disposition = null): void
    {
        $customer->notes()->create([
            'user_id' => $request->user()->id,
            'author' => $request->user()->name,
            'disposition' => $disposition,
            'body' => $body,
        ]);
    }
}
