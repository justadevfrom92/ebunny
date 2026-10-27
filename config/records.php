<?php

use App\Models\AiAutomationRun;
use App\Models\ApiLog;
use App\Models\Bill;
use App\Models\ContactLog;
use App\Models\CrmActivity;
use App\Models\CustomerFlag;
use App\Models\EmailTestSend;
use App\Models\ErcotTransaction;
use App\Models\Heartbeat;
use App\Models\HeartbeatAction;
use App\Models\HistoryItem;
use App\Models\IntegrationTest;
use App\Models\JobRun;
use App\Models\LedgerEntry;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Phonecall;
use App\Models\PlanTerm;
use App\Models\QueueLog;
use App\Models\ReferenceRow;
use App\Models\ServiceAddress;
use App\Models\SiteVisit;
use App\Models\StarEntry;

/*
| Records that show up in tables without a page of their own (notes, star entries, API calls, test runs,
| visits…). Each app has /admin/{app}/records/{type}/{id} so the ID in a table always opens something,
| without linking into another app. type => [model class (or "table:<name>"), label].
*/
return [
    'api_log' => [ApiLog::class, 'API Call'],
    'automation_run' => [AiAutomationRun::class, 'Automation Run'],
    'bill' => [Bill::class, 'Bill'],
    'contact_log' => [ContactLog::class, 'Email / Text Sent'],
    'crm_activity' => [CrmActivity::class, 'CRM Activity'],
    'customer_flag' => [CustomerFlag::class, 'Account Flag'],
    'email_test' => [EmailTestSend::class, 'Template Test Send'],
    'ercot_transaction' => [ErcotTransaction::class, 'ERCOT Transaction'],
    'failed_job' => ['table:failed_jobs', 'Failed Job'],
    'heartbeat' => [Heartbeat::class, 'Heartbeat Check'],
    'heartbeat_action' => [HeartbeatAction::class, 'Heartbeat Control'],
    'history_item' => [HistoryItem::class, 'History Entry'],
    'integration_test' => [IntegrationTest::class, 'Connection Test'],
    'job_run' => [JobRun::class, 'Job Run'],
    'ledger_entry' => [LedgerEntry::class, 'Credit / Debit'],
    'note' => [Note::class, 'Note'],
    'payment' => [Payment::class, 'Payment'],
    'phonecall' => [Phonecall::class, 'Phone Call'],
    'plan_term' => [PlanTerm::class, 'Plan Term'],
    'queue_log' => [QueueLog::class, 'Queue Log'],
    'reference_row' => [ReferenceRow::class, 'Table Row'],
    'service_address' => [ServiceAddress::class, 'Service Address'],
    'site_visit' => [SiteVisit::class, 'Site Visit'],
    'star_entry' => [StarEntry::class, 'Star Entry'],
];
