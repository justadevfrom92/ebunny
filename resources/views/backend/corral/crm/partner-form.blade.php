@extends('backend.layouts.app')

@section('crumb', $p->exists ? 'Edit '.$p->name : 'New Partner')

@section('content')
    @php $v = fn ($k) => old($k, $p->{$k} instanceof \Carbon\CarbonInterface ? $p->{$k}->toDateString() : $p->{$k}); @endphp
    @include('backend.partials.page-head', ['title' => $p->exists ? 'Edit '.$p->name : 'New Partner', 'sub' => 'A broker or referral partner who sends leads.'])
    <form method="post" action="{{ $p->exists ? route('corral.crm.partners.update', $p) : route('corral.crm.partners.store') }}" class="stack" style="max-width:980px">@csrf @if ($p->exists) @method('put') @endif
        <div class="panel"><div class="panel-body stack">
            <div class="field"><label>Kind</label><div class="chips small">@foreach (\App\Models\CrmPartner::KINDS as $k)<label><input type="radio" name="kind" value="{{ $k }}" @checked($v('kind') === $k)><span>{{ $k }}</span></label>@endforeach</div></div>
            <div class="grid-2">
                <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="150" value="{{ $v('name') }}"></div>
                <div class="field"><label for="contact_name">Contact</label><input id="contact_name" name="contact_name" maxlength="120" value="{{ $v('contact_name') }}"></div>
                <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="200" value="{{ $v('email') }}"></div>
                <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" maxlength="30" value="{{ $v('phone') }}"></div>
                <div class="field"><label for="city">City</label><input id="city" name="city" maxlength="80" value="{{ $v('city') }}"></div>
                <div class="field"><label for="agreement_ends_on">Agreement ends</label><input id="agreement_ends_on" name="agreement_ends_on" type="date" value="{{ $v('agreement_ends_on') }}"></div>
            </div>
            <div class="field"><label>Commission paid</label><div class="chips small"><label><input type="radio" name="commission_basis" value="kwh" @checked($v('commission_basis') === 'kwh')><span>Per kWh</span></label><label><input type="radio" name="commission_basis" value="flat" @checked($v('commission_basis') === 'flat')><span>Share of first-year revenue</span></label></div></div>
            <div class="field"><label for="commission_rate">Rate</label><input id="commission_rate" name="commission_rate" type="number" step="0.0001" min="0" max="1" required value="{{ $v('commission_rate') }}" style="max-width:16ch"><div class="help">Per kWh in dollars (0.004 = 0.4¢), or a share (0.05 = 5%).</div></div>
            <div class="field"><label>Status</label><div class="chips small">@foreach (\App\Models\CrmPartner::STATUSES as $s)<label><input type="radio" name="status" value="{{ $s }}" @checked($v('status') === $s)><span>{{ $s }}</span></label>@endforeach</div></div>
            <div class="field"><label>Our contact</label><div class="table-wrap pick-list"><table class="table"><tbody>@foreach ($owners as $o)
                <tr><td style="width:32px"><input type="radio" id="o{{ $o->id }}" name="owner_id" value="{{ $o->id }}" @checked((string) $v('owner_id') === (string) $o->id)></td><td><label for="o{{ $o->id }}"><b>{{ $o->name }}</b></label></td><td class="help">{{ $o->title }}</td></tr>
            @endforeach</tbody></table></div></div>
            <div class="field"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000">{{ $v('notes') }}</textarea></div>
        </div></div>
        <div class="actions"><button class="btn cyan">{{ $p->exists ? 'Save Partner' : 'Add Partner' }}</button><a class="btn ghost" href="{{ $p->exists ? route('corral.crm.partners.show', $p) : route('corral.crm.partners') }}">Cancel</a></div>
    </form>
@endsection
