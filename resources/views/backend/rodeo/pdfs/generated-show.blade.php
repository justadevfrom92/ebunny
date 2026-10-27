@extends('backend.layouts.app')

@section('crumb', 'PDF #'.$g->id)

{{-- One PDF made from a template: the pages, View / Download, and what it was made from. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $g->title, 'sub' => 'PDF #'.$g->id.' · made '.$g->created_at->format('n/j/Y g:i A').' by '.e($g->user?->name ?? 'System'),
        'actions' => '<a class="btn ghost btn-back" href="'.route('rodeo.pdfs.generated').'">Back to PDFs Made</a>'
            .'<a class="btn cyan" href="'.route('rodeo.pdfs.generated.pdf', $g).'" target="_blank" rel="noopener" data-pdf-open>View PDF</a>'
            .'<a class="btn ghost" href="'.route('rodeo.pdfs.generated.pdf', [$g, 'download' => 1]).'" data-pdf-download>Download PDF</a>'])

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="panel"><div class="panel-head"><h2>Details</h2></div><div class="panel-body"><dl class="kv">
            <dt>PDF</dt><dd class="mono">#{{ $g->id }} · {{ $g->file_name }}</dd>
            <dt>Template</dt><dd>@if ($g->template)<a href="{{ route('rodeo.pdfs.edit', $g->template) }}">#{{ $g->template->id }} {{ $g->template->name }}</a>@else — @endif</dd>
            <dt>Account</dt><dd>@if ($g->customer)@include('backend.partials.account-link', ['c' => $g->customer]) {{ $g->customer->name }}@else — @endif</dd>
            <dt>Pages</dt><dd>{{ $g->pages }} · {{ number_format($g->size / 1024, 1) }} KB</dd>
            <dt>Made By</dt><dd>{{ $g->user?->name ?? 'System' }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>The PDF</h2><span class="muted">{{ count($pages) }} {{ \Illuminate\Support\Str::plural('page', count($pages)) }}</span></div>
            @include('backend.rodeo.pdfs._pages', ['pages' => $pages, 'pdf64' => $pdf64, 'name' => $g->file_name])
        </div>
    </div>
@endsection
