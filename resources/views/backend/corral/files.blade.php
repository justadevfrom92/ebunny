@extends('backend.layouts.app')

{{-- Every customer PDF (EFL, welcome packet, invoice, YRAC, TOS). Each row opens the file: view, download, regenerate or email it. --}}
@section('content')
    @php $kinds = \App\Http\Controllers\Backend\Corral\CustomerFileController::KINDS; @endphp
    @include('backend.partials.page-head', ['title' => 'Customer PDFs', 'sub' => 'The PDFs on customer accounts: Electricity Facts Labels, welcome packets, invoices, Your Rights as a Customer and Terms of Service. Open one to view, download, regenerate or email it in English or Spanish.'])

    <div class="grid-4 stat-row" style="margin-bottom:20px">
        <div class="stat"><span>All PDFs</span><strong>{{ number_format($counts->sum()) }}</strong><small>on customer accounts</small></div>
        <div class="stat"><span>EFLs</span><strong>{{ number_format($counts['efl'] ?? 0) }}</strong><small>facts labels</small></div>
        <div class="stat"><span>Invoices</span><strong>{{ number_format($counts['invoice'] ?? 0) }}</strong><small>bill statements</small></div>
        <div class="stat"><span>Regenerated</span><strong>{{ number_format($regenerated) }}</strong><small>built again at least once</small></div>
    </div>

    <select class="pill-select" aria-label="Type" style="margin-bottom:18px" onchange="location.href=this.value">
        <option value="{{ route('corral.files.index') }}">All types · {{ number_format($counts->sum()) }}</option>
        @foreach ($kinds as $k => $label)<option value="{{ route('corral.files.index', ['kind' => $k]) }}" @selected($kind === $k)>{{ $label }} · {{ number_format($counts[$k] ?? 0) }}</option>@endforeach
    </select>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>File</th><th>Type</th><th>Account</th><th>Created</th><th>Last Generated</th></tr></thead>
        <tbody>@forelse ($files as $f)
            @php $url = route('corral.files.show', $f); @endphp
            <tr class="click" data-href="{{ $url }}"><td class="nowrap"><a href="{{ $url }}"><b>#{{ $f->id }}</b></a></td>
                <td class="mono wrap">{{ $f->name }}</td><td>{{ $kinds[$f->kind] ?? ucfirst($f->kind) }}<div class="help">PDF</div></td>
                <td>@include('backend.partials.account-link', ['c' => $f->customer])<div class="help">{{ $f->customer->name }}</div></td>
                <td class="nowrap">{{ $f->created_at->format('n/j/Y') }}</td>
                <td class="nowrap">{{ ($f->generated_at ?? $f->created_at)->format('n/j/Y g:i A') }}@if ($f->regenerated)<div class="help">regenerated {{ $f->regenerated }}×</div>@endif</td></tr>
        @empty <tr><td colspan="6" class="empty">No PDFs{{ $kind ? ' of this type' : '' }}.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $files])</div>
@endsection
