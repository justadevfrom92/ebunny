<?php

use App\Reports\BillsReport;
use App\Reports\ContactsReport;
use App\Reports\ErcotReport;
use App\Reports\ExceptionsReport;
use App\Reports\NotesReport;
use App\Reports\OrdersReport;
use App\Reports\PaymentsReport;
use App\Reports\PhonecallsReport;
use App\Reports\ReceivablesReport;
use App\Reports\StarsReport;

/*
|--------------------------------------------------------------------------
| Walker (reporting)
|--------------------------------------------------------------------------
| Every report the admin can run. Each is a class in app/Reports, used by
| Walker and by Corral's report screens. 'model' is the record type it reads
| (the same model names the history tracker uses).
*/

return [

    'reports' => [
        'orders' => ['title' => 'Orders', 'class' => OrdersReport::class, 'model' => 'TicketCustomer_model', 'category' => 'Customers',
            'description' => 'New orders (accounts) by order date and status.'],
        'notes' => ['title' => 'Notes', 'class' => NotesReport::class, 'model' => 'ItemNote_model', 'category' => 'Customers',
            'description' => 'Account notes by agent, date, text and priority.'],
        'phonecalls' => ['title' => 'Phonecalls', 'class' => PhonecallsReport::class, 'model' => 'ItemCall_model', 'category' => 'Customers',
            'description' => 'Calls by date, account, agent id or phone number.'],
        'contacts' => ['title' => 'Emails & Texts', 'class' => ContactsReport::class, 'model' => 'ItemEmailSalesforce_model', 'category' => 'Customers',
            'description' => 'Emails and texts sent to customers, with opens and clicks.'],
        'exceptions' => ['title' => 'Exception Queues', 'class' => ExceptionsReport::class, 'model' => 'TicketQueue_model', 'category' => 'Customers',
            'description' => 'Work items in the exception queues and how long they have been open.'],
        'payments' => ['title' => 'Payments', 'class' => PaymentsReport::class, 'model' => 'ItemPayment_model', 'category' => 'Billing',
            'description' => 'Payments by date, status and source.'],
        'bills' => ['title' => 'Bills', 'class' => BillsReport::class, 'model' => 'ItemFileBill_model', 'category' => 'Billing',
            'description' => 'Bills issued, with usage and amounts.'],
        'receivables' => ['title' => 'Accounts Receivable Aging', 'class' => ReceivablesReport::class, 'model' => 'TicketCustomer_model', 'category' => 'Billing',
            'description' => 'Open balances aged Current, 1-30, 31-60, 61-90 and 90+ days past due.'],
        'ercot' => ['title' => 'ERCOT Transactions', 'class' => ErcotReport::class, 'model' => 'ItemErcot81405_model', 'category' => 'Market',
            'description' => 'Market transactions (814s, 867s) by date, type and status.'],
        'stars' => ['title' => 'Rewards Stars', 'class' => StarsReport::class, 'model' => 'ItemProductReward_model', 'category' => 'Rewards',
            'description' => 'Stars earned and redeemed.'],
    ],

    // A run still "running" after this many minutes is shown as "Did not finish"
    'timeout_minutes' => 60,

    // File folders (Walker → Files). Every uploaded or generated file is in one of these.
    'upload_categories' => ['ETL', 'Legal Documents', 'Customer Templates', 'Finance', 'Utility / ERCOT', 'Vendor', 'Marketing', 'Operations', 'Regulatory', 'Other'],

    // Menu links straight to a folder: slug => category
    'folders' => ['etl' => 'ETL', 'legal' => 'Legal Documents', 'templates' => 'Customer Templates', 'finance' => 'Finance', 'ercot' => 'Utility / ERCOT', 'vendor' => 'Vendor'],

    // ETL feeds: files that come in from, or go out to, other companies on a schedule. Each run is
    // recorded as a job run (command "etl:<key>"), so it shows on its feed page and in Sheriff → Timeline.
    'etl' => [
        'ercot-867' => ['name' => 'ERCOT 867_03 Usage Import', 'direction' => 'In', 'party' => 'ERCOT (via market gateway)', 'schedule' => 'Hourly', 'cron' => '0 * * * *',
            'format' => 'EDI X12 → CSV', 'file' => 'ercot_867_03_{date}_{hour}.csv', 'target' => 'Usage reads on each account'],
        'ercot-814' => ['name' => 'ERCOT 814 Enrollment Responses', 'direction' => 'In', 'party' => 'ERCOT (via market gateway)', 'schedule' => 'Every 10 minutes', 'cron' => '*/10 * * * *',
            'format' => 'EDI X12', 'file' => 'ercot_814_{date}_{time}.edi', 'target' => 'Orders (accepted, rejected, switch dates)'],
        'utilibill-bills' => ['name' => 'Utilibill Bill Export', 'direction' => 'In', 'party' => 'Utilibill', 'schedule' => 'Daily 4:00 AM', 'cron' => '0 4 * * *',
            'format' => 'CSV + PDF zip', 'file' => 'bills_{date}.zip', 'target' => 'Bills and bill PDFs (S3 bills/)'],
        'lockbox' => ['name' => 'Bank Lockbox Payments', 'direction' => 'In', 'party' => 'Sample Bank lockbox', 'schedule' => 'Weekdays 7:30 AM', 'cron' => '30 7 * * 1-5',
            'format' => 'BAI2', 'file' => 'lockbox_{date}.bai', 'target' => 'Payments (check and money order)'],
        'stripe-settlement' => ['name' => 'Card Settlement Report', 'direction' => 'In', 'party' => 'Card processor', 'schedule' => 'Daily 6:15 AM', 'cron' => '15 6 * * *',
            'format' => 'CSV', 'file' => 'settlement_{date}.csv', 'target' => 'Payment status and fees'],
        'quickbooks-journal' => ['name' => 'Journal Export', 'direction' => 'Out', 'party' => 'QuickBooks', 'schedule' => 'Daily 11:30 PM', 'cron' => '30 23 * * *',
            'format' => 'IIF', 'file' => 'journal_{date}.iif', 'target' => 'Ledger entries for the day'],
        'monthly-payment-letters' => ['name' => 'Monthly Payment Letters', 'direction' => 'Out', 'party' => 'Print & mail vendor', 'schedule' => '1st of the month 5:00 AM', 'cron' => '0 5 1 * *',
            'format' => 'PDF batch + CSV manifest', 'file' => 'payment_letters_{month}.zip', 'target' => 'Customer Templates → Monthly Payment Reminder'],
        'collections' => ['name' => 'Collections Placement', 'direction' => 'Out', 'party' => 'Collections agency', 'schedule' => 'Mondays 8:00 AM', 'cron' => '0 8 * * 1',
            'format' => 'Fixed-width text', 'file' => 'placements_{date}.txt', 'target' => 'Accounts 90+ days past due'],
    ],

];
