<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\SurveyAnswerSet;
use App\Models\SurveyBankQuestion;
use App\Models\SurveyCategory;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Support\SurveyStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rodeo → Surveys: every survey with its status and scores, the survey builder
 * (questions filed under categories, answers from reusable answer sets or the
 * question bank), results by category, all responses, and the bank, answer
 * sets and categories themselves.
 */
class SurveyController extends Controller
{
    // ---------- All surveys ----------

    public function index(Request $request): View
    {
        $surveys = Survey::with(['questions.category'])->withCount('responses')->withMax('responses', 'created_at')->latest()->get();
        $recent = SurveyResponse::with('survey.questions')->where('created_at', '>=', now()->subDays(30))->get();
        $state = $request->query('state');

        return view('backend.rodeo.surveys.index', [
            'surveys' => $state ? $surveys->filter(fn ($s) => $s->state() === $state)->values() : $surveys,
            'all' => $surveys,
            'state' => $state,
            'recent' => $recent->count(),
            'nps' => SurveyStats::nps($recent->map(fn ($r) => $r->nps())->filter(fn ($v) => $v !== null)->values()),
            'npsBySurvey' => $surveys->mapWithKeys(fn ($s) => [$s->id => SurveyStats::surveyNps($s)]),
        ]);
    }

    // ---------- Builder ----------

    public function create(Request $request): View
    {
        return $this->builder(new Survey(['status' => 'active']), collect());
    }

    public function edit(Survey $survey): View
    {
        return $this->builder($survey, $survey->questions);
    }

    private function builder(Survey $survey, Collection $questions): View
    {
        $bank = SurveyBankQuestion::with(['category', 'answerSet'])->orderBy('question')->get();

        return view('backend.rodeo.surveys.builder', [
            'survey' => $survey,
            'questions' => $questions,
            'categories' => SurveyCategory::orderBy('position')->orderBy('name')->get(),
            'sets' => SurveyAnswerSet::orderBy('group')->orderBy('name')->get(),
            'bank' => $bank,
            'bankJson' => $bank->map(fn ($b) => ['id' => $b->id, 'question' => $b->question, 'type' => $b->type, 'category' => $b->survey_category_id,
                'set' => $b->survey_answer_set_id, 'help' => $b->help])->values(),
            'responses' => $survey->exists ? $survey->responses()->count() : 0,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        return $this->save($request, $survey);
    }

    private function save(Request $request, ?Survey $survey): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'thank_you' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'closed'])],
            'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'],
            'q' => ['required', 'array', 'min:1'],
            'q.*.id' => ['nullable', 'integer'],
            'q.*.category' => ['nullable', 'exists:survey_categories,id'],
            'q.*.question' => ['required', 'string', 'max:250'],
            'q.*.type' => ['required', Rule::in(array_keys(SurveyQuestion::TYPES))],
            'q.*.answer_set' => ['nullable', 'exists:survey_answer_sets,id'],
            'q.*.options' => ['nullable', 'string', 'max:1500'],
            'q.*.required' => ['nullable', 'boolean'],
            'q.*.help' => ['nullable', 'string', 'max:200'],
            'q.*.bank' => ['nullable', 'exists:survey_bank_questions,id'],
        ], ['q.required' => 'Add at least one question.', 'q.*.question.required' => 'Every question needs its wording.']);

        // Validated arrays are rebuilt field by field, so take the question order from the form as sent
        $data['q'] = array_map(fn ($k) => $data['q'][$k], array_keys($request->input('q', [])));
        $sets = SurveyAnswerSet::whereIn('id', collect($data['q'])->pluck('answer_set')->filter())->get()->keyBy('id');
        $rows = [];
        foreach (array_values($data['q']) as $i => $q) {
            $set = isset($q['answer_set']) ? $sets[$q['answer_set']] ?? null : null;
            $options = null;
            if (in_array($q['type'], SurveyQuestion::LISTED, true)) {
                $options = $set ? $set->options : array_values(array_filter(array_map('trim', explode("\n", (string) ($q['options'] ?? '')))));
                if (count($options) < 2) {
                    throw ValidationException::withMessages(["q.$i.options" => 'Question '.($i + 1).' (“'.Str::limit($q['question'], 40).'”) needs an answer set or at least two answers.']);
                }
            }
            $rows[] = ['id' => $q['id'] ?? null, 'survey_category_id' => $q['category'] ?? null, 'question' => $q['question'], 'type' => $q['type'],
                'survey_answer_set_id' => in_array($q['type'], SurveyQuestion::LISTED, true) ? $set?->id : null, 'options' => $options,
                'required' => (bool) ($q['required'] ?? false), 'help' => $q['help'] ?? null, 'survey_bank_question_id' => $q['bank'] ?? null, 'position' => $i];
        }

        DB::transaction(function () use (&$survey, $data, $rows) {
            $fields = ['title' => $data['title'], 'intro' => $data['intro'] ?? null, 'thank_you' => $data['thank_you'] ?? null, 'status' => $data['status'],
                'opens_on' => $data['opens_on'] ?? null, 'closes_on' => $data['closes_on'] ?? null];
            $survey = $survey ? tap($survey)->update($fields) : Survey::create($fields + ['slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(4))]);
            $existing = $survey->questions()->get()->keyBy('id');
            $kept = [];
            foreach ($rows as $row) {
                $id = $row['id'];
                unset($row['id']);
                if ($id && $existing->has($id)) {
                    $existing[$id]->update($row);
                    $kept[] = $id;
                } else {
                    $kept[] = $survey->questions()->create($row)->id;
                }
            }
            // Removed questions go; answers already given to them stay in the responses
            $survey->questions()->whereNotIn('id', $kept)->delete();
        });

        return redirect()->route('rodeo.surveys.edit', $survey)->with('status', 'Survey saved');
    }

    /** A copy of a survey's questions and wording as a new closed survey, ready to change. */
    public function copy(Survey $survey): RedirectResponse
    {
        $new = DB::transaction(function () use ($survey) {
            $new = Survey::create(['title' => $survey->title.' (copy)', 'slug' => Str::slug($survey->title).'-'.Str::lower(Str::random(4)),
                'intro' => $survey->intro, 'thank_you' => $survey->thank_you, 'status' => 'closed']);
            foreach ($survey->questions as $q) {
                $new->questions()->create(collect($q->getAttributes())->except(['id', 'survey_id'])->all());
            }

            return $new;
        });

        return redirect()->route('rodeo.surveys.edit', $new)->with('status', 'Copied. The copy is closed until you open it.');
    }

    public function destroy(Survey $survey): RedirectResponse
    {
        abort_if($survey->responses()->exists(), 422, 'Surveys with responses can be closed, not deleted.');
        $survey->delete();

        return redirect()->route('rodeo.surveys')->with('status', 'Survey deleted');
    }

    // ---------- Results ----------

    public function results(Request $request, Survey $survey): View
    {
        $survey->load('questions.category');
        [$f, $responses] = $this->filtered($request, $survey);
        $weeks = collect(range(11, 0))->map(fn ($i) => now()->startOfWeek()->subWeeks($i))
            ->map(fn ($start) => ['label' => $start->format('M j'), 'count' => $survey->responses->filter(fn ($r) => $r->created_at->between($start, $start->copy()->endOfWeek()))->count()]);
        $required = $survey->questions->where('required', true);
        $complete = $responses->filter(fn ($r) => $survey->questions->every(fn ($q) => $r->answer($q) !== null))->count();
        $firstScale = $survey->questions->firstWhere('type', 'scale');

        return view('backend.rodeo.surveys.results', [
            'survey' => $survey, 'f' => $f, 'responses' => $responses,
            'groups' => SurveyStats::byCategory($survey, $responses),
            'nps' => SurveyStats::surveyNps($survey, $responses),
            'scale' => $firstScale ? SurveyStats::question($firstScale, $responses) : null,
            'complete' => $responses->count() ? (int) round(100 * $complete / $responses->count()) : null,
            'weeks' => $weeks, 'required' => $required->count(),
        ]);
    }

    /** One row per response, one column per question. */
    public function export(Request $request, Survey $survey): StreamedResponse
    {
        $survey->load('questions');
        [, $responses] = $this->filtered($request, $survey);
        $name = Str::slug($survey->title).'-responses-'.today()->toDateString().'.csv';

        return response()->streamDownload(function () use ($survey, $responses) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Response', 'Date', 'Account', 'Customer', 'Source', ...$survey->questions->map(fn ($q) => $q->question)->all()]);
            foreach ($responses as $r) {
                $cells = [$r->id, $r->created_at->format('Y-m-d H:i'), $r->customer?->account, $r->customer?->name, SurveyResponse::SOURCES[$r->source] ?? $r->source,
                    ...$survey->questions->map(fn ($q) => is_array($a = $r->answer($q)) ? implode('; ', $a) : $a)->all()];
                // Keep spreadsheet apps from running cell text as a formula
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $cells));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: array, 1: Collection<int, SurveyResponse>} */
    private function filtered(Request $request, Survey $survey): array
    {
        $f = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'source' => ['nullable', Rule::in(array_keys(SurveyResponse::SOURCES))]]);
        $responses = $survey->responses()->with(['customer', 'survey.questions'])
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($f['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->latest('created_at')->get();

        return [$f + ['from' => null, 'to' => null, 'source' => null], $responses];
    }

    // ---------- Responses ----------

    public function responses(Request $request): View
    {
        $f = $request->validate(['survey' => ['nullable', 'exists:surveys,id'], 'band' => ['nullable', Rule::in(['promoter', 'passive', 'detractor'])],
            'comments' => ['nullable', 'boolean'], 'source' => ['nullable', Rule::in(array_keys(SurveyResponse::SOURCES))],
            'account' => ['nullable', 'string', 'max:20'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $rows = SurveyResponse::with(['survey.questions.category', 'customer'])
            ->when($f['survey'] ?? null, fn ($q, $s) => $q->where('survey_id', $s))
            ->when($f['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->when($f['account'] ?? null, fn ($q, $a) => $q->whereHas('customer', fn ($c) => $c->where('account', trim($a))))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->latest('created_at')->get();
        // Score bands and comments live inside the answers, so they filter here
        if ($band = $f['band'] ?? null) {
            $rows = $rows->filter(fn ($r) => ($n = $r->nps()) !== null && match ($band) {
                'promoter' => $n >= 9, 'passive' => $n >= 7 && $n <= 8, default => $n <= 6
            });
        }
        if ($f['comments'] ?? false) {
            $rows = $rows->filter(fn ($r) => $this->comment($r) !== null);
        }
        $page = max(1, (int) $request->query('page', 1));
        $paged = new LengthAwarePaginator($rows->forPage($page, 25)->values(), $rows->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('backend.rodeo.surveys.responses', ['rows' => $paged, 'total' => $rows->count(), 'f' => $f, 'surveys' => Survey::orderBy('title')->get(['id', 'title']),
            'comment' => fn ($r) => $this->comment($r)]);
    }

    public function response(SurveyResponse $response): View
    {
        $response->load(['survey.questions.category', 'customer.plan']);
        $groups = $response->survey->questions->groupBy(fn ($q) => (string) ($q->survey_category_id ?? 0));

        return view('backend.rodeo.surveys.response', ['r' => $response, 'groups' => $groups,
            'prev' => SurveyResponse::where('id', '<', $response->id)->max('id'), 'next' => SurveyResponse::where('id', '>', $response->id)->min('id')]);
    }

    /** The first written answer in a response. */
    private function comment(SurveyResponse $r): ?string
    {
        foreach ($r->survey->questions->where('type', 'text') as $q) {
            if (filled($a = $r->answer($q))) {
                return (string) $a;
            }
        }

        return null;
    }

    // ---------- Question bank ----------

    public function bank(Request $request): View
    {
        return view('backend.rodeo.surveys.bank', [
            'categories' => SurveyCategory::with(['bankQuestions' => fn ($q) => $q->with('answerSet')->withCount('uses')->orderBy('question')])->orderBy('position')->orderBy('name')->get(),
            'uncategorized' => SurveyBankQuestion::with('answerSet')->withCount('uses')->whereNull('survey_category_id')->orderBy('question')->get(),
            'allCategories' => SurveyCategory::orderBy('position')->orderBy('name')->get(),
            'only' => $request->query('category'),
        ]);
    }

    /** New Question / Edit Question: its own page with a simple form. */
    public function bankForm(Request $request, ?SurveyBankQuestion $question = null): View
    {
        return view('backend.rodeo.surveys.bank-form', ['question' => $question ?? new SurveyBankQuestion(['type' => 'scale', 'survey_category_id' => $request->query('category')]),
            'sets' => SurveyAnswerSet::orderBy('group')->orderBy('name')->get(), 'categories' => SurveyCategory::orderBy('position')->orderBy('name')->get()]);
    }

    public function saveBank(Request $request, ?SurveyBankQuestion $question = null): RedirectResponse
    {
        $data = $request->validate([
            'survey_category_id' => ['nullable', 'exists:survey_categories,id'],
            'question' => ['required', 'string', 'max:250'],
            'type' => ['required', Rule::in(array_keys(SurveyQuestion::TYPES))],
            'survey_answer_set_id' => ['nullable', 'required_if:type,scale,single,multi', 'exists:survey_answer_sets,id'],
            'help' => ['nullable', 'string', 'max:200'],
        ], ['survey_answer_set_id.required_if' => 'Scale, Pick one and Pick any questions need an answer set.']);
        if (! in_array($data['type'], SurveyQuestion::LISTED, true)) {
            $data['survey_answer_set_id'] = null;
        }
        $question ? $question->update($data) : SurveyBankQuestion::create($data);

        return redirect()->route('rodeo.surveys.bank')->with('status', 'Question saved to the bank');
    }

    public function deleteBank(SurveyBankQuestion $question): RedirectResponse
    {
        $question->delete();   // surveys that used it keep their own copy

        return back()->with('status', 'Question removed from the bank');
    }

    // ---------- Answer sets ----------

    public function answers(Request $request): View
    {
        return view('backend.rodeo.surveys.answers', [
            'groups' => SurveyAnswerSet::withCount(['questions', 'bankQuestions'])->orderBy('name')->get()->groupBy('group')
                ->sortBy(fn ($g, $k) => array_search($k, SurveyAnswerSet::GROUPS) === false ? 99 : array_search($k, SurveyAnswerSet::GROUPS)),
        ]);
    }

    public function answerForm(?SurveyAnswerSet $set = null): View
    {
        return view('backend.rodeo.surveys.answer-form', ['set' => $set ?? new SurveyAnswerSet(['type' => 'scale'])]);
    }

    public function saveAnswerSet(Request $request, ?SurveyAnswerSet $set = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('survey_answer_sets')->ignore($set)],
            'group' => ['required', 'string', 'max:40'],
            'type' => ['required', Rule::in(array_keys(SurveyAnswerSet::TYPES))],
            'options' => ['required', 'string', 'max:1500'],
        ]);
        $data['options'] = array_values(array_unique(array_filter(array_map('trim', explode("\n", $data['options'])))));
        if (count($data['options']) < 2) {
            throw ValidationException::withMessages(['options' => 'An answer set needs at least two answers.']);
        }
        DB::transaction(function () use (&$set, $data) {
            $set ? $set->update($data) : $set = SurveyAnswerSet::create($data);
            // Questions using the set show the new wording; past answers keep theirs
            SurveyQuestion::where('survey_answer_set_id', $set->id)->update(['options' => json_encode($data['options'])]);
        });

        return redirect()->route('rodeo.surveys.answers')->with('status', 'Answer set saved');
    }

    public function deleteAnswerSet(SurveyAnswerSet $set): RedirectResponse
    {
        abort_if($set->questions()->exists() || $set->bankQuestions()->exists(), 422, 'This answer set is in use.');
        $set->delete();

        return back()->with('status', 'Answer set deleted');
    }

    // ---------- Categories ----------

    public function categories(Request $request): View
    {
        return view('backend.rodeo.surveys.categories', [
            'categories' => SurveyCategory::withCount(['bankQuestions', 'questions'])->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function categoryForm(?SurveyCategory $category = null): View
    {
        return view('backend.rodeo.surveys.category-form', ['category' => $category ?? new SurveyCategory(['color' => '#00AEEF'])]);
    }

    public function saveCategory(Request $request, ?SurveyCategory $category = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('survey_categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:200'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['position'] ??= (int) SurveyCategory::max('position') + 1;
        $category ? $category->update($data) : SurveyCategory::create($data);

        return redirect()->route('rodeo.surveys.categories')->with('status', 'Category saved');
    }

    public function deleteCategory(SurveyCategory $category): RedirectResponse
    {
        $category->delete();   // its questions become uncategorized

        return back()->with('status', 'Category deleted; its questions are now uncategorized');
    }
}
