<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Backend\HistoryController;
use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\DataItem;
use App\Models\HistoryItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\History;
use App\Support\Items;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The original Corral data item pages: corral/data/item/{id}/{Model}. Each
 * model shows its own fields in the original order, its own buttons, its
 * Process Logs and its parent tickets (see config/items.php, App\Support\Items).
 */
class ItemController extends Controller
{
    public function show(int $id, string $model): View|RedirectResponse
    {
        $d = Items::def($model);
        if ($d['source'] === 'ticket') {
            return $this->ticket($id, $model);
        }
        $r = Items::find($model, $id);
        $c = Items::customer($r);

        return view('backend.corral.items.show', [
            'model' => $model, 'd' => $d, 'r' => $r, 'c' => $c,
            'title' => Items::title($model, $r), 'created' => Items::created($r),
            'values' => Items::values($model, $r),
            'logs' => $this->processLogs($model, $r),
            'events' => Items::events($model, $r),
            'parents' => $this->parents($model, $r, $c),
            'siblings' => $c ? Items::forModel($model, $c)->reject(fn ($x) => $x->getKey() === $r->getKey())->take(25) : collect(),
        ]);
    }

    public function edit(int $id, string $model): View
    {
        $r = Items::find($model, $id);
        abort_if(Items::def($model)['source'] === 'ticket', 404);

        return view('backend.corral.items.edit', ['model' => $model, 'd' => Items::def($model), 'r' => $r, 'c' => Items::customer($r),
            'title' => Items::title($model, $r), 'values' => Items::values($model, $r), 'readOnly' => Items::readOnly($model, $r)]);
    }

    public function update(Request $request, int $id, string $model): RedirectResponse
    {
        $r = Items::find($model, $id);
        $before = Items::values($model, $r);
        $input = $request->validate(['f' => ['array'], 'f.*' => ['nullable', 'string', 'max:5000']])['f'] ?? [];
        if (Items::def($model)['source'] === 'person' && filled($input['email'] ?? null)) {
            $request->validate(['f.email' => ['email', 'max:150']]);
        }
        DB::transaction(fn () => Items::save($model, $r, $input));
        $after = Items::values($model, $r->fresh());
        $changed = collect($after)->filter(fn ($v, $k) => ($before[$k] ?? '') !== $v)->keys();
        $this->log($model, $r, 'updated', $changed->isEmpty() ? 'Saved with no changes' : 'Edited: '.$changed->implode(', '),
            $changed->mapWithKeys(fn ($k) => [$k => [$before[$k] ?? '', $after[$k]]])->all());

        return redirect()->route('corral.items.show', [$id, $model])->with('status', 'Saved');
    }

    public function destroy(int $id, string $model): RedirectResponse
    {
        Gate::authorize('delete');
        $d = Items::def($model);
        $r = Items::find($model, $id);
        $c = Items::customer($r);
        abort_if(in_array($d['source'], ['person', 'postal', 'login', 'plan', 'ticket'], true), 422, 'This item is part of the account itself; change it with Edit, or delete the whole account.');
        $this->log($model, $r, 'deleted', 'Deleted (permanent): '.Items::summary($model, $r));
        DB::transaction(function () use ($model, $r) {
            Items::extras($model, $r)?->delete();
            $r->delete();
        });

        return redirect()->route('corral.customers.ticket', $c)->with('status', $model.' '.$id.' deleted');
    }

    /** The original page's other buttons: Resend, Check Status, Duplicate, Mark as Deposit, Regenerate… */
    public function act(Request $request, int $id, string $model, string $action): RedirectResponse
    {
        $d = Items::def($model);
        abort_unless(in_array($action, $d['actions'], true) && ! in_array($action, ['edit', 'delete'], true), 404);
        $r = Items::find($model, $id);
        $c = Items::customer($r);
        $ready = fn (string $key) => collect(config('admin.integrations.'.$key.'.env'))->every(fn ($v) => filled($v));
        $integration = fn (string $key) => config('admin.integrations.'.$key.'.name');
        $go = fn ($msg, $ok = true) => redirect()->route('corral.items.show', [$id, $model])->with($ok ? 'status' : 'item_notice', $msg);

        switch ($action) {
            case 'resend':
                /** @var ContactLog $r */
                $sf = $ready('salesforce');
                $copy = ContactLog::create(['customer_id' => $r->customer_id, 'channel' => 'Email', 'template' => $r->template, 'body' => $r->body,
                    'status' => $sf ? 'queued' : 'not sent', 'user_id' => $request->user()->id]);
                $this->log($model, $r, 'resent', 'Resent as #'.$copy->id.($sf ? ' (queued for SalesForce)' : ' (not sent: SalesForce not configured)'));

                return redirect()->route('corral.items.show', [$copy->id, $model])
                    ->with($sf ? 'status' : 'item_notice', $sf ? 'Resent. The copy is queued for SalesForce.' : 'Logged a resend, but it was not sent: '.$integration('salesforce').' is not configured in .env (Sheriff → APIs).');
            case 'check_status':
                $state = collect(['sent' => $r->status === 'sent', 'opened' => $r->opened_at, 'clicked' => $r->clicked_at, 'dropped' => $r->dropped_at])->map(fn ($v) => $v ? 'y' : 'n');
                $this->log($model, $r, 'checked', 'Status checked: '.$state->map(fn ($v, $k) => "$k=$v")->implode(', '));

                return $go($ready('salesforce')
                    ? 'Recorded status: '.$state->map(fn ($v, $k) => "$k $v")->implode(' · ').'. Live tracking from SalesForce needs its API client, which isn\'t built yet.'
                    : 'Recorded status: '.$state->map(fn ($v, $k) => "$k $v")->implode(' · ').'. SalesForce is not configured, so nothing newer can be fetched.', false);
            case 'duplicate':
                /** @var Payment $r */
                $copy = $r->replicate(['confirmation', 'reversed_at', 'reversed_by']);
                $copy->fill(['status' => 'Pending', 'paid_on' => today(), 'reference' => 'COPY-'.$r->id.'-'.now()->format('His')])->save();
                $this->log($model, $r, 'duplicated', 'Duplicated as payment '.$copy->id);

                return redirect()->route('corral.items.show', [$copy->id, $model])->with('status', 'Duplicated as a Pending payment of $'.number_format($copy->amount, 2).'.');
            case 'mark_deposit':
                abort_if($r->kind === 'Deposit', 422, 'Already a deposit.');
                DB::transaction(function () use ($r, $c) {
                    $r->update(['kind' => 'Deposit']);
                    $c?->increment('deposit_held', $r->amount);
                });
                $this->log($model, $r, 'updated', 'Marked as deposit ($'.number_format($r->amount, 2).' added to deposit held)');

                return $go('Marked as a deposit; $'.number_format($r->amount, 2).' added to the deposit held.');
            case 'regenerate':
            case 'cancel_rebill':
                $what = $action === 'regenerate' ? 'PDF regeneration' : 'Cancel & rebill';
                $this->log($model, $r, 'requested', $what.' requested');
                $this->extra($model, $r, [$action === 'regenerate' ? 'status' : 'bill_status' => $action === 'regenerate' ? 'regeneration requested' : 'cancel & rebill requested']);

                return $go($what.' logged. It is done by '.$integration('utilibill').($ready('utilibill') ? ', whose API client isn\'t built yet.' : ', which is not configured in .env (Sheriff → APIs).'), false);
            case 'check_account':
                $unpaid = max(0, (float) $r->amount - (float) $r->amount_paid);
                $this->log($model, $r, 'checked', 'Checked against the current account');

                return $go('Bill '.($r->invoice ?: $r->reference).' is on account '.$c->account.' ('.$c->status.'). Bill $'.number_format($r->amount, 2).', paid $'.number_format((float) $r->amount_paid, 2)
                    .', unpaid $'.number_format($unpaid, 2).'. Current account balance $'.number_format($c->balance, 2).'.', false);
            case 'link_accounts':
                /** @var PaymentMethod $r */
                $others = Customer::whereKeyNot($c->id)->where(fn ($q) => $q->where('email', $c->email)->when($c->phone, fn ($q) => $q->orWhere('phone', $c->phone)))->get();
                $added = 0;
                foreach ($others as $o) {
                    if (! $o->paymentMethods()->where('last4', $r->last4)->where('type', $r->type)->whereNull('removed_at')->exists()) {
                        $o->paymentMethods()->create(collect($r->getAttributes())->only(['type', 'last4', 'expires', 'nickname', 'vendor'])->all() + ['autopay' => false]);
                        $added++;
                    }
                }
                $this->log($model, $r, 'linked', 'Linked to '.$added.' other '.Str::plural('account', $added).' with the same email or phone');

                return $go($others->isEmpty() ? 'No other accounts share this customer\'s email or phone.' : 'Linked to '.$added.' of '.$others->count().' other '.Str::plural('account', $others->count()).' with the same email or phone.', $others->isNotEmpty());
            case 'recalculate':
                $avg = (int) round($c->bills()->where('billed_on', '>=', now()->subYear())->avg('kwh') ?? 0);
                $bucket = $avg >= 1500 ? 'High Avg' : ($avg >= 800 ? 'Mid Avg' : 'Low Avg');
                $r->update(['data' => array_merge($r->data ?? [], ['bucket_name' => $bucket, 'crunched' => 'y', 'last_crunched' => now()->toDateTimeString(), 'valid' => $avg ? 'y' : 'n'])]);
                $this->log($model, $r, 'recalculated', "Recalculated: 12-month average {$avg} kWh → {$bucket}");

                return $go("Recalculated from the last 12 months of bills: average {$avg} kWh, bucket {$bucket}.");
            case 'welcome_packet':
                $file = CustomerFile::firstOrCreate(['customer_id' => $c->id, 'kind' => 'welcome'], ['name' => 'Welcome Packet - '.$c->account.'.pdf', 'path' => 'welcome/'.$c->account.'.pdf']);
                $file->touch();
                $this->log($model, $r, 'requested', 'Welcome packet regenerated (file '.$file->id.')');

                return $go('Welcome packet file record refreshed. The PDF itself is stored on '.$integration('amazon').($ready('amazon') ? '.' : ', which is not configured in .env, so no PDF was written.'), $ready('amazon'));
        }

        abort(404);
    }

    // ---------- Helpers ----------

    /** Ticket models open the account ticket or the matching log ticket. */
    private function ticket(int $id, string $model): RedirectResponse
    {
        $c = Customer::findOrFail($id);
        $log = Items::TICKET_PAGES[$model] ?? null;

        return $log ? redirect()->route('corral.customers.log', [$c, $log]) : redirect()->route('corral.customers.ticket', $c);
    }

    private function processLogs(string $model, Model $r)
    {
        $names = [$model];
        if (method_exists($r, 'historyName')) {
            $names[] = $r->historyName()[0];
        } elseif ($n = config('history.models.'.$r::class)) {
            $names[] = $n[0];
        }
        $q = HistoryItem::with('user')->whereIn('model', array_unique($names))->where('record_id', $r->getKey());

        return $q->latest('created_at')->latest('id')->limit(100)->get();
    }

    private function parents(string $model, Model $r, ?Customer $c): array
    {
        if (! $c) {
            return [];
        }
        $out = [];
        foreach (Items::def($model)['parents'] as $p) {
            [$col1, $col2] = config('items.tickets.'.$p, [$p, null]);
            // Each parent links to that ticket's own page, under the same id that page shows
            $log = Items::TICKET_PAGES[$p] ?? null;
            // Items filed on a log ticket (payments, files & usage, logins, notes) have that log as their parent, not the account
            if ($p === 'TicketCart_model' && ($own = collect(Items::LOG_ITEMS)->search(fn ($models) => in_array($model, $models, true))) !== false) {
                [$col1, $col2] = ['Logs', Str::after(config('history.logs.'.$own)[0], 'Logs - ')];
                $log = $own;
            }
            $out[] = match (true) {
                $p === 'TicketCart_model' && $log === null => ['id' => $c->ticket, 'url' => route('corral.customers.ticket', $c), 'created' => $c->created_at, 'a' => $col1, 'b' => $c->account],
                $p === 'TicketBillUsageTx_model' && $r instanceof Bill => ['id' => 'BILL-'.$r->getKey(), 'url' => route('corral.customers.bill-ticket', [$c, $r]),
                    'created' => Items::created($r), 'a' => $col1, 'b' => $r->invoice ?: $r->reference],
                $log !== null => ['id' => HistoryController::logTicketId($c, $log), 'url' => route('corral.customers.log', [$c, $log]), 'created' => $c->created_at, 'a' => $col1, 'b' => $col2],
                default => ['id' => $c->ticket, 'url' => route('corral.customers.ticket', $c), 'created' => $c->created_at, 'a' => $col1, 'b' => $col2],
            };
        }

        return $out;
    }

    private function log(string $model, Model $r, string $action, string $summary, ?array $changes = null): void
    {
        History::record(['customer_id' => Items::customer($r)?->id, 'model' => $model, 'group' => 'Admin Changes', 'record_id' => $r->getKey(),
            'action' => $action, 'summary' => Str::limit($summary, 250), 'data' => Items::values($model, $r), 'changes' => $changes]);
    }

    private function extra(string $model, Model $r, array $fields): void
    {
        if (Items::def($model)['source'] === 'stored') {
            $r->update(['data' => array_merge($r->data ?? [], $fields)]);

            return;
        }
        $e = Items::extras($model, $r);
        DataItem::updateOrCreate(['model' => $model, 'record_id' => $r->getKey()],
            ['customer_id' => Items::customer($r)?->id, 'data' => array_merge($e?->data ?? [], $fields), 'summary' => Items::summary($model, $r)]);
    }
}
