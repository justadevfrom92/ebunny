<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\EmailPart;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Rodeo → Headers & Footers: the top and bottom of every email. Templates pick one of each, or use the default. */
class EmailPartController extends Controller
{
    public function index(): View
    {
        $parts = EmailPart::withCount('templates')->orderByDesc('is_default')->orderBy('name')->get()->groupBy('kind');

        return view('backend.rodeo.parts', ['parts' => $parts, 'usingDefault' => [
            'header' => EmailTemplate::whereNull('email_header_id')->count(), 'footer' => EmailTemplate::whereNull('email_footer_id')->count()]]);
    }

    public function form(Request $request, ?EmailPart $part = null): View
    {
        $part ??= new EmailPart(['kind' => in_array($request->query('kind'), ['header', 'footer'], true) ? $request->query('kind') : 'footer', 'html' => '']);

        return view('backend.rodeo.part-form', ['part' => $part,
            'templates' => $part->exists ? EmailTemplate::where($part->kind === 'header' ? 'email_header_id' : 'email_footer_id', $part->id)
                ->when($part->is_default, fn ($q) => $q->orWhereNull($part->kind === 'header' ? 'email_header_id' : 'email_footer_id'))->orderBy('name')->get() : collect()]);
    }

    public function save(Request $request, ?EmailPart $part = null): RedirectResponse
    {
        $kind = $part?->kind ?? $request->input('kind');
        $data = $request->validate([
            'kind' => [$part ? 'nullable' : 'required', Rule::in(array_keys(EmailPart::KINDS))],
            'name' => ['required', 'string', 'max:100', Rule::unique('email_parts')->where('kind', $kind)->ignore($part)],
            'html' => ['nullable', 'string', 'max:50000'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $part = DB::transaction(function () use ($data, $part, $kind) {
            $fields = ['name' => $data['name'], 'html' => $data['html'] ?? ''];
            $part = $part ? tap($part)->update($fields) : EmailPart::create($fields + ['kind' => $kind]);
            if (! empty($data['is_default'])) {
                EmailPart::where('kind', $kind)->where('id', '!=', $part->id)->update(['is_default' => false]);
                $part->update(['is_default' => true]);
            }

            return $part;
        });

        return redirect()->route('rodeo.parts.edit', $part)->with('status', EmailPart::KINDS[$part->kind].' saved');
    }

    /** Templates using it go back to the default. The default itself can't be deleted. */
    public function destroy(EmailPart $part): RedirectResponse
    {
        abort_if($part->is_default, 422, 'Pick another default first.');
        $part->templates()->update([$part->kind === 'header' ? 'email_header_id' : 'email_footer_id' => null]);
        $part->delete();

        return redirect()->route('rodeo.parts')->with('status', $part->name.' deleted. Its templates use the default '.strtolower(EmailPart::KINDS[$part->kind]).' now.');
    }
}
