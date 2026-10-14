@extends('backend.layouts.app')

@section('crumb', $lead->exists ? 'Edit '.$lead->displayName() : 'New Lead')

@section('content')
    @php $v = fn ($k) => old($k, $lead->{$k} instanceof \Carbon\CarbonInterface ? $lead->{$k}->toDateString() : $lead->{$k}); @endphp
    @include('backend.partials.page-head', ['title' => $lead->exists ? 'Edit '.$lead->displayName() : 'New Lead', 'sub' => 'A home or business that might sign up.'])

    <form method="post" action="{{ $lead->exists ? route('corral.crm.leads.update', $lead) : route('corral.crm.leads.store') }}" class="stack" style="max-width:980px">@csrf @if ($lead->exists) @method('put') @endif
        <div class="panel"><div class="panel-head"><h2>Who</h2></div><div class="panel-body stack">
            <div class="field"><label>Kind</label><div class="chips small">@foreach (\App\Models\CrmLead::KINDS as $k)<label><input type="radio" name="kind" value="{{ $k }}" @checked($v('kind') === $k)><span>{{ $k }}</span></label>@endforeach</div></div>
            <div class="grid-2">
                <div class="field"><label for="name">Contact name</label><input id="name" name="name" required maxlength="150" value="{{ $v('name') }}"></div>
                <div class="field"><label for="company">Business name</label><input id="company" name="company" maxlength="150" value="{{ $v('company') }}"><div class="help">Leave empty for a home.</div></div>
                <div class="field"><label for="title">Job title</label><input id="title" name="title" maxlength="100" value="{{ $v('title') }}"></div>
                <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="200" value="{{ $v('email') }}"></div>
                <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="30" value="{{ $v('phone') }}"></div>
                <div class="field"><label for="city">City</label><input id="city" name="city" maxlength="80" value="{{ $v('city') }}"></div>
            </div>
            <div class="field"><label>Utility area</label><div class="chips small"><label><input type="radio" name="market_id" value="" @checked(! $v('market_id'))><span>Not sure</span></label>@foreach ($markets as $mk)<label><input type="radio" name="market_id" value="{{ $mk->id }}" @checked((string) $v('market_id') === (string) $mk->id)><span>{{ $mk->name }}</span></label>@endforeach</div></div>
        </div></div>

        <div class="panel"><div class="panel-head"><h2>Opportunity</h2></div><div class="panel-body stack">
            <div class="grid-2">
                <div class="field"><label for="annual_kwh">Yearly usage (kWh)</label><input id="annual_kwh" name="annual_kwh" type="number" min="0" value="{{ $v('annual_kwh') }}"></div>
                <div class="field"><label for="est_value">Yearly value ($)</label><input id="est_value" name="est_value" type="number" min="0" step="0.01" value="{{ $v('est_value') ?: '' }}"><div class="help">Empty: estimated at about 14¢ per kWh.</div></div>
                <div class="field"><label for="sites">Sites / meters</label><input id="sites" name="sites" type="number" min="1" value="{{ $v('sites') ?: 1 }}"></div>
                <div class="field"><label for="current_provider">Current provider</label><input id="current_provider" name="current_provider" maxlength="80" value="{{ $v('current_provider') }}"></div>
                <div class="field"><label for="contract_ends_on">Their contract ends</label><input id="contract_ends_on" name="contract_ends_on" type="date" value="{{ $v('contract_ends_on') }}"></div>
                <div class="field"><label for="next_follow_up_on">Next follow-up</label><input id="next_follow_up_on" name="next_follow_up_on" type="date" value="{{ $v('next_follow_up_on') }}"></div>
            </div>
            <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000">{{ $v('notes') }}</textarea></div>
        </div></div>

        <div class="panel"><div class="panel-head"><h2>Source &amp; Owner</h2></div><div class="panel-body stack">
            <div class="field"><label>Source</label><div class="chips small">@foreach (\App\Models\CrmLead::SOURCES as $s)<label><input type="radio" name="source" value="{{ $s }}" @checked($v('source') === $s)><span>{{ $s }}</span></label>@endforeach</div></div>
            <div class="field"><label>Broker or partner <span class="help">(for Broker and Partner leads)</span></label>
                <div class="table-wrap pick-list"><table class="table"><tbody>
                    <tr><td style="width:32px"><input type="radio" id="p0" name="partner_id" value="" @checked(! $v('partner_id'))></td><td><label for="p0">None</label></td><td></td></tr>
                    @foreach ($partners as $p)<tr><td><input type="radio" id="p{{ $p->id }}" name="partner_id" value="{{ $p->id }}" @checked((string) $v('partner_id') === (string) $p->id)></td><td><label for="p{{ $p->id }}"><b>{{ $p->name }}</b></label><div class="help">{{ $p->contact_name }}</div></td><td class="help">{{ $p->kind }}</td></tr>@endforeach
                </tbody></table></div></div>
            <div class="field"><label>Owner</label>
                <div class="table-wrap pick-list"><table class="table"><tbody>@foreach ($owners as $o)
                    <tr><td style="width:32px"><input type="radio" id="o{{ $o->id }}" name="owner_id" value="{{ $o->id }}" @checked((string) $v('owner_id') === (string) $o->id)></td><td><label for="o{{ $o->id }}"><b>{{ $o->name }}</b></label></td><td class="help">{{ $o->title }}{{ $o->company ? ' · '.$o->company : '' }}</td></tr>
                @endforeach</tbody></table></div></div>
        </div></div>

        <div class="actions"><button class="btn cyan">{{ $lead->exists ? 'Save Lead' : 'Add Lead' }}</button><a class="btn ghost" href="{{ $lead->exists ? route('corral.crm.leads.show', $lead) : route('corral.crm.leads') }}">Cancel</a></div>
    </form>
@endsection
