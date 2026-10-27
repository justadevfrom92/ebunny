<?php

namespace App\Support;

use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Builder;

/**
 * The automatic survey emails (sent daily by `php artisan et:survey-emails`, shown in Rodeo → Survey Emails):
 * a 90-day check-in to customers about 90 days after their service started, and a farewell survey to
 * customers whose service has ended. Each customer gets each one once; suppressed emails are skipped.
 */
class SurveyEmails
{
    /** key => [name, template, survey slug, who gets it] */
    public const RULES = [
        '90_day' => ['90-Day Check-In', 'Survey - 90 Day Check-In', '90-day-check-in', 'Customers whose service started 90 days ago (up to two weeks late, so nobody is missed)'],
        'farewell' => ['Farewell Survey', 'Survey - Farewell', 'farewell', 'Customers whose service ended (dropped, moved out, cancelled or disconnected) in the last 30 days'],
    ];

    public const LEAVING = ['Dropped - Churned', 'Moved Out', 'Cancelled', 'Disconnected'];

    /** Who should get it now and hasn't yet. */
    public static function due(string $rule): Builder
    {
        $template = self::RULES[$rule][1];
        $q = Customer::whereNotNull('email')->where('email', '!=', '')
            ->whereDoesntHave('contactLogs', fn ($l) => $l->where('template', $template));

        return $rule === '90_day'
            ? $q->whereBetween('actual_start', [today()->subDays(104)->toDateString(), today()->subDays(90)->toDateString()])->whereNotIn('status', self::LEAVING)
            : $q->whereIn('status', self::LEAVING)->where('updated_at', '>=', now()->subDays(30));
    }

    /** Send one rule's emails; returns [sent, skipped]. */
    public static function send(string $rule): array
    {
        [, $name] = self::RULES[$rule];
        $template = EmailTemplate::where('name', $name)->first();
        if (! $template) {
            return [0, 0];
        }
        $ready = collect(config('admin.integrations.salesforce.env'))->every(fn ($v) => filled($v));
        $sent = $skipped = 0;
        self::due($rule)->with('plan')->chunkById(200, function ($customers) use ($template, $ready, &$sent, &$skipped) {
            foreach ($customers as $c) {
                if (EmailSuppression::has($c->email)) {
                    $skipped++;

                    continue;
                }
                ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => $template->name, 'body' => strip_tags($template->renderBody($c)),
                    'status' => $ready ? 'queued' : 'not sent', 'sent_at' => now()]);
                $sent++;
            }
        });

        return [$sent, $skipped];
    }

    /** For Rodeo → Survey Emails: sent in the last 30 days, waiting now, answers, and the response rate. */
    public static function stats(string $rule): array
    {
        [, $name, $slug] = self::RULES[$rule];
        $sent = ContactLog::where('template', $name);
        $survey = Survey::where('slug', $slug)->first();
        $total = (clone $sent)->count();
        $answered = $survey ? $survey->responses()->whereIn('customer_id', (clone $sent)->select('customer_id'))->distinct()->count('customer_id') : 0;

        return ['sent30' => (clone $sent)->where('created_at', '>=', now()->subDays(30))->count(), 'total' => $total, 'due' => self::due($rule)->count(),
            'answered' => $answered, 'rate' => $total ? round(100 * $answered / $total) : null, 'survey' => $survey, 'template' => EmailTemplate::where('name', $name)->first()];
    }
}
