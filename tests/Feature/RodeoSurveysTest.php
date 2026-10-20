<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\Survey;
use App\Models\SurveyAnswerSet;
use App\Models\SurveyBankQuestion;
use App\Models\SurveyCategory;
use App\Models\SurveyQuestion;
use App\Models\User;
use App\Support\SurveyStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rodeo → Surveys: list, builder, results by category, responses, question bank, answer sets, categories and the public page. */
class RodeoSurveysTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function marketing(): User
    {
        return User::where('email', 'marketing@example.com')->firstOrFail();
    }

    public function test_every_surveys_page_opens(): void
    {
        $this->actingAs($this->marketing());
        $survey = Survey::where('slug', 'how-are-we-doing')->firstOrFail();
        $this->get(route('rodeo.surveys'))->assertOk()->assertSee('How are we doing?')->assertSee('Billing Check-In')->assertSee('Opens');
        $this->get(route('rodeo.surveys', ['state' => 'scheduled']))->assertOk()->assertSee('Billing Check-In')->assertDontSee('New Customer Welcome');
        $this->get(route('rodeo.surveys.create'))->assertOk()->assertSee('+ Add a Question')->assertSee('Choose a question');
        $this->get(route('rodeo.surveys.edit', $survey))->assertOk()->assertSee('Overall Experience')->assertSee('Rodeo / <a', false);
        $this->get(route('rodeo.surveys.results', $survey))->assertOk()->assertSee('Net Promoter Score')->assertSee('Customer Service')->assertSee('Billing &amp; Payments', false)->assertSee('Responses by Week');
        $this->get(route('rodeo.surveys.responses'))->assertOk()->assertSee('44 Responses');
        $this->get(route('rodeo.surveys.responses', ['band' => 'detractor', 'survey' => $survey->id]))->assertOk();
        $this->get(route('rodeo.surveys.responses', ['comments' => 1]))->assertOk()->assertSee('Faster answers on the phone.');
        $this->get(route('rodeo.surveys.responses.show', $survey->responses()->first()))->assertOk()->assertSee('Recommend Score')->assertSee('Overall Experience');
        $this->get(route('rodeo.surveys.bank'))->assertOk()->assertSee('Was your question answered on the first contact?');
        $this->get(route('rodeo.surveys.answers'))->assertOk()->assertSee('Satisfaction (5-point)')->assertSee('scores 5');
        $this->get(route('rodeo.surveys.categories'))->assertOk()->assertSee('Getting Started');
        $csv = $this->get(route('rodeo.surveys.export', $survey))->assertOk()->streamedContent();
        $this->assertStringContainsString('How likely are you to recommend us', $csv);
        $this->assertSame(35, substr_count(trim($csv), "\n") + 1);   // header + 34 responses
    }

    public function test_build_a_survey_from_the_bank_and_custom_questions(): void
    {
        $this->actingAs($this->marketing());
        $bank = SurveyBankQuestion::where('type', 'scale')->firstOrFail();
        $cat = SurveyCategory::where('name', 'Plans & Pricing')->firstOrFail();
        $this->post(route('rodeo.surveys.store'), ['title' => 'Quick poll', 'status' => 'active', 'thank_you' => 'Much obliged!', 'q' => [
            ['question' => 'How likely are you to recommend us?', 'type' => 'rating', 'required' => 1],
            ['question' => $bank->question, 'type' => 'scale', 'answer_set' => $bank->survey_answer_set_id, 'category' => $bank->survey_category_id, 'bank' => $bank->id],
            ['question' => 'Favorite thing?', 'type' => 'multi', 'options' => "Price\nService\nRewards", 'category' => $cat->id],
            ['question' => 'Missing answers', 'type' => 'single', 'options' => 'Only one'],
        ]])->assertSessionHasErrors('q.3.options');

        $this->post(route('rodeo.surveys.store'), ['title' => 'Quick poll', 'status' => 'active', 'thank_you' => 'Much obliged!', 'q' => [
            ['question' => 'How likely are you to recommend us?', 'type' => 'rating', 'required' => 1],
            ['question' => $bank->question, 'type' => 'scale', 'answer_set' => $bank->survey_answer_set_id, 'category' => $bank->survey_category_id, 'bank' => $bank->id],
            ['question' => 'Favorite thing?', 'type' => 'multi', 'options' => "Price\nService\nRewards", 'category' => $cat->id],
        ]])->assertRedirect();
        $survey = Survey::where('title', 'Quick poll')->firstOrFail();
        $qs = $survey->questions;
        $this->assertSame(['rating', 'scale', 'multi'], $qs->pluck('type')->all());
        $this->assertSame($bank->answerSet->options, $qs[1]->options);
        $this->assertSame(['Price', 'Service', 'Rewards'], $qs[2]->options);
        $this->assertTrue($qs[0]->required);

        // Reorder and drop a question; existing questions keep their ids
        $this->put(route('rodeo.surveys.update', $survey), ['title' => 'Quick poll', 'status' => 'active', 'q' => [
            ['id' => $qs[2]->id, 'question' => 'Favorite thing?', 'type' => 'multi', 'options' => "Price\nService", 'category' => $cat->id],
            ['id' => $qs[0]->id, 'question' => 'How likely are you to recommend us?', 'type' => 'rating'],
        ]])->assertRedirect();
        $this->assertSame([$qs[2]->id, $qs[0]->id], $survey->fresh()->questions->pluck('id')->all());

        $this->post(route('rodeo.surveys.copy', $survey))->assertRedirect();
        $copy = Survey::where('title', 'Quick poll (copy)')->firstOrFail();
        $this->assertSame(['closed', 2], [$copy->status, $copy->questions()->count()]);
        $this->delete(route('rodeo.surveys.destroy', $copy))->assertRedirect(route('rodeo.surveys'));
        $this->delete(route('rodeo.surveys.destroy', Survey::where('slug', 'how-are-we-doing')->first()))->assertStatus(422);
    }

    public function test_bank_answer_sets_and_categories(): void
    {
        $this->actingAs($this->marketing());
        $this->post(route('rodeo.surveys.categories.store'), ['name' => 'Outages', 'color' => '#123456'])->assertRedirect();
        $cat = SurveyCategory::where('name', 'Outages')->firstOrFail();
        $this->post(route('rodeo.surveys.answers.store'), ['name' => 'Speed', 'group' => 'Other', 'type' => 'scale', 'options' => "Slow\nOK\nFast"])->assertRedirect();
        $set = SurveyAnswerSet::where('name', 'Speed')->firstOrFail();
        $this->post(route('rodeo.surveys.bank.store'), ['question' => 'How fast was power restored?', 'type' => 'scale', 'survey_category_id' => $cat->id])->assertSessionHasErrors('survey_answer_set_id');
        $this->post(route('rodeo.surveys.bank.store'), ['question' => 'How fast was power restored?', 'type' => 'scale', 'survey_category_id' => $cat->id, 'survey_answer_set_id' => $set->id])->assertRedirect();
        $this->get(route('rodeo.surveys.bank', ['category' => $cat->id]))->assertOk()->assertSee('How fast was power restored?')->assertSee('Fast');

        // Editing a set updates the questions that use it
        $survey = Survey::firstOrFail();
        $q = $survey->questions()->create(['question' => 'Speed?', 'type' => 'scale', 'survey_answer_set_id' => $set->id, 'options' => $set->options, 'position' => 99]);
        $this->put(route('rodeo.surveys.answers.update', $set), ['name' => 'Speed', 'group' => 'Other', 'type' => 'scale', 'options' => "Very slow\nSlow\nOK\nFast"])->assertRedirect();
        $this->assertSame(['Very slow', 'Slow', 'OK', 'Fast'], $q->fresh()->options);
        $this->delete(route('rodeo.surveys.answers.destroy', $set))->assertStatus(422);

        // Deleting a category leaves its questions uncategorized
        $this->delete(route('rodeo.surveys.categories.destroy', $cat))->assertRedirect();
        $this->assertNull(SurveyBankQuestion::where('question', 'How fast was power restored?')->value('survey_category_id'));
    }

    public function test_results_are_grouped_by_category_with_scores(): void
    {
        $survey = Survey::where('slug', 'how-are-we-doing')->firstOrFail()->load('questions.category');
        $groups = SurveyStats::byCategory($survey, $survey->responses()->get());
        $this->assertSame(['Overall Experience', 'Customer Service', 'Billing & Payments', 'Plans & Pricing', 'About You'], $groups->map(fn ($g) => $g['category']->name)->values()->all());
        $rating = $groups->first()['questions'][0];
        $this->assertSame(34, $rating['count']);
        $this->assertSame(SurveyStats::surveyNps($survey), $rating['nps']);
        $this->assertSame($rating['count'], array_sum($rating['bands']));
        $scale = $groups->first()['questions'][1];
        $this->assertGreaterThan(1, $scale['avg']);
        $this->assertLessThanOrEqual(5, $scale['avg']);
    }

    public function test_public_survey_by_category_with_every_answer_type(): void
    {
        $survey = Survey::where('slug', 'how-are-we-doing')->firstOrFail()->load('questions');
        $c = Customer::firstOrFail();
        $link = EmailTemplate::make(['body' => '{{survey:how-are-we-doing}}'])->renderBody($c);
        $answers = $survey->questions->mapWithKeys(fn (SurveyQuestion $q) => [$q->id => match ($q->type) {
            'rating' => 9, 'multi' => array_slice($q->choices(), 0, 2), 'text' => 'Great', default => $q->choices()[0],
        }])->all();

        $this->get($link)->assertOk()->assertSee($survey->title)->assertSee('Answering as')->assertSee('Customer Service')->assertSee('Very satisfied');
        $path = parse_url($link, PHP_URL_PATH).'?'.parse_url($link, PHP_URL_QUERY);
        $this->post($path, ['a' => $answers])->assertRedirect();
        $r = $survey->responses()->latest('id')->first();
        $this->assertSame([$c->id, 'email', 9], [$r->customer_id, $r->source, $r->nps()]);
        $multi = $survey->questions->firstWhere('type', 'multi');
        $this->assertSame(array_slice($multi->choices(), 0, 2), $r->answer($multi));
        $this->followingRedirects()->post($path, ['a' => $answers])->assertSee('We read every answer');

        // Required questions, answer lists and tampered links are checked
        $required = $survey->questions->where('required', true)->first();
        $this->post('/survey/how-are-we-doing', ['a' => array_diff_key($answers, [$required->id => 1])])->assertSessionHasErrors('a.'.$required->id);
        $this->post('/survey/how-are-we-doing', ['a' => [$multi->id => ['Not an answer']] + $answers])->assertSessionHasErrors('a.'.$multi->id.'.0');
        $this->post('/survey/how-are-we-doing?c='.Customer::latest('id')->value('account'), ['a' => $answers])->assertRedirect();
        $this->assertSame([null, 'website'], [$survey->responses()->latest('id')->first()->customer_id, $survey->responses()->latest('id')->first()->source]);
        $this->get('/survey/how-are-we-doing')->assertOk()->assertDontSee('Answering as');

        // Scheduled surveys are not open yet
        $this->get('/survey/billing-check-in')->assertOk()->assertSee('This survey opens');
        $this->post('/survey/billing-check-in', ['a' => []])->assertStatus(410);
    }
}
