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

/** Walker → Uploaded Reports: report files people share (from utilities, vendors, finance…). */
class UploadController extends Controller
{
    public function index(Request $request): View
    {
        $category = in_array($request->query('category'), config('walker.upload_categories'), true) ? $request->query('category') : null;

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
        ReportUpload::create(['title' => $data['title'], 'category' => $data['category'], 'description' => $data['description'] ?? null,
            'path' => $file->store('report-uploads', 'local'), 'original_name' => $file->getClientOriginalName(), 'size' => $file->getSize(), 'user_id' => $request->user()->id]);

        return redirect()->route('walker.uploads.index')->with('status', 'Uploaded '.$data['title']);
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

        return back()->with('status', 'Deleted '.$upload->title);
    }
}
