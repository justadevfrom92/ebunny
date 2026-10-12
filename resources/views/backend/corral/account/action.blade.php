@extends('backend.layouts.app')

@section('crumb', 'Account '.$c->account.' › '.$label)

{{-- One account action. Fields come from config/corral.php; with no fields it is a confirmation. --}}
@section('content')
    @include('backend.partials.page-head', [
        'title' => $label,
        'sub' => e($c->name).' · Account <span class="mono">'.e($c->account).'</span> · Balance $'.number_format($c->balance, 2),
        'actions' => '<a class="btn ghost" href="'.route('corral.customers.show', $c).'#actions">Back to account</a>',
    ])

    <div class="panel"><div class="panel-body">
        <form method="post" action="{{ route('corral.customers.action.run', [$c, $action]) }}" class="form-grid">@csrf
            @forelse ($fields as $name => [$fLabel, $type])
                <label for="f-{{ $name }}">{{ $fLabel }}</label>
                @switch($type)
                    @case('money')
                        <input id="f-{{ $name }}" name="{{ $name }}" type="number" step="0.01" min="0.01" value="{{ old($name, $name === 'amount' && $action === 'make-payment' && $c->balance > 0 ? number_format($c->balance, 2, '.', '') : '') }}" required>
                        @break
                    @case('number')
                        <input id="f-{{ $name }}" name="{{ $name }}" type="number" step="1" min="1" value="{{ old($name) }}" required>
                        @break
                    @case('date')
                        <input id="f-{{ $name }}" name="{{ $name }}" type="date" value="{{ old($name, today()->toDateString()) }}" required>
                        @break
                    @case('textarea')
                        <textarea id="f-{{ $name }}" name="{{ $name }}" maxlength="2000" required style="font-family:inherit;min-height:140px">{{ old($name) }}</textarea>
                        @break
                    @case('method')
                        <select id="f-{{ $name }}" name="{{ $name }}" required><option value="">-- Payment method --</option>
                            @foreach ($methods as $m)<option value="{{ $m->id }}" @selected(old($name, $methods->firstWhere('autopay', true)?->id) == $m->id)>{{ $m->label() }}{{ $m->autopay ? ' (AutoPay)' : '' }}</option>@endforeach
                        </select>
                        @break
                    @case('payment')
                        <select id="f-{{ $name }}" name="{{ $name }}" required><option value="">-- Payment --</option>
                            @foreach ($payments as $p)<option value="{{ $p->id }}" @selected(old($name) == $p->id)>{{ $p->paid_on?->format('n/j/Y') }} · ${{ number_format($p->amount, 2) }} · {{ $p->method }} · {{ $p->status }}</option>@endforeach
                        </select>
                        @break
                    @case('template')
                        <select id="f-{{ $name }}" name="{{ $name }}" required><option value="">-- Template --</option>
                            @foreach (\App\Models\EmailTemplate::orderBy('name')->pluck('name') as $t)<option @selected(old($name) === $t)>{{ $t }}</option>@endforeach
                        </select>
                        @break
                    @default
                        <input id="f-{{ $name }}" name="{{ $name }}" maxlength="200" value="{{ old($name) }}" required>
                @endswitch
            @empty
                <p style="grid-column:1/-1">Run <b>{{ $label }}</b> on {{ $c->name }}'s account? A note is added to the account with your name.</p>
            @endforelse
            @if ($action === 'make-payment' && $methods->isEmpty())
                <p class="help" style="grid-column:1/-1">No payment methods on file. Add one through MyAccount or the customer's next payment.</p>
            @endif
            <div class="actions" style="grid-column:1/-1">
                <button class="btn {{ in_array($action, ['delete-order', 'disconnect', 'cancel-order']) ? 'red' : '' }}">{{ $fields ? 'Submit' : 'Yes, '.\Illuminate\Support\Str::after($label, ' - ') }}</button>
                <a class="btn ghost" href="{{ route('corral.customers.show', $c) }}#actions">Cancel</a>
            </div>
        </form>
    </div></div>
@endsection
