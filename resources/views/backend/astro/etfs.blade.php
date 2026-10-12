@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('astro.etfs.update') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'ETF by Term', 'sub' => 'Early termination fee charged when a customer leaves a fixed plan early. The website\'s plan builder shows these.', 'actions' => '<button class="btn cyan">Save ETFs</button>'])
        <div class="panel" style="max-width:520px"><div class="table-wrap"><table>
            <thead><tr><th class="num">Term (months)</th><th class="num">ETF ($)</th></tr></thead>
            <tbody>@foreach ($etfs as $e)
                <tr><td class="num">{{ $e->term }}</td><td class="num"><input type="number" step="1" min="0" name="etf[{{ $e->term }}]" value="{{ $e->amount }}" data-track aria-label="ETF for {{ $e->term }} months"></td></tr>
            @endforeach</tbody>
        </table></div></div>
    </form>
@endsection
