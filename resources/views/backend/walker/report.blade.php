@extends('backend.layouts.app')

@section('crumb', $def['title'])

@section('content')
    @include('backend.partials.page-head', ['title' => $def['title'], 'sub' => e($def['description']).' · Model <span class="mono">'.e($def['model']).'</span>'])

    <div class="search-layout">
        <aside class="search-side">
            <form method="post" action="{{ route('walker.reports.run', $def['key']) }}" class="panel">@csrf
                <div class="panel-head"><h2>Parameters</h2></div>
                <div class="panel-body">
                    @foreach ($filters as $name => $filter)
                        @php [$label, $type] = $filter; $options = $filter[2] ?? []; $value = old($name, $defaults[$name] ?? null); @endphp
                        @if ($type === 'multi')
                            <div class="field"><label>{{ $label }}</label><div class="check-stack">
                                @foreach ($options as $v => $l)<label class="check"><input type="checkbox" name="{{ $name }}[]" value="{{ $v }}" @checked(in_array($v, (array) $value))> {{ $l }}</label>@endforeach
                            </div></div>
                        @elseif ($type === 'select')
                            <div class="field"><label for="f-{{ $name }}">{{ $label }}</label><select id="f-{{ $name }}" name="{{ $name }}"><option value="">-- Any --</option>
                                @foreach ($options as $v => $l)<option value="{{ $v }}" @selected((string) $value === (string) $v)>{{ $l }}</option>@endforeach</select></div>
                        @else
                            <div class="field"><label for="f-{{ $name }}">{{ $label }}</label><input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type === 'date' ? 'date' : 'text' }}" value="{{ $value }}"></div>
                        @endif
                    @endforeach
                    <button class="btn cyan">Run Report</button>
                </div>
            </form>
        </aside>
        <div class="search-main">
            <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Columns</h2></div><div class="panel-body muted">{{ implode(' · ', $columns) }}</div></div>
            <div class="panel"><div class="panel-head"><h2>Recent Runs</h2></div><div class="table-wrap"><table>
                <thead><tr><th>Run</th><th>Requested</th><th>By</th><th class="num">Rows</th><th>Status</th></tr></thead>
                <tbody>@forelse ($runs as $run)
                    <tr class="click" data-href="{{ route('walker.runs.show', $run) }}"><td><a href="{{ route('walker.runs.show', $run) }}">#{{ $run->id }}</a></td><td>{{ $run->created_at->format('n/j/Y g:i A') }}</td>
                        <td>{{ $run->user?->name ?? 'System' }}</td><td class="num">{{ $run->status === 'done' ? number_format($run->rows) : '—' }}</td><td>@include('backend.walker._state')</td></tr>
                @empty <tr><td colspan="5" class="empty">Not run yet.</td></tr> @endforelse</tbody>
            </table></div></div>
        </div>
    </div>
@endsection
