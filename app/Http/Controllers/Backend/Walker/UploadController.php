<?php

namespace App\Http\Controllers\Backend\Walker;

use App\Http\Controllers\Controller;
use App\Models\ReportUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Walker → Files: documents and report files by folder (ETL, legal documents, customer templates, finance, vendors…). */
class UploadController extends Controller
{
    public function index(Request $request, ?string $folder = null): View
    {
        abort_if($folder && ! config('walker.folders.'.$folder), 404);
        $category = $folder ? config('walker.folders.'.$folder) : (in_array($request->query('category'), config('walker.upload_categories'), true) ? $request->query('category') : null);

        return view('backend.walker.uploads', ['category' => $category,
            'uploads' => ReportUpload::with('user')->when($category, fn ($q, $c) => $q->where('category', $c))->latest()->paginate(25)->withQueryString(),
            'counts' => ReportUpload::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category')]);
    }

    /** Upload a Report: its own page with a simple form. */
    public function create(): View
    {
        return view('backend.walker.upload-form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(config('walker.upload_categories'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv,pdf,txt,zip'],
        ]);
        $file = $request->file('file');
        $upload = ReportUpload::create(['title' => $data['title'], 'category' => $data['category'], 'description' => $data['description'] ?? null,
            'path' => $file->store('report-uploads', 'local'), 'original_name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'user_id' => $request->user()->id]);

        return redirect()->route('walker.uploads.show', $upload)->with('status', 'Uploaded '.$data['title']);
    }

    /**
     * One file's own page: what it is, who added it, a preview (table for CSV, text, PDF or image),
     * download, replace with a new version, edit its details, and the other files in its folder.
     */
    public function show(ReportUpload $upload): View
    {
        $upload->load('user');
        $ext = strtolower(pathinfo($upload->original_name, PATHINFO_EXTENSION));
        $exists = Storage::disk('local')->exists($upload->path);
        $preview = null;
        if ($exists && in_array($ext, ['csv', 'txt'], true) && $upload->size <= 2_000_000) {
            $text = Storage::disk('local')->get($upload->path);
            $preview = $ext === 'csv'
                ? ['type' => 'table', 'rows' => collect(preg_split('/\r?\n/', trim($text)))->take(51)->map(fn ($l) => str_getcsv($l))->all()]
                : ['type' => 'text', 'text' => mb_substr($text, 0, 20000)];
        } elseif ($exists && in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif'], true)) {
            $preview = ['type' => $ext === 'pdf' ? 'pdf' : 'image'];
        }
        $folder = array_search($upload->category, config('walker.folders'), true);

        return view('backend.walker.upload', ['u' => $upload, 'ext' => $ext, 'exists' => $exists, 'preview' => $preview, 'folder' => $folder,
            'siblings' => ReportUpload::where('category', $upload->category)->where('id', '!=', $upload->id)->latest()->limit(10)->get()]);
    }

    /** PDFs and images shown inside the file page. */
    public function view(ReportUpload $upload): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($upload->path), 404);

        return Storage::disk('local')->response($upload->path, $upload->original_name, ['Content-Disposition' => 'inline; filename="'.addslashes($upload->original_name).'"']);
    }

    /** Title, folder and description; optionally a new version of the file (the old one is replaced). */
    public function update(Request $request, ReportUpload $upload): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(config('walker.upload_categories'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:xlsx,xls,csv,pdf,txt,zip'],
        ]);
        $changes = ['title' => $data['title'], 'category' => $data['category'], 'description' => $data['description'] ?? null];
        if ($file = $request->file('file')) {
            Storage::disk('local')->delete($upload->path);
            $changes += ['path' => $file->store('report-uploads', 'local'), 'original_name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'user_id' => $request->user()->id];
        }
        $upload->update($changes);

        return back()->with('status', $request->hasFile('file') ? 'New version uploaded' : 'Saved');
    }

    public function download(ReportUpload $upload): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($upload->path), 404);

        return Storage::disk('local')->download($upload->path, $upload->original_name);
    }

    /** Needs the "delete" right. */
    public function destroy(ReportUpload $upload): RedirectResponse
    {
        Gate::authorize('delete');
        Storage::disk('local')->delete($upload->path);
        $upload->delete();

        $folder = array_search($upload->category, config('walker.folders'), true);

        return redirect()->route($folder ? 'walker.folder' : 'walker.uploads.index', $folder ? ['folder' => $folder] : [])->with('status', 'Deleted '.$upload->title);
    }
}
