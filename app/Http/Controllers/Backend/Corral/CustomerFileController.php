<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\ContactLog;
use App\Models\CustomerFile;
use App\Support\CustomerDocument;
use App\Support\CustomerFilePdf;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** One file on an account: its PDF (view, download, regenerate) in English or Spanish, print it, or email it to the customer. */
class CustomerFileController extends Controller
{
    private function lang(Request $request, CustomerFile $file): string
    {
        $lang = (string) $request->query('lang', CustomerDocument::preferred($file->customer->language));

        return array_key_exists($lang, CustomerDocument::LANGUAGES) ? $lang : 'en';
    }

    public const KINDS = ['efl' => 'Electricity Facts Label', 'welcome' => 'Welcome Packet', 'invoice' => 'Invoice', 'yrac' => 'Your Rights as a Customer', 'tos' => 'Terms of Service'];

    /** Every customer PDF the billing system made, newest first, by type. Each row opens the file's page. */
    public function index(Request $request): View
    {
        $kind = array_key_exists((string) $request->query('kind'), self::KINDS) ? $request->query('kind') : null;
        $q = CustomerFile::with('customer')->whereNull('path')->when($kind, fn ($q) => $q->where('kind', $kind));

        return view('backend.corral.files', ['files' => $q->latest('created_at')->latest('id')->paginate(10)->withQueryString(), 'kind' => $kind,
            'counts' => CustomerFile::whereNull('path')->selectRaw('kind, count(*) as n')->groupBy('kind')->pluck('n', 'kind'),
            'regenerated' => CustomerFile::whereNull('path')->where('regenerated', '>', 0)->count()]);
    }

    public function show(Request $request, CustomerFile $file): View
    {
        $file->load('customer');
        $lang = $this->lang($request, $file);

        return view('backend.corral.customers.file', ['file' => $file, 'c' => $file->customer, 'lang' => $lang,
            'doc' => $file->path ? null : CustomerDocument::render($file, $lang),
            // The PDF goes in the page as base64; the page turns it into a viewer and a download in the browser
            'pdf64' => $file->path ? null : base64_encode(CustomerFilePdf::make($file, $lang)),
            'page' => $file->path ? null : CustomerFilePdf::build($file, $lang)->svg(),
            'sent' => ContactLog::where('customer_id', $file->customer_id)->where('channel', 'Email')->where('template', 'like', 'Customer File: '.$file->name.'%')->latest()->limit(10)->get()]);
    }

    /** The PDF itself: inline to view, or ?download=1 to save it. */
    public function pdf(Request $request, CustomerFile $file): Response
    {
        abort_if($file->path, 404);
        $file->load('customer');
        $lang = $this->lang($request, $file);

        return response(CustomerFilePdf::make($file, $lang), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.self::pdfName($file, $lang).'"',
            'Cache-Control' => 'private, no-store']);
    }

    /** Build the PDF again from the account as it is now (address, plan, latest bill). */
    public function regenerate(Request $request, CustomerFile $file): RedirectResponse
    {
        abort_if($file->path, 404);
        $file->load('customer');
        $file->forceFill(['generated_at' => now(), 'regenerated' => $file->regenerated + 1])->save();
        $file->forceFill(['size' => strlen(CustomerFilePdf::make($file, $this->lang($request, $file)))])->save();
        History::record(['customer_id' => $file->customer_id, 'model' => 'ItemFile_model', 'group' => 'Billing & Files', 'record_id' => $file->id, 'action' => 'regenerated',
            'summary' => $file->name.' PDF regenerated']);

        return back()->with('status', $file->name.' regenerated.');
    }

    public static function pdfName(CustomerFile $file, string $lang): string
    {
        $base = preg_replace('/\.pdf$/i', '', $file->name);

        return $base.($lang === 'es' ? '-es' : '').'.pdf';
    }

    public function print(Request $request, CustomerFile $file): View
    {
        $file->load('customer');
        abort_if($file->path, 404, 'Uploaded files print from your browser after you download them.');

        return view('backend.corral.customers.file-print', ['file' => $file, 'c' => $file->customer, 'doc' => CustomerDocument::render($file, $this->lang($request, $file))]);
    }

    public function send(Request $request, CustomerFile $file): RedirectResponse
    {
        $c = $file->customer;
        $data = $request->validate(['lang' => ['required', Rule::in(array_keys(CustomerDocument::LANGUAGES))], 'email' => ['required', 'email', 'max:200'], 'note' => ['nullable', 'string', 'max:1000']]);
        $doc = $file->path ? null : CustomerDocument::render($file, $data['lang']);
        $subject = ($doc['title'] ?? $file->name).' — '.config('brand.name');
        $html = view('backend.corral.customers.file-email', ['doc' => $doc, 'file' => $file, 'c' => $c, 'note' => $data['note'] ?? null, 'lang' => $data['lang']])->render();

        Mail::html($html, function ($m) use ($data, $subject, $file) {
            $m->to($data['email'])->subject($subject);
            if ($file->path && Storage::disk('local')->exists($file->path)) {
                $m->attachData(Storage::disk('local')->get($file->path), $file->name);
            } elseif (! $file->path) {
                $m->attachData(CustomerFilePdf::make($file, $data['lang']), self::pdfName($file, $data['lang']), ['mime' => 'application/pdf']);
            }
        });
        $language = CustomerDocument::LANGUAGES[$data['lang']];
        ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => 'Customer File: '.$file->name.' ('.$language.')', 'body' => $subject,
            'status' => 'sent', 'direction' => 'out', 'sent_at' => now(), 'user_id' => $request->user()->id]);
        $c->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Contact', 'action' => 'Contact - Send Email', 'priority' => 'Low',
            'body' => 'Emailed '.$file->name.' in '.$language.' to '.$data['email'].'.']);
        History::record(['customer_id' => $c->id, 'model' => 'ItemFile_model', 'group' => 'Billing & Files', 'record_id' => $file->id, 'action' => 'emailed',
            'summary' => $file->name.' emailed in '.$language.' to '.$data['email']]);

        return back()->with('status', $file->name.' emailed to '.$data['email'].' in '.$language.'.');
    }
}
