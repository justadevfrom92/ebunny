@extends('backend.layouts.app')

{{-- Rodeo → PDFs → PDFs Made: every PDF made from a template, newest first. Each row opens it. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'PDFs Made', 'sub' => 'Every PDF made from a template for an account, with the wording it was made from.'])

    <select class="pill-select" aria-label="Template" style="margin-bottom:18px" onchange="location.href=this.value">
        <option value="{{ route('rodeo.pdfs.generated') }}">All templates</option>
        @foreach ($templates as $t)<option value="{{ route('rodeo.pdfs.generated', ['template' => $t->id]) }}" @selected($only === $t->id)>{{ $t->name }}</option>@endforeach
    </select>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Made</th><th>Template</th><th>Account</th><th>File</th><th>By</th><th class="num">Pages</th></tr></thead>
        <tbody>@forelse ($rows as $g)
            @php $u = route('rodeo.pdfs.generated.show', $g); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $g->id }}</b></a></td><td class="nowrap">{{ $g->created_at->format('n/j/Y g:i A') }}</td>
                <td><b>{{ $g->template?->name }}</b><div class="help">{{ $g->template?->category }}</div></td>
                <td>@if ($g->customer)@include('backend.partials.account-link', ['c' => $g->customer])<div class="help">{{ $g->customer->name }}</div>@else — @endif</td>
                <td class="mono wrap">{{ $g->file_name }}<div class="help">{{ number_format($g->size / 1024, 1) }} KB</div></td>
                <td>{{ $g->user?->name ?? 'System' }}</td><td class="num">{{ $g->pages }}</td></tr>
        @empty <tr><td colspan="7" class="empty">No PDFs made yet.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $rows])</div>
@endsection
