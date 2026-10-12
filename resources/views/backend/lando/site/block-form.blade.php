@extends('backend.layouts.app')

@php $ip = $block->type === 'ip'; $label = $ip ? 'IP' : 'Area'; @endphp
@section('crumb', $block->exists ? 'Edit '.$block->value : 'Block an '.$label)

@section('content')
    @include('backend.partials.page-head', ['title' => $block->exists ? 'Edit Blocked '.$label : 'Block an '.$label,
        'sub' => $ip ? 'Visitors from this address see “Not available” instead of the website. The admin isn\'t affected.' : 'Nobody can open this part of the website, or anything under it.'])
    @if ($views)<div class="banner-note" role="status" style="margin-bottom:16px">{{ $ip ? 'This address' : 'This area' }} had {{ number_format($views['n']) }} page {{ Str::plural('view', $views['n']) }} from {{ $views['people'] }} {{ Str::plural('visitor', $views['people']) }} in the last 30 days{{ $views['last'] ? ', the last '.\Illuminate\Support\Carbon::parse($views['last'])->diffForHumans() : '' }}.</div>@endif
    <form method="post" action="{{ $block->exists ? route('lando.blocked.'.$kind.'.update', $block) : route('lando.blocked.'.$kind.'.store') }}" class="panel simple-form">@csrf @if ($block->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="value">{{ $ip ? 'IP address' : 'Area (path)' }}</label>
            <div><input id="value" name="value" required maxlength="200" value="{{ old('value', $block->value) }}" placeholder="{{ $ip ? '203.0.113.7 or 203.0.113.*' : 'wp-admin or careers' }}" class="mono">
                <div class="help">{{ $ip ? 'End with * for a whole range.' : 'Without the leading slash. “careers” also covers careers/lead-developer.' }}</div></div>
            <label for="reason">Reason</label><input id="reason" name="reason" maxlength="200" value="{{ old('reason', $block->reason) }}" placeholder="For the team, e.g. scraping the rates pages">
            <label for="message">Message to the visitor</label><input id="message" name="message" maxlength="250" value="{{ old('message', $block->message) }}" placeholder="This page isn't available.">
            <label for="expires_at">Blocked until</label><div><input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at', $block->expires_at?->format('Y-m-d\TH:i')) }}"><div class="help">Leave empty to block until you unblock it.</div></div>
            <label for="active">Blocked now</label><label class="check"><input type="hidden" name="active" value="0"><input id="active" type="checkbox" name="active" value="1" @checked(old('active', $block->active))> On</label>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $block->exists ? 'Save' : 'Block' }}</button><a class="btn ghost" href="{{ route('lando.blocked.'.$kind) }}">Cancel</a></div>
    </form>
@endsection
