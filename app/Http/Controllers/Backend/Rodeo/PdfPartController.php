<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PdfPart;
use App\Models\PdfTemplate;
use App\Support\PdfTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Rodeo → PDF Headers & Footers: the band across the top and the small print at the bottom of PDFs. Templates pick one of each, or use the default. */
class PdfPartController extends Controller
{
    public function index(): View
    {
        return view('backend.rodeo.pdfs.parts', ['parts' => PdfPart::withCount('templates')->orderByDesc('is_default')->orderBy('name')->get()->groupBy('kind'),
            'usingDefault' => ['header' => PdfTemplate::whereNull('pdf_header_id')->count(), 'footer' => PdfTemplate::whereNull('pdf_footer_id')->count()]]);
    }

    public function form(Request $request, ?PdfPart $part = null): View
    {
        $part ??= new PdfPart(['kind' => $request->query('kind') === 'header' ? 'header' : 'footer', 'color' => 'navy']);
        // Preview: a short sample letter with this header or footer
        $sample = Customer::with('plan')->orderBy('id')->first();
        $doc = ['title' => 'Sample Letter', 'body' => "Hi {{first_name}},\n\nThis is how the ".strtolower(PdfPart::KINDS[$part->kind]).' looks on a PDF.', 'show_address' => true,
            'header' => $part->kind === 'header' ? $part->headerData() : PdfPart::defaultFor('header')?->headerData(),
            'footer' => $part->kind === 'footer' ? $part->body : PdfPart::defaultFor('footer')?->body];
        $pdf = PdfTemplateRenderer::build($doc, $sample);
        $col = $part->kind === 'header' ? 'pdf_header_id' : 'pdf_footer_id';

        return view('backend.rodeo.pdfs.part-form', ['part' => $part, 'pages' => $pdf->svgs(), 'pdf64' => base64_encode($pdf->output('Sample')),
            'templates' => $part->exists ? PdfTemplate::where($col, $part->id)->when($part->is_default, fn ($q) => $q->orWhereNull($col))->orderBy('name')->get() : collect()]);
    }

    public function save(Request $request, ?PdfPart $part = null): RedirectResponse
    {
        $kind = $part?->kind ?? $request->input('kind');
        $data = $request->validate([
            'kind' => [$part ? 'nullable' : 'required', Rule::in(array_keys(PdfPart::KINDS))],
            'name' => ['required', 'string', 'max:100', Rule::unique('pdf_parts')->where('kind', $kind)->ignore($part)],
            'title' => ['nullable', 'string', 'max:120'], 'subtitle' => ['nullable', 'string', 'max:160'],
            'color' => ['nullable', Rule::in(array_keys(PdfPart::COLORS))], 'body' => ['nullable', 'string', 'max:600'],
            'is_default' => ['nullable', 'boolean'],
        ]);
        $part = DB::transaction(function () use ($data, $part, $kind) {
            $fields = ['name' => $data['name'], 'title' => $data['title'] ?? null, 'subtitle' => $data['subtitle'] ?? null, 'color' => $data['color'] ?? 'navy', 'body' => $data['body'] ?? null];
            $part = $part ? tap($part)->update($fields) : PdfPart::create($fields + ['kind' => $kind]);
            if (! empty($data['is_default'])) {
                PdfPart::where('kind', $kind)->where('id', '!=', $part->id)->update(['is_default' => false]);
                $part->update(['is_default' => true]);
            }

            return $part;
        });

        return redirect()->route('rodeo.pdf-parts.edit', $part)->with('status', 'PDF '.strtolower(PdfPart::KINDS[$part->kind]).' saved');
    }

    /** Templates using it go back to the default. The default itself can't be deleted. */
    public function destroy(PdfPart $part): RedirectResponse
    {
        abort_if($part->is_default, 422, 'Pick another default first.');
        $part->templates()->update([$part->kind === 'header' ? 'pdf_header_id' : 'pdf_footer_id' => null]);
        $part->delete();

        return redirect()->route('rodeo.pdf-parts')->with('status', $part->name.' deleted. Its templates use the default '.strtolower(PdfPart::KINDS[$part->kind]).' now.');
    }
}
