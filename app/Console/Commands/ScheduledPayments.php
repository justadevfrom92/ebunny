<?php

namespace App\Console\Commands;

use App\Models\ScheduledPayment;
use App\Services\AccountActions;
use Illuminate\Support\Facades\DB;

/** Pays the My Account payments whose picked date and time has come. */
class ScheduledPayments extends TrackedCommand
{
    protected $signature = 'et:scheduled-payments {--user= : ID of the admin who ran it}';

    protected $description = 'Pay scheduled My Account payments that are due';

    protected function work(): array
    {
        $due = ScheduledPayment::with(['customer', 'method'])->where('status', 'scheduled')->where('pay_at', '<=', now())->orderBy('pay_at')->get();
        foreach ($due as $s) {
            if (! $s->method || $s->method->removed_at) {
                $s->update(['status' => 'failed', 'note' => 'The payment method was removed.']);

                continue;
            }
            DB::transaction(function () use ($s) {
                [$message] = (new AccountActions($s->customer, null, 'MyAccount'))->run('make-payment', ['amount' => (float) $s->amount, 'method' => $s->payment_method_id]);
                $s->update(['status' => 'paid', 'payment_id' => $s->customer->payments()->latest('id')->value('id'), 'note' => $message]);
            });
        }

        return ['OK', $due->isEmpty() ? 'Nothing due.' : $due->count().' scheduled '.str('payment')->plural($due->count()).' processed.'];
    }
}
