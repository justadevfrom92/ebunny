@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Headers & Footers', 'sub' => 'The top and bottom of every email. Each template picks a header and a footer; templates that don\'t pick one use the default.',
        'actions' => '<a class="btn ghost" href="'.route('rodeo.parts.create', ['kind' => 'header']).'">New Header</a><a class="btn cyan" href="'.route('rodeo.parts.create', ['kind' => 'footer']).'">New Footer</a>'])

    @foreach (\App\Models\EmailPart::KINDS as $kind => $label)
        <div class="panel"><div class="panel-head"><h2>{{ $label }}s</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Default</th><th class="num">Templates Using It</th><th>Changed</th></tr></thead>
            <tbody>@forelse ($parts[$kind] ?? [] as $p)
                <tr class="click" data-href="{{ route('rodeo.parts.edit', $p) }}"><td><a href="{{ route('rodeo.parts.edit', $p) }}"><b>{{ $p->name }}</b></a>@if (trim($p->html) === '')<div class="help">Empty: the email has no {{ strtolower($label) }}</div>@endif</td>
                    <td>@if ($p->is_default)@include('backend.partials.pill', ['text' => 'Default', 'tone' => 'ok'])@endif</td>
                    <td class="num">{{ $p->templates_count + ($p->is_default ? $usingDefault[$kind] : 0) }}</td>
                    <td class="nowrap">{{ $p->updated_at->format('n/j/Y') }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No {{ strtolower($label) }}s yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    @endforeach
@endsection
