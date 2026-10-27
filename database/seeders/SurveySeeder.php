<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Survey;
use App\Models\SurveyAnswerSet;
use App\Models\SurveyBankQuestion;
use App\Models\SurveyCategory;
use Illuminate\Database\Seeder;

/** Rodeo → Surveys sample data: categories, answer sets, the question bank, three surveys and fictional responses. */
class SurveySeeder extends Seeder
{
    public function run(): void
    {
        $cats = collect([
            ['Overall Experience', 'How customers feel about us overall', '#102247'],
            ['Customer Service', 'Phone, chat and email support', '#00AEEF'],
            ['Billing & Payments', 'Bills, payments, AutoPay and due dates', '#EF8A00'],
            ['Website & My Account', 'Signing up online and managing the account', '#5dd3ff'],
            ['Plans & Pricing', 'Rates, terms and plan choices', '#137a3a'],
            ['Getting Started', 'Enrollment, switching and the first bill', '#C80611'],
            ['About You', 'How customers found us and what they want', '#96999d'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => SurveyCategory::create(['name' => $c[0], 'description' => $c[1], 'color' => $c[2], 'position' => $i])]);

        $sets = collect([
            ['Satisfaction (5-point)', 'Satisfaction', 'scale', ['Very dissatisfied', 'Dissatisfied', 'Neutral', 'Satisfied', 'Very satisfied']],
            ['Agreement (5-point)', 'Agreement', 'scale', ['Strongly disagree', 'Disagree', 'Neither agree nor disagree', 'Agree', 'Strongly agree']],
            ['Ease (5-point)', 'Ease', 'scale', ['Very difficult', 'Difficult', 'Neither', 'Easy', 'Very easy']],
            ['Likelihood (5-point)', 'Likelihood', 'scale', ['Very unlikely', 'Unlikely', 'Not sure', 'Likely', 'Very likely']],
            ['How often', 'Frequency', 'single', ['Never', 'Rarely', 'Sometimes', 'Often', 'Every month']],
            ['Contact channels', 'Channels', 'multi', ['Phone', 'Email', 'Text message', 'Website chat', 'My Account messages']],
            ['How you heard about us', 'Channels', 'single', ['Search engine', 'Friend or family', 'Ad', 'Power to Choose', 'Other']],
            ['What matters most', 'Other', 'multi', ['Low rate', 'Fixed price', 'Renewable energy', 'Rewards', 'Great service', 'No deposit']],
            ['Reasons for leaving', 'Other', 'single', ['Moving out of the area', 'Found a lower rate', 'Unhappy with customer service', 'Billing problems', 'Contract ended', 'Other']],
        ])->mapWithKeys(fn ($s) => [$s[0] => SurveyAnswerSet::create(['name' => $s[0], 'group' => $s[1], 'type' => $s[2], 'options' => $s[3]])]);

        $bank = collect([
            ['Overall Experience', 'How likely are you to recommend us to a friend or colleague?', 'rating', null, '0 = not at all likely, 10 = extremely likely'],
            ['Overall Experience', 'Overall, how satisfied are you with your electricity service?', 'scale', 'Satisfaction (5-point)', null],
            ['Overall Experience', 'How likely are you to renew when your contract ends?', 'scale', 'Likelihood (5-point)', null],
            ['Customer Service', 'How satisfied were you with your last call to customer service?', 'scale', 'Satisfaction (5-point)', null],
            ['Customer Service', 'Was your question answered on the first contact?', 'yes_no', null, null],
            ['Customer Service', 'How would you like us to contact you?', 'multi', 'Contact channels', 'Pick all that apply'],
            ['Billing & Payments', 'My bill is easy to understand.', 'scale', 'Agreement (5-point)', null],
            ['Billing & Payments', 'How easy is it to pay your bill?', 'scale', 'Ease (5-point)', null],
            ['Billing & Payments', 'Do you use AutoPay?', 'yes_no', null, null],
            ['Website & My Account', 'How easy was it to sign up on our website?', 'scale', 'Ease (5-point)', null],
            ['Website & My Account', 'How often do you sign in to My Account?', 'single', 'How often', null],
            ['Plans & Pricing', 'What matters most when you choose a plan?', 'multi', 'What matters most', 'Pick all that apply'],
            ['Plans & Pricing', 'My rate is fair for what I get.', 'scale', 'Agreement (5-point)', null],
            ['Getting Started', 'How easy was it to switch to us?', 'scale', 'Ease (5-point)', null],
            ['Getting Started', 'Did your service start on the date you expected?', 'yes_no', null, null],
            ['About You', 'How did you hear about us?', 'single', 'How you heard about us', null],
            ['About You', 'Anything we could do better?', 'text', null, 'Optional'],
            ['Overall Experience', 'What is the main reason you left?', 'single', 'Reasons for leaving', null],
            ['Overall Experience', 'How likely are you to come back to us someday?', 'scale', 'Likelihood (5-point)', null],
            ['Getting Started', 'How has your first three months with us been?', 'scale', 'Satisfaction (5-point)', null],
        ])->map(fn ($b) => SurveyBankQuestion::create(['survey_category_id' => $cats[$b[0]]->id, 'question' => $b[1], 'type' => $b[2],
            'survey_answer_set_id' => $b[3] ? $sets[$b[3]]->id : null, 'help' => $b[4]]))->keyBy('question');

        $make = function (array $fields, array $questions) use ($bank) {
            $survey = Survey::create($fields);
            foreach ($questions as $i => [$text, $required]) {
                $b = $bank[$text];
                $survey->questions()->create(['survey_category_id' => $b->survey_category_id, 'survey_bank_question_id' => $b->id, 'question' => $b->question, 'type' => $b->type,
                    'survey_answer_set_id' => $b->survey_answer_set_id, 'options' => $b->answerSet?->options, 'required' => $required, 'help' => $b->help, 'position' => $i]);
            }

            return $survey->load('questions');
        };

        $howAreWe = $make(['title' => 'How are we doing?', 'slug' => 'how-are-we-doing', 'intro' => 'Two minutes to help us serve you better.',
            'thank_you' => 'Thanks for telling us how we’re doing. We read every answer.', 'status' => 'active', 'opens_on' => today()->subMonths(3)], [
                ['How likely are you to recommend us to a friend or colleague?', true],
                ['Overall, how satisfied are you with your electricity service?', true],
                ['How satisfied were you with your last call to customer service?', false],
                ['Was your question answered on the first contact?', false],
                ['My bill is easy to understand.', false],
                ['How easy is it to pay your bill?', false],
                ['What matters most when you choose a plan?', false],
                ['How did you hear about us?', false],
                ['Anything we could do better?', false],
            ]);
        $onboarding = $make(['title' => 'New Customer Welcome', 'slug' => 'new-customer-welcome', 'intro' => 'You’re a few weeks in. How did getting started go?',
            'thank_you' => 'Thanks, and welcome aboard.', 'status' => 'active'], [
                ['How easy was it to sign up on our website?', true],
                ['How easy was it to switch to us?', true],
                ['Did your service start on the date you expected?', true],
                ['How would you like us to contact you?', false],
                ['Anything we could do better?', false],
            ]);
        $make(['title' => 'Billing Check-In', 'slug' => 'billing-check-in', 'intro' => 'Help us make bills clearer.', 'status' => 'active',
            'opens_on' => today()->addDays(10), 'closes_on' => today()->addDays(40)], [
                ['My bill is easy to understand.', true],
                ['Do you use AutoPay?', false],
                ['My rate is fair for what I get.', false],
            ]);

        // Sent automatically by the et:survey-emails cron (Rodeo → Survey Emails)
        $ninety = $make(['title' => '90-Day Check-In', 'slug' => '90-day-check-in', 'intro' => 'You’ve been with us about three months. How’s it going?',
            'thank_you' => 'Thanks! We read every answer.', 'status' => 'active'], [
                ['How has your first three months with us been?', true],
                ['How likely are you to recommend us to a friend or colleague?', true],
                ['My bill is easy to understand.', false],
                ['How likely are you to renew when your contract ends?', false],
                ['Anything we could do better?', false],
            ]);
        $farewell = $make(['title' => 'Sorry to See You Go', 'slug' => 'farewell', 'intro' => 'Thank you for being a customer. Tell us why you left; it helps us do better.',
            'thank_you' => 'Thank you, and good luck. We’d love to have you back someday.', 'status' => 'active'], [
                ['What is the main reason you left?', true],
                ['Overall, how satisfied are you with your electricity service?', false],
                ['How likely are you to come back to us someday?', false],
                ['Anything we could do better?', false],
            ]);

        // Fictional responses with a realistic spread
        $comments = ['Faster answers on the phone.', 'Text me when my bill is ready.', 'Happy so far, the app is easy.', 'The renewal letter was confusing.',
            'Love the rewards.', 'Rates went up at renewal, not thrilled.', 'Chat support was great.', 'Wish AutoPay was easier to set up.'];
        $customers = Customer::orderBy('id')->limit(44)->get();
        $pick = fn (array $list, int $i, int $skew = 0) => $list[min(count($list) - 1, max(0, ($i * 7 + $skew) % count($list)))];
        foreach ($customers->take(34) as $i => $c) {
            $answers = [];
            foreach ($howAreWe->questions as $q) {
                $a = match ($q->type) {
                    'rating' => (string) [10, 9, 9, 8, 10, 7, 6, 9, 10, 4, 8, 9, 10, 5, 9, 7, 10][$i % 17],
                    'scale' => $q->choices()[[4, 3, 3, 4, 2, 3, 4, 1, 3, 4][($i + $q->position) % 10]],
                    'yes_no' => $i % 4 ? 'Yes' : 'No',
                    'multi' => array_values(array_unique([$pick($q->choices(), $i), $pick($q->choices(), $i, 3)])),
                    'single' => $pick($q->choices(), $i),
                    default => $i % 3 ? null : $comments[($i / 3) % count($comments)],
                };
                if ($a !== null && ! (! $q->required && $i % 9 === 4)) {   // a few skip optional questions
                    $answers[$q->id] = $a;
                }
            }
            $howAreWe->responses()->create(['customer_id' => $i % 11 === 10 ? null : $c->id, 'answers' => $answers,
                'source' => ['email', 'email', 'website', 'my-account'][$i % 4], 'created_at' => now()->subDays(2 + $i * 2)->subHours($i)]);
        }
        foreach ($customers->slice(32)->values() as $i => $c) {
            $answers = [];
            foreach ($onboarding->questions as $q) {
                $answers[$q->id] = match ($q->type) {
                    'scale' => $q->choices()[[4, 3, 4, 2, 3][($i + $q->position) % 5]],
                    'yes_no' => $i === 2 ? 'No' : 'Yes',
                    'multi' => [$pick($q->choices(), $i), $pick($q->choices(), $i, 2)],
                    default => $i === 2 ? 'Start date slipped a week; nobody told me.' : null,
                };
            }
            $onboarding->responses()->create(['customer_id' => $c->id, 'answers' => array_filter($answers, fn ($a) => $a !== null), 'source' => 'email', 'created_at' => now()->subDays(1 + $i * 3)]);
        }
        foreach ([$ninety, $farewell] as $k => $s) {
            foreach ($customers->slice(10 * $k, 9)->values() as $i => $c) {
                $answers = [];
                foreach ($s->questions as $q) {
                    $answers[$q->id] = match ($q->type) {
                        'rating' => (string) [9, 10, 8, 7, 9, 10, 6, 9, 8][$i],
                        'scale' => $q->choices()[[4, 3, 4, 2, 3, 4, 1, 3, 4][$i]],
                        'single' => $q->choices()[[0, 1, 0, 2, 4, 0, 1, 3, 4][$i] % count($q->choices())],
                        default => [null, 'Great so far.', null, 'The first bill was confusing.', null, null, 'Rate went up at renewal.', null, null][$i],
                    };
                }
                $s->responses()->create(['customer_id' => $c->id, 'answers' => array_filter($answers, fn ($a) => $a !== null), 'source' => 'email', 'created_at' => now()->subDays(2 + $i * 3)]);
            }
        }
    }
}
