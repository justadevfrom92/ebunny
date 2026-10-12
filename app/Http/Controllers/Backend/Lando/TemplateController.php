<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Page templates: the layouts pages are built on. */
class TemplateController extends Controller
{
    public function index(): View
    {
        return view('backend.lando.templates.index', ['templates' => Template::withCount('pages')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('backend.lando.templates.form', ['template' => new Template]);
    }

    public function store(Request $request): RedirectResponse
    {
        Template::create($this->validated($request));

        return redirect()->route('lando.templates.index')->with('status', 'Template added');
    }

    public function edit(Template $template): View
    {
        return view('backend.lando.templates.form', ['template' => $template->loadCount('pages')->load(['pages' => fn ($q) => $q->orderBy('path')])]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return redirect()->route('lando.templates.index')->with('status', 'Template saved');
    }

    private function validated(Request $request, ?Template $template = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('templates')->ignore($template)],
            'description' => ['required', 'string', 'max:300'],
            'file' => ['nullable', 'string', 'max:200', 'regex:#^[a-z0-9][a-z0-9/_.-]*\.html$#', 'not_regex:#\.\.#'],
        ], ['name.regex' => 'Use lowercase letters, numbers, dashes and underscores.', 'file.regex' => 'Enter a path to an .html file under public/, e.g. shared/layouts/article.html.']);
    }
}
