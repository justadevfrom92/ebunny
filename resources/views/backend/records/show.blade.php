@extends('backend.layouts.app')

@section('crumb', $label.' #'.$id)

{{-- One record from a table without a page of its own: every field it has. --}}
@section('content')
    @php
        $when = $fields->first(fn ($v, $k) => in_array($k, ['created_at', 'started_at', 'checked_at', 'sent_at', 'failed_at', 'paid_on', 'billed_on'], true) && $v);
        $show = function ($v) {
            if ($v === null || $v === '') return '—';
            if (is_bool($v)) return $v ? 'Yes' : 'No';
            $j = is_string($v) && in_array(substr(ltrim($v), 0, 1), ['{', '['], true) ? json_decode($v, true) : null;
            return $j !== null ? json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $v;
        };
    @endphp
    @include('backend.partials.page-head', ['title' => $label.' #'.$id, 'sub' => $when ? \Illuminate\Support\Carbon::parse($when)->format('n/j/Y g:i A') : '',
        'actions' => $back ? '<a class="btn ghost btn-back" href="'.e($back).'">Back</a>' : ''])

    <div class="panel"><div class="panel-head"><h2>Details</h2><span class="muted">{{ $fields->count() }} fields</span></div>
        <div class="panel-body"><dl class="kv">
            <dt>ID</dt><dd class="mono">#{{ $id }}</dd>
            @if ($customer)<dt>Account</dt><dd>@include('backend.partials.account-link', ['c' => $customer]) <span class="help">{{ $customer->name }}</span></dd>@endif
            @if ($person)<dt>Person</dt><dd>{{ $person->name }}</dd>@endif
            @foreach ($fields as $k => $v)
                @continue(in_array($k, ['customer_id'], true))
                @php $text = $show($v); @endphp
                <dt>{{ \Illuminate\Support\Str::headline($k) }}</dt><dd @class(['mono' => str_ends_with($k, '_id') || str_contains($text, "\n")]) style="white-space:pre-wrap">{{ \Illuminate\Support\Str::limit($text, 4000) }}</dd>
            @endforeach
        </dl></div>
    </div>
@endsection
