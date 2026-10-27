<?php

namespace App\Console\Commands;

use App\Support\SurveyEmails;

/** Sends the automatic survey emails: the 90-day check-in and the farewell survey (Rodeo → Survey Emails). */
class SurveyEmailsCommand extends TrackedCommand
{
    protected $signature = 'et:survey-emails {--user= : ID of the admin who ran it}';

    protected $description = 'Send the 90-day check-in and farewell survey emails';

    protected function work(): array
    {
        $out = [];
        foreach (SurveyEmails::RULES as $rule => [$name]) {
            [$sent, $skipped] = SurveyEmails::send($rule);
            $out[] = $name.': '.$sent.' sent'.($skipped ? ', '.$skipped.' skipped (suppressed)' : '');
        }

        return ['OK', implode(' · ', $out)];
    }
}
