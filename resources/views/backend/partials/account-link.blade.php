{{-- An account number, linked to its Corral account (everywhere in the admin). $c = customer or null --}}
@if ($c)<a class="mono" href="{{ route('corral.customers.show', $c) }}">{{ $c->account }}</a>@else—@endif
