<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PdfGeneration;
use App\Models\PdfTemplate;
use App\Support\PdfTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rodeo → PDFs: PDF templates (letters and notices) written like email templates, with fill-ins, a live
 * preview for any account, and Make PDF to create one for an account. Every PDF made is kept with the
 * wording it was made from.
 */
class PdfTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $only = in_array($request->query('category'), PdfTemplate::CATEGORIES, true) ? $request->query('category') : null;

        return view('backend.rodeo.pdfs.index', ['only' => $only,
            'templates' => PdfTemplate::withCount('generations')->withMax('generations', 'created_at')->when($only, fn ($q) => $q->where('category', $only))->orderBy('name')->paginate(10)->withQueryString(),
            'counts' => PdfTemplate::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')]);
    }

    public function form(Request $request, ?PdfTemplate $template = null): View
    {
        $template ??= new PdfTemplate(['title' => 'Notice', 'body' => "Hi {{first_name}},\n\n", 'show_address' => true, 'status' => 'Draft', 'category' => 'Billing']);
        $sample = $request->filled('account') ? Customer::with('plan')->where('account', $request->query('account'))->first() : null;
        $sample ??= Customer::with('plan')->orderBy('id')->first();
        $pdf = PdfTemplateRenderer::build($template->only(['title', 'body', 'footer', 'show_address']), $sample);

        return view('backend.rodeo.pdfs.form', ['template' => $template, 'sample' => $sample, 'pages' => $pdf->svgs(),
            'pdf64' => base64_encode($pdf->output($template->title)), 'fileName' => $template->fileName($sample),
            'recent' => $template->exists ? $template->generations()->with(['customer', 'user'])->latest('created_at')->latest('id')->limit(10)->get() : collect()]);
    }

    public function save(Request $request, ?PdfTemplate $template = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('pdf_templates')->ignore($template)],
            'category' => ['nullable', Rule::in(PdfTemplate::CATEGORIES)],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:50000'],
            'footer' => ['nullable', 'string', 'max:600'],
            'show_address' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['Active', 'Draft'])],
        ]);
        $data['show_address'] = $request->boolean('show_address');
        $template = $template ? tap($template)->update($data) : PdfTemplate::create($data);

        return redirect()->route('rodeo.pdfs.edit', $template)->with('status', 'PDF template saved');
    }

    public function destroy(PdfTemplate $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('rodeo.pdfs')->with('status', $template->name.' deleted');
    }

    /** Make the PDF for an account and keep it. */
    public function generate(Request $request, PdfTemplate $template): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'string', Rule::exists('customers', 'account')]], ['account.exists' => 'No account has that number.']);
        $c = Customer::with('plan')->where('account', $data['account'])->firstOrFail();
        $doc = $template->only(['title', 'body', 'footer', 'show_address']);
        $pdf = PdfTemplateRenderer::build($doc, $c);
        $g = PdfGeneration::create($doc + ['pdf_template_id' => $template->id, 'customer_id' => $c->id, 'user_id' => $request->user()->id,
            'file_name' => $template->fileName($c), 'pages' => $pdf->pageCount(), 'size' => strlen($pdf->output($template->title)), 'created_at' => now()]);
        $c->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Contact', 'action' => 'Contact - Letter', 'priority' => 'Low',
            'body' => 'Made the PDF "'.$template->name.'" ('.$g->file_name.').']);

        return redirect()->route('rodeo.pdfs.generated.show', $g)->with('status', 'PDF made for account '.$c->account);
    }

    public function generated(Request $request): View
    {
        $q = PdfGeneration::with(['template', 'customer', 'user'])->latest('created_at')->latest('id');
        if ($request->filled('template')) {
            $q->where('pdf_template_id', $request->integer('template'));
        }

        return view('backend.rodeo.pdfs.generated', ['rows' => $q->paginate(10)->withQueryString(), 'templates' => PdfTemplate::orderBy('name')->get(['id', 'name']), 'only' => $request->integer('template') ?: null]);
    }

    public function generatedShow(PdfGeneration $generation): View
    {
        $generation->load(['template', 'customer.plan', 'user']);
        $pdf = PdfTemplateRenderer::build($generation->only(['title', 'body', 'footer', 'show_address']), $generation->customer);

        return view('backend.rodeo.pdfs.generated-show', ['g' => $generation, 'pages' => $pdf->svgs(), 'pdf64' => base64_encode($pdf->output($generation->title))]);
    }

    /** The PDF file itself (inline, or ?download=1). */
    public function generatedPdf(Request $request, PdfGeneration $generation): Response
    {
        $pdf = PdfTemplateRenderer::build($generation->only(['title', 'body', 'footer', 'show_address']), $generation->customer);

        return response($pdf->output($generation->title), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$generation->file_name.'"', 'Cache-Control' => 'private, no-store']);
    }
}
