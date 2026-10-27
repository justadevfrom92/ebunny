@extends('backend.layouts.app')

{{-- Rodeo → PDFs → PDF Templates: letters and notices made into PDFs for an account. Each row opens the template. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'PDF Templates', 'sub' => 'Letters and notices made into PDFs for an account, written like email templates with fill-ins such as <span class="mono">&#123;&#123;first_name&#125;&#125;</span> and <span class="mono">&#123;&#123;balance&#125;&#125;</span>.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.pdfs.create').'">New PDF Template</a>'])

    <select class="pill-select" aria-label="Category" style="margin-bottom:18px" onchange="location.href=this.value">
        <option value="{{ route('rodeo.pdfs') }}">All categories · {{ $counts->sum() }}</option>
        @foreach (\App\Models\PdfTemplate::CATEGORIES as $c)<option value="{{ route('rodeo.pdfs', ['category' => $c]) }}" @selected($only === $c)>{{ $c }} · {{ $counts[$c] ?? 0 }}</option>@endforeach
    </select>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Template</th><th>Category</th><th>Status</th><th class="num">PDFs Made</th><th>Last Made</th><th>Updated</th></tr></thead>
        <tbody>@forelse ($templates as $t)
            @php $u = route('rodeo.pdfs.edit', $t); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $t->id }}</b></a></td>
                <td><b>{{ $t->name }}</b><div class="help">{{ $t->title }}</div></td><td>{{ $t->category ?? '—' }}</td>
                <td>@include('backend.partials.pill', ['text' => $t->status, 'tone' => $t->status === 'Active' ? 'ok' : 'warn'])</td>
                <td class="num">{{ number_format($t->generations_count) }}</td>
                <td class="nowrap">{{ $t->generations_max_created_at ? \Illuminate\Support\Carbon::parse($t->generations_max_created_at)->format('n/j/Y') : '—' }}</td>
                <td class="nowrap">{{ $t->updated_at->format('n/j/Y') }}</td></tr>
        @empty <tr><td colspan="7" class="empty">No PDF templates{{ $only ? ' in '.$only : '' }}.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $templates])</div>
@endsection
