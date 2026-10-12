@extends('backend.layouts.app')

@section('crumb', $campaign->exists ? $campaign->name : 'New Campaign')

@section('content')
    @php $a = old('audience', $campaign->audience ?? []); $sent = $campaign->status === 'sent'; @endphp
    @include('backend.partials.page-head', ['title' => $campaign->exists ? $campaign->name : 'New Campaign',
        'sub' => $sent ? 'Sent '.$campaign->sent_at->format('n/j/Y g:i A').' to '.number_format($campaign->recipients).' customers.' : ($count !== null ? number_format($count).' customers match this audience.' : 'Pick who gets it and what they get.'),
        'actions' => ! $sent && $campaign->exists && $count ? '<form method="post" action="'.route('rodeo.campaigns.send', $campaign).'" class="inline" data-confirm="Send this campaign?|It goes to '.number_format($count).' customers now.|Send">'.csrf_field().'<button class="btn cyan">Send to '.number_format($count).'</button></form>' : null])
    @if (! $ready && ! $sent)<div class="banner-note">{{ $campaign->channel === 'SMS' ? 'Twilio' : 'Salesforce' }} isn't set up in .env, so sending will log each message without delivering it (Sheriff → APIs).</div>@endif

    <form method="post" action="{{ $campaign->exists ? route('rodeo.campaigns.update', $campaign) : route('rodeo.campaigns.store') }}" class="panel">@csrf @if ($campaign->exists) @method('put') @endif
        <fieldset @disabled($sent) style="border:0;margin:0;padding:0">
        <div class="panel-head"><h2>Message</h2>@unless ($sent)<button class="btn">Save</button>@endunless</div>
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required value="{{ old('name', $campaign->name) }}">
            <label for="channel">Channel</label><select id="channel" name="channel">@foreach (['Email', 'SMS'] as $ch)<option @selected(old('channel', $campaign->channel) === $ch)>{{ $ch }}</option>@endforeach</select>
            <label for="email_template_id">Email Template</label><select id="email_template_id" name="email_template_id"><option value="">—</option>@foreach ($templates as $t)<option value="{{ $t->id }}" @selected(old('email_template_id', $campaign->email_template_id) == $t->id)>{{ $t->name }}</option>@endforeach</select>
            <label for="message">Text Message (SMS)</label><textarea id="message" name="message" maxlength="320" style="min-height:80px;font-family:inherit" placeholder="Hi @{{first_name}}, …">{{ old('message', $campaign->message) }}</textarea>
        </div>
        <div class="panel-head"><h2>Audience</h2></div>
        <div class="panel-body form-grid">
            <label>Account Status</label>@include('backend.partials.multiselect', ['id' => 'a-status', 'name' => 'audience[statuses][]', 'label' => 'Account Status', 'options' => array_keys(config('admin.customer_statuses')), 'selected' => $a['statuses'] ?? [], 'placeholder' => 'Any status', 'disabled' => $sent])
            <label for="a-type">Customer Type</label><select id="a-type" name="audience[type]"><option value="">Any</option>@foreach (['Residential', 'Small Business'] as $t)<option @selected(($a['type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select>
            <label for="a-market">Market</label><select id="a-market" name="audience[market_id]"><option value="">Any</option>@foreach ($markets as $m)<option value="{{ $m->id }}" @selected(($a['market_id'] ?? '') == $m->id)>{{ $m->name }}</option>@endforeach</select>
            <label for="a-has">Has Product</label><select id="a-has" name="audience[product]"><option value="">Any</option>@foreach (array_keys(config('corral.products')) as $p)<option @selected(($a['product'] ?? '') === $p)>{{ $p }}</option>@endforeach</select>
            <label for="a-not">Doesn't Have Product</label><select id="a-not" name="audience[without_product]"><option value="">—</option>@foreach (array_keys(config('corral.products')) as $p)<option @selected(($a['without_product'] ?? '') === $p)>{{ $p }}</option>@endforeach</select>
            <label for="a-ends">Contract Ends Within (days)</label><input id="a-ends" name="audience[contract_ends_within]" type="number" min="1" max="365" value="{{ $a['contract_ends_within'] ?? '' }}" style="max-width:10ch">
        </div>
        </fieldset>
    </form>
@endsection
