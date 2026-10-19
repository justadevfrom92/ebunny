@extends('frontend.layouts.main')
@section('title', $survey->title)
@section('heading', $survey->title)
@section('show-title', '1')
@section('banner')@if ($survey->intro)<p>{{ $survey->intro }}</p>@endif @endsection
@section('main')
<section class="section"><div class="wrap narrow">
  <div class="co-card">
  @if (session('thanks'))
    <div class="site-alert ok" role="status">{{ $survey->thank_you ?: 'Thank you — your answers were received.' }}</div>
    <p><a class="btn btn-outline btn-back" href="{{ url('/') }}">Back to the homepage</a></p>
  @elseif (! $survey->isOpen())
    <p>{{ $survey->state() === 'scheduled' ? 'This survey opens '.$survey->opens_on->format('F j').'. Please come back then.' : 'This survey is closed. Thank you for your interest.' }}</p>
  @else
    @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
    {{-- The signed link's query string is kept so the answer stays tied to the customer the email was sent to --}}
    <form method="post" action="{{ route('survey.store', $survey).($query ? '?'.http_build_query($query) : '') }}" class="sv-form">@csrf
      @if ($customer)<p class="muted-text">Answering as {{ $customer->first_name }} (account ending {{ substr($customer->account, -4) }}).</p>@endif
      @if ($survey->questions->contains('required', true))<p class="muted-text"><span class="sv-req">*</span> Required</p>@endif
      @php $n = 0; @endphp
      @foreach ($groups as $qs)
        @php $cat = $qs->first()->category; @endphp
        @if ($groups->count() > 1 || $cat)<h3 class="sv-section" style="--dot:{{ $cat?->color ?? '#96999d' }}">{{ $cat?->name ?? 'A few more questions' }}</h3>@endif
        @foreach ($qs as $q)
          @php $n++; $name = 'a['.$q->id.']'; $old = old('a.'.$q->id); @endphp
          <fieldset class="field sv-field">
            <legend>{{ $n }}. {{ $q->question }}@if ($q->required) <span class="sv-req" aria-label="required">*</span>@endif</legend>
            @if ($q->help)<p class="sv-help">{{ $q->help }}</p>@endif
            @switch($q->type)
              @case('rating')
                <div class="rating-row">@for ($v = 0; $v <= 10; $v++)<label class="radio"><input type="radio" name="{{ $name }}" value="{{ $v }}" @checked((string) $old === (string) $v) @required($q->required)> {{ $v }}</label>@endfor</div>
                <p class="muted-text" style="display:flex;justify-content:space-between;margin:0"><span>Not likely</span><span>Very likely</span></p>
                @break
              @case('multi')
                @foreach ($q->choices() as $opt)<label class="check"><input type="checkbox" name="{{ $name }}[]" value="{{ $opt }}" @checked(in_array($opt, (array) $old, true))> {{ $opt }}</label>@endforeach
                @break
              @case('text')
                <textarea name="{{ $name }}" aria-label="{{ $q->question }}" @required($q->required) maxlength="2000">{{ $old }}</textarea>
                @break
              @default
                <div @class(['sv-scale' => $q->type === 'scale', 'sv-yesno' => $q->type === 'yes_no'])>@foreach ($q->choices() as $opt)<label class="radio"><input type="radio" name="{{ $name }}" value="{{ $opt }}" @checked($old === $opt) @required($q->required)> {{ $opt }}</label>@endforeach</div>
            @endswitch
          </fieldset>
        @endforeach
      @endforeach
      <button class="btn" style="margin-top:24px">Submit</button>
    </form>
  @endif
  </div>
</div></section>
@endsection
