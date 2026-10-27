<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\ContactLog;
use App\Models\SurveyResponse;
use App\Support\SurveyEmails;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/** Rodeo → Survey Emails: the automatic 90-day check-in and farewell survey emails, how they're doing, and every one sent. */
class SurveyEmailController extends Controller
{
    public function index(): View
    {
        $names = array_column(SurveyEmails::RULES, 1);
        $sent = ContactLog::whereIn('template', $names)->with('customer')->latest('sent_at')->latest('id')->paginate(10);
        // Which of these customers answered the survey the email was for
        $slugs = collect(SurveyEmails::RULES)->mapWithKeys(fn ($r) => [$r[1] => $r[2]]);
        $answered = SurveyResponse::whereIn('customer_id', $sent->pluck('customer_id'))->with('survey:id,slug')->get()
            ->map(fn ($r) => $r->customer_id.'|'.$r->survey?->slug)->flip();

        return view('backend.rodeo.survey-emails', ['stats' => collect(SurveyEmails::RULES)->map(fn ($r, $k) => SurveyEmails::stats($k)),
            'sent' => $sent, 'slugs' => $slugs, 'answered' => $answered]);
    }

    public function send(): RedirectResponse
    {
        Artisan::call('et:survey-emails', ['--user' => auth()->id()]);

        return back()->with('status', trim(Artisan::output()));
    }
}
