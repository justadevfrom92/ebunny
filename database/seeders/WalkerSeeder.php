<?php

namespace Database\Seeders;

use App\Jobs\RunReport;
use App\Models\JobRun;
use App\Models\ReportRun;
use App\Models\ReportUpload;
use App\Models\User;
use App\Reports\Report;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Sample Walker data: report runs in every state and a few uploaded report files. */
class WalkerSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $finance = User::where('email', 'finance@example.com')->first() ?? $admin;
        $make = function (string $key, ?User $by, array $params, string $status, $at) {
            $def = Report::definition($key);

            return ReportRun::create(['user_id' => $by?->id, 'app' => 'walker', 'report' => $key, 'title' => $def['title'], 'model' => $def['model'],
                'params' => $params + Report::make($key)->defaults(), 'rows' => 0, 'status' => $status, 'created_at' => $at]);
        };

        // Completed: actually run, so they have real results and files
        foreach ([['payments', $finance, 26], ['receivables', $finance, 8], ['orders', $admin, 5], ['bills', $finance, 2], ['stars', $admin, 1]] as [$key, $by, $hoursAgo]) {
            $run = $make($key, $by, [], 'queued', now()->subHours($hoursAgo));
            (new RunReport($run->id))->handle();
            $run->update(['started_at' => now()->subHours($hoursAgo), 'finished_at' => now()->subHours($hoursAgo)->addSeconds(4), 'duration_ms' => 4000,
                'seen_at' => $hoursAgo > 2 ? now()->subHours($hoursAgo - 1) : null]);
        }
        // Error
        $make('ercot', $admin, ['type' => '867_03'], 'failed', now()->subHours(3))
            ->update(['started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3)->addSeconds(41),
                'error' => 'Utilibill export timed out after 40 seconds (sample error).']);
        // Running now, and one that never finished
        $make('contacts', $admin, [], 'running', now()->subMinutes(4))->update(['started_at' => now()->subMinutes(4)]);
        $make('exceptions', $finance, ['open' => 'all'], 'running', now()->subHours(5))->update(['started_at' => now()->subHours(5)]);

        // Files in each folder (small fictional sample files)
        $letter = fn (string $lang) => $lang === 'es'
            ? "Estimado/a {{customer.first_name}}:\n\nSu pago mensual de {{bill.amount_due}} para la cuenta {{customer.account}} vence el {{bill.due_date}}.\nPague en línea en {{site.url}}/myaccount o llame al {{site.phone}}.\n\nGracias,\n{{brand.name}}\n"
            : "Dear {{customer.first_name}},\n\nYour monthly payment of {{bill.amount_due}} for account {{customer.account}} is due on {{bill.due_date}}.\nPay online at {{site.url}}/myaccount or call {{site.phone}}.\n\nThank you,\n{{brand.name}}\n";
        $files = [
            ['Monthly Revenue Close - September', 'Finance', 'revenue-close-2026-09.csv', "Account Group,Revenue\nResidential,412880.11\nSmall Business,98012.40\n", 'Month-end close workbook export.'],
            ['Deposits Held - Q3', 'Finance', 'deposits-q3.csv', "Month,Held,Returned\nJul,18200.00,2400.00\nAug,19150.00,3100.00\nSep,18760.00,2950.00\n", 'Customer deposits by month.'],
            ['ERCOT 867 Usage Exceptions', 'Utility / ERCOT', 'ercot-867-exceptions.csv', "ESIID,Issue\n10089011000000000000,Missing read\n", 'Reads that failed validation.'],
            ['TDSP Charges Reconciliation - September', 'Utility / ERCOT', 'tdsp-recon-2026-09.csv', "TDSP,Billed,Invoiced,Difference\nOncor,88120.40,88120.40,0.00\nCenterPoint,61004.18,61010.02,-5.84\n", 'Billed delivery charges against the utility invoices.'],
            ['ERCOT 867_03 Import - Today 6 AM', 'ETL', 'ercot_867_03_sample_06.csv', "ESIID,Read Date,kWh,Status\n10089011000000000001,2026-10-18,31.2,Loaded\n10089011000000000002,2026-10-18,28.7,Loaded\n10089011000000000003,2026-10-18,,Rejected: missing kWh\n", 'Hourly usage import (sample rows).'],
            ['Card Settlement - Yesterday', 'ETL', 'settlement_sample.csv', "Batch,Payments,Gross,Fees,Net\n4471,212,24830.15,620.75,24209.40\n", 'Daily settlement file from the card processor.'],
            ['Collections Placement - This Week', 'ETL', 'placements_sample.txt', "ACCT      NAME                BALANCE   DAYS\n100118    SAMPLE CUSTOMER A      412.18    97\n100342    SAMPLE CUSTOMER B      188.40    112\n", 'Fixed-width file sent to the collections agency.'],
            ['Terms of Service - Residential (v2026.3)', 'Legal Documents', 'terms-of-service-residential-v2026-3.txt', "TERMS OF SERVICE (SAMPLE)\n\n1. Agreement. These terms are between you and the retail electric provider...\n2. Term and price...\n3. Billing and payment...\n", 'Current residential terms. Approved by Legal 9/2026.'],
            ['Your Rights as a Customer (YRAC)', 'Legal Documents', 'yrac-2026.txt', "YOUR RIGHTS AS A CUSTOMER (SAMPLE)\n\nYou have the right to receive a copy of your contract...\n", 'Required disclosure, sent with every welcome packet.'],
            ['Electricity Facts Label - Template', 'Legal Documents', 'efl-template.txt', "ELECTRICITY FACTS LABEL (SAMPLE)\nPlan: {{plan.name}}\nAverage price per kWh: 500 kWh {{plan.p500}} / 1000 kWh {{plan.p1000}} / 2000 kWh {{plan.p2000}}\n", 'Template filled for each plan and TDSP.'],
            ['Privacy Policy (v2026.1)', 'Legal Documents', 'privacy-policy-v2026-1.txt', "PRIVACY POLICY (SAMPLE)\n\nWe collect the information needed to provide electric service...\n", 'Published on the website.'],
            ['Monthly Payment Reminder - English', 'Customer Templates', 'monthly-payment-reminder-en.txt', $letter('en'), 'Mailed and emailed with the monthly payment letters run (Walker → ETL Feeds).'],
            ['Monthly Payment Reminder - Spanish', 'Customer Templates', 'monthly-payment-reminder-es.txt', $letter('es'), 'Spanish version of the monthly payment reminder.'],
            ['Past Due Notice - English', 'Customer Templates', 'past-due-notice-en.txt', "Dear {{customer.first_name}},\n\nYour account {{customer.account}} is past due by {{balance.past_due}}. Please pay by {{notice.pay_by}} to avoid disconnection.\n", 'Sent when an account is 15 days past due.'],
            ['Disconnection Notice - English', 'Customer Templates', 'disconnection-notice-en.txt', "IMPORTANT NOTICE\n\nService at {{address.line}} may be disconnected on or after {{notice.disconnect_on}} unless {{balance.past_due}} is paid.\n", 'Required 10-day notice before disconnection.'],
            ['Welcome Letter - English', 'Customer Templates', 'welcome-letter-en.txt', "Welcome, {{customer.first_name}}!\n\nYour service at {{address.line}} starts on {{order.start_date}}. Your plan: {{plan.name}}.\n", 'Included in the welcome packet.'],
            ['Meter Vendor Monthly Report', 'Vendor', 'meter-vendor-2026-09.csv', "Region,Installs,Swaps\nNorth,112,18\nHouston,96,22\n", 'From the meter services vendor.'],
            ['Call Center Vendor Scorecard - September', 'Vendor', 'call-center-scorecard-2026-09.csv', "Metric,Target,Actual\nAnswer rate,95%,96.4%\nAverage handle time,6:00,5:41\n", 'Monthly scorecard from the outside call center.'],
            ['Q3 Campaign Results', 'Marketing', 'q3-campaigns.csv', "Campaign,Sent,Opened\nFall Renewal,1200,540\n", 'Campaign totals for the quarter.'],
        ];
        foreach ($files as $n => [$title, $category, $name, $content, $description]) {
            $path = 'report-uploads/sample-'.$name;
            Storage::disk('local')->put($path, $content);
            $u = ReportUpload::create(['title' => $title, 'category' => $category, 'path' => $path, 'original_name' => $name, 'size' => strlen($content),
                'user_id' => (in_array($category, ['Finance', 'ETL'], true) ? $finance : $admin)?->id, 'description' => $description]);
            $u->forceFill(['created_at' => now()->subDays($n % 9)->subHours($n), 'updated_at' => now()->subDays($n % 9)->subHours($n)])->save();
        }

        // ETL feed runs: the last two weeks of each feed, with a few failures to look into
        $problems = ['ercot-867' => 'Gateway timeout after 120 seconds; 0 of 1,842 rows loaded (sample error)', 'lockbox' => 'File not found on the bank SFTP (sample error)',
            'collections' => 'Agency SFTP rejected the login (sample error)', 'stripe-settlement' => '3 payments in the file have no matching payment (sample warning)'];
        foreach (config('walker.etl') as $key => $feed) {
            $every = match (true) {
                str_contains($feed['schedule'], 'minutes') => 180, str_contains($feed['schedule'], 'Hourly') => 240,
                str_contains($feed['schedule'], 'month') => 60 * 24 * 30, str_contains($feed['schedule'], 'Mondays') => 60 * 24 * 7, default => 60 * 24,
            };
            [$min, $hour] = explode(' ', $feed['cron']);
            $first = (match (true) {
                str_contains($feed['schedule'], 'month') => now()->subMonths(2)->startOfMonth(),
                str_contains($feed['schedule'], 'Mondays') => now()->subDays(14)->startOfWeek(),
                default => now()->subDays(14)->startOfDay(),
            })->setTime(is_numeric($hour) ? (int) $hour : 0, is_numeric($min) ? (int) $min : 0);
            for ($at = $first, $i = 0; $at < now(); $at = str_contains($feed['schedule'], 'month') ? $at->copy()->addMonthNoOverflow() : $at->copy()->addMinutes($every), $i++) {
                if (str_contains($feed['schedule'], 'Weekdays') && $at->isWeekend()) {
                    continue;
                }
                $bad = isset($problems[$key]) && $i % 9 === 4;
                $status = $bad ? ($key === 'stripe-settlement' ? 'Warning' : 'Failed') : 'OK';
                $rows = mt_rand(40, 2400);
                $ms = mt_rand(800, 45000);
                JobRun::create(['command' => 'etl:'.$key, 'status' => $status,
                    'message' => $bad ? $problems[$key] : ($feed['direction'] === 'In' ? 'Loaded '.number_format($rows).' rows from ' : 'Sent '.number_format($rows).' rows in ').str_replace(['{date}', '{hour}', '{time}', '{month}'], [$at->format('Ymd'), $at->format('H'), $at->format('His'), $at->format('Ym')], $feed['file']),
                    'started_at' => $at, 'finished_at' => $at->copy()->addMilliseconds($ms), 'duration_ms' => $ms]);
            }
        }
    }
}
