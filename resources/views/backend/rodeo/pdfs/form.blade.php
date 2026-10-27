@extends('backend.layouts.app')

@section('crumb', $template->exists ? $template->name : 'New PDF Template')

{{-- One PDF template: its wording (with fill-ins), the PDF as it comes out for an account, and Make PDF. --}}
@section('content')
    @php $fields = \App\Support\PdfTemplateRenderer::FIELDS; @endphp
    @include('backend.partials.page-head', ['title' => $template->exists ? $template->name : 'New PDF Template',
        'sub' => $template->exists ? 'Template #'.$template->id.' · '.($template->category ?? 'No category').' · updated '.$template->updated_at->format('n/j/Y g:i A') : 'Write the letter, save it, then preview and make PDFs for accounts.',
        'actions' => '<a class="btn ghost btn-back" href="'.route('rodeo.pdfs').'">Back to PDF Templates</a>'
            .'<a class="btn cyan" href="#" target="_blank" rel="noopener" data-pdf-open>View PDF</a><a class="btn ghost" href="#" data-pdf-download>Download PDF</a>'])

    <div style="display:flex;flex-direction:column;gap:20px">
        <form method="post" action="{{ $template->exists ? route('rodeo.pdfs.update', $template) : route('rodeo.pdfs.store') }}" class="panel">@csrf @if ($template->exists) @method('put') @endif
            <div class="panel-head"><h2>Template</h2></div>
            <div class="panel-body form-grid" style="grid-template-columns:1fr">
                <label for="name">Name</label><input id="name" name="name" required maxlength="100" value="{{ old('name', $template->name) }}">
                <label for="category">Category</label><select id="category" name="category"><option value="">None</option>@foreach (\App\Models\PdfTemplate::CATEGORIES as $c)<option @selected(old('category', $template->category) === $c)>{{ $c }}</option>@endforeach</select>
                <label for="status">Status</label><select id="status" name="status">@foreach (['Active', 'Draft'] as $s)<option @selected(old('status', $template->status) === $s)>{{ $s }}</option>@endforeach</select>
                <label for="title">Title on the PDF</label><input id="title" name="title" required maxlength="120" value="{{ old('title', $template->title) }}">
                <label for="body">Body</label>
                <div><textarea id="body" name="body" required spellcheck="true" style="min-height:320px;font-family:var(--font-mono, monospace);font-size:.88rem">{{ old('body', $template->body) }}</textarea>
                    <div class="help">One block per line: <span class="mono"># Heading</span>, <span class="mono">## Smaller heading</span>, <span class="mono">- bullet</span>, <span class="mono">| Label | Value |</span> for a shaded row, <span class="mono">**bold line**</span>, <span class="mono">---</span> for a line. A blank line starts a new paragraph.</div></div>
                <label for="footer">Footer (small print on every page)</label><textarea id="footer" name="footer" maxlength="600" style="min-height:70px">{{ old('footer', $template->footer) }}</textarea>
                <label for="show_address">Account block</label><label class="check"><input type="hidden" name="show_address" value="0"><input id="show_address" type="checkbox" name="show_address" value="1" @checked(old('show_address', $template->show_address))> Show the customer's name, address and account number on the first page</label>
            </div>
            <div class="panel-body"><button class="btn cyan">Save Template</button>
                @if ($template->exists)<span class="help" style="margin-left:10px">Save to update the preview.</span>@endif</div>
        </form>
        @if ($template->exists)
            <form method="post" action="{{ route('rodeo.pdfs.destroy', $template) }}" data-confirm="Delete {{ $template->name }}?|The template and the {{ $template->generations()->count() }} PDFs made from it are deleted.|Delete" style="margin-top:-8px">@csrf @method('delete')<button class="btn sm ghost">Delete Template</button></form>
        @endif

        <div class="panel"><div class="panel-head"><h2>Fill-Ins</h2><span class="muted">type these anywhere in the title, body or footer</span></div><div class="table-wrap"><table data-page="0">
            <thead><tr><th>Fill-In</th><th>What It Becomes</th><th>For {{ $sample?->first_name ?? 'the sample' }}</th></tr></thead>
            <tbody>@foreach ($fields as $k => $label)<tr><td class="mono">&#123;&#123;{{ $k }}&#125;&#125;</td><td>{{ $label }}</td>@php $tag = '{'.'{'.$k.'}'.'}'; @endphp<td>{{ \App\Support\PdfTemplateRenderer::fill($tag, $sample) }}</td></tr>@endforeach</tbody>
        </table></div></div>

        <div class="panel" id="preview"><div class="panel-head"><h2>Preview</h2><span class="muted">{{ count($pages) }} {{ \Illuminate\Support\Str::plural('page', count($pages)) }} · for {{ $sample?->name ?? 'a sample account' }} ({{ $sample?->account }})</span></div>
            <form method="get" class="panel-body form-row" action="{{ url()->current() }}#preview"><input name="account" aria-label="Preview for account number" placeholder="Preview for another account number" value="{{ request('account') }}" inputmode="numeric"><button class="btn sm">Preview</button></form>
            @include('backend.rodeo.pdfs._pages', ['pages' => $pages, 'pdf64' => $pdf64, 'name' => $fileName])
        </div>

        @if ($template->exists)
            <form method="post" action="{{ route('rodeo.pdfs.generate', $template) }}" class="panel">@csrf
                <div class="panel-head"><h2>Make a PDF for an Account</h2></div>
                <div class="panel-body">
                    <div class="form-row"><input name="account" required aria-label="Account number" placeholder="Account number" inputmode="numeric" value="{{ old('account', $sample?->account) }}"><button class="btn cyan">Make PDF</button></div>
                    @error('account')<div class="help" style="color:var(--bad)">{{ $message }}</div>@enderror
                    <p class="help">The PDF is kept under PDFs Made, and a note is added to the account.</p>
                </div>
            </form>

            <div class="panel"><div class="panel-head"><h2>PDFs Made From This Template</h2><a class="btn sm ghost" href="{{ route('rodeo.pdfs.generated', ['template' => $template->id]) }}">All</a></div><div class="table-wrap"><table>
                <thead><tr><th>ID</th><th>Made</th><th>Account</th><th>By</th><th class="num">Pages</th></tr></thead>
                <tbody>@forelse ($recent as $g)
                    @php $u = route('rodeo.pdfs.generated.show', $g); @endphp
                    <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $g->id }}</b></a></td><td class="nowrap">{{ $g->created_at->format('n/j/Y g:i A') }}</td>
                        <td>@if ($g->customer)@include('backend.partials.account-link', ['c' => $g->customer])<div class="help">{{ $g->customer->name }}</div>@else — @endif</td>
                        <td>{{ $g->user?->name ?? 'System' }}</td><td class="num">{{ $g->pages }}</td></tr>
                @empty <tr><td colspan="5" class="empty">None made yet.</td></tr> @endforelse</tbody>
            </table></div></div>
        @endif
    </div>
@endsection
