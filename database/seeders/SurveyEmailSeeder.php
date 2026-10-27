<?php

namespace Database\Seeders;

use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\Survey;
use App\Support\SurveyEmails;
use Illuminate\Database\Seeder;

/** Past 90-day check-in and farewell survey emails (fictional): everyone who answered was sent one, plus some who didn't answer. */
class SurveyEmailSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(90);
        foreach (SurveyEmails::RULES as $rule => [, $template, $slug]) {
            $survey = Survey::where('slug', $slug)->first();
            if (! $survey) {
                continue;
            }
            $answered = $survey->responses()->whereNotNull('customer_id')->get(['customer_id', 'created_at']);
            $others = Customer::whereNotNull('email')->whereNotIn('id', $answered->pluck('customer_id'))->inRandomOrder()->limit(14)->pluck('id');
            foreach ($answered->map(fn ($r) => [$r->customer_id, $r->created_at->copy()->subDays(mt_rand(1, 3))])->concat($others->map(fn ($id) => [$id, now()->subDays(mt_rand(1, 40))])) as [$id, $at]) {
                ContactLog::create(['customer_id' => $id, 'channel' => 'Email', 'template' => $template, 'body' => 'Survey invitation (sample)', 'status' => 'sent',
                    'sent_at' => $at, 'opened_at' => mt_rand(0, 2) ? $at->copy()->addHours(mt_rand(1, 30)) : null, 'created_at' => $at, 'updated_at' => $at]);
            }
        }
    }
}
