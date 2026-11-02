@extends('backend.layouts.app')

{{-- Rodeo → PDF Headers & Footers: like email Headers & Footers, for PDFs. Each row opens the header or footer. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'PDF Headers & Footers', 'sub' => 'The band across the top of a PDF and the small print at the bottom of every page. Each PDF template picks a header and a footer; templates that don\'t pick one use the default.',
        'actions' => '<a class="btn ghost" href="'.route('rodeo.pdf-parts.create', ['kind' => 'header']).'">New Header</a><a class="btn cyan" href="'.route('rodeo.pdf-parts.create', ['kind' => 'footer']).'">New Footer</a>'])

    @foreach (\App\Models\PdfPart::KINDS as $kind => $label)
        <div class="panel"><div class="panel-head"><h2>{{ $label }}s</h2></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Name</th><th>{{ $kind === 'header' ? 'Looks Like' : 'Small Print' }}</th><th>Default</th><th class="num">Templates Using It</th><th>Changed</th></tr></thead>
            <tbody>@forelse ($parts[$kind] ?? [] as $p)
                @php $u = route('rodeo.pdf-parts.edit', $p); @endphp
                <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $p->id }}</b></a></td><td><b>{{ $p->name }}</b></td>
                    <td class="wrap help">@if ($kind === 'header'){{ $p->title ?: config('brand.name') }} · {{ \App\Models\PdfPart::COLORS[$p->color] ?? $p->color }}@else{{ \Illuminate\Support\Str::limit($p->body ?: '(empty)', 90) }}@endif</td>
                    <td>@if ($p->is_default)@include('backend.partials.pill', ['text' => 'Default', 'tone' => 'ok'])@endif</td>
                    <td class="num">{{ $p->templates_count + ($p->is_default ? $usingDefault[$kind] : 0) }}</td>
                    <td class="nowrap">{{ $p->updated_at->format('n/j/Y') }}</td></tr>
            @empty <tr><td colspan="6" class="empty">No {{ strtolower($label) }}s yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    @endforeach
@endsection
