<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Survey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A customer survey on the website. Campaign emails link here with a signed
 * ?c=<account> so the answer is tied to that customer; a signed-in My Account
 * customer is used otherwise, and anyone else answers anonymously.
 */
class SurveyController extends Controller
{
    public function show(Request $request, Survey $survey): View
    {
        $survey->load('questions.category');

        return view('frontend.survey', ['survey' => $survey, 'customer' => $this->customer($request),
            'groups' => $survey->questions->groupBy(fn ($q) => (string) ($q->survey_category_id ?? 0)),
            'query' => $request->hasValidSignature() ? $request->query() : []]);
    }

    public function store(Request $request, Survey $survey): RedirectResponse
    {
        abort_unless($survey->isOpen(), 410);
        $rules = [];
        $names = [];
        foreach ($survey->questions as $q) {
            $key = 'a.'.$q->id;
            $need = $q->required ? 'required' : 'nullable';
            $names[$key] = '“'.$q->question.'”';
            $rules[$key] = match ($q->type) {
                'rating' => [$need, 'integer', 'between:0,10'],
                'multi' => [$need, 'array'],
                'text' => [$need, 'string', 'max:2000'],
                default => [$need, Rule::in($q->choices())],
            };
            if ($q->type === 'multi') {
                $rules[$key.'.*'] = [Rule::in($q->choices())];
            }
        }
        $answers = $request->validate($rules, ['required' => 'Please answer :attribute'], $names)['a'] ?? [];
        $customer = $this->customer($request);
        $survey->responses()->create([
            'customer_id' => $customer?->id,
            'answers' => (object) collect($answers)->filter(fn ($a) => $a !== null && $a !== '' && $a !== [])
                ->mapWithKeys(fn ($a, $id) => [(string) $id => is_array($a) ? array_values($a) : (string) $a])->all(),
            'source' => $request->hasValidSignature() && $request->query('c') ? 'email' : (auth('customer')->check() ? 'my-account' : 'website'),
            'created_at' => now(),
        ]);

        return redirect()->route('survey.show', $survey)->with('thanks', true);
    }

    /** The customer this answer belongs to: from the signed email link, else the My Account login. */
    private function customer(Request $request): ?Customer
    {
        if ($request->hasValidSignature() && $request->query('c')) {
            return Customer::where('account', $request->query('c'))->first();
        }

        return auth('customer')->user();
    }
}
