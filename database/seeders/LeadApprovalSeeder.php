<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\LeadApproval;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Sample Lead Approvals (fictional) on Friends & Family accounts: two waiting, one approved, one denied. */
class LeadApprovalSeeder extends Seeder
{
    public function run(): void
    {
        $csr = User::where('email', 'csr@example.com')->first();
        $lead = User::where('email', 'lead@example.com')->first();
        $accounts = Customer::whereHas('flags', fn ($q) => $q->where('flag', 'VIP - Friends & Family'))->orderBy('id')->get();
        if (! $csr || ! $lead || $accounts->isEmpty()) {
            return;
        }
        $c = fn ($i) => $accounts[$i % $accounts->count()];
        $rows = [
            [$c(0), 'add-bill-credit', ['amount' => '35.00', 'description' => 'Courtesy credit for the outage'], 'Out of power for 2 days last week', 'pending', 1],
            [$c(1), 'payment-arrangement', ['installments' => '3', 'date' => now()->addDays(7)->toDateString()], 'Asked to split the balance', 'pending', 3],
            [$c(0), 'credit-waive-deposit', ['reason' => 'Paid on time for 12 months'], null, 'approved', 9],
            [$c(1), 'add-bill-credit', ['amount' => '120.00', 'description' => 'Goodwill credit'], 'Customer asked for a credit', 'denied', 14],
        ];
        foreach ($rows as [$cust, $action, $input, $note, $status, $daysAgo]) {
            $at = now()->subDays($daysAgo)->setTime(10, 15);
            $a = LeadApproval::create(['customer_id' => $cust->id, 'action' => $action, 'input' => $input, 'note' => $note, 'requested_by' => $csr->id, 'status' => $status,
                'decided_by' => $status === 'pending' ? null : $lead->id, 'decided_at' => $status === 'pending' ? null : $at->copy()->addHours(2),
                'decision_note' => $status === 'denied' ? 'Already credited for that bill' : null, 'result' => $status === 'approved' ? 'Deposit waived' : null]);
            $a->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
        }
    }
}
