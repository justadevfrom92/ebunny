@extends('backend.layouts.app')

@section('crumb', 'Edit '.$model.' '.$r->getKey())

@section('content')
    @include('backend.partials.page-head', ['title' => 'Edit '.$model, 'sub' => e($title).' · id <span class="mono">'.$r->getKey().'</span>',
        'actions' => '<a class="btn ghost" href="'.route('corral.items.show', [$r->getKey(), $model]).'">Cancel</a>'])

    <form method="post" action="{{ route('corral.items.update', [$r->getKey(), $model]) }}" class="panel">@csrf @method('put')
        <div class="panel-body form-grid">
            @foreach ($values as $field => $value)
                @php $secret = in_array($field, config('items.secret'), true); $locked = $field === 'id' || $secret || in_array($field, $readOnly, true); @endphp
                <label for="f-{{ $field }}" class="mono">{{ $field }}</label>
                <div>
                    @if ($locked)
                        <input id="f-{{ $field }}" value="{{ $value }}" disabled>
                        @if ($secret)<div class="help">Hidden. Sensitive fields are never shown or edited here.</div>@elseif ($field !== 'id')<div class="help">Comes from the account; change it on its own screen.</div>@endif
                    @elseif (strlen($value) > 70 || in_array($field, ['cmd', 'note', 'description', 'original_click_data', 'adjustments', 'error_log'], true))
                        <textarea id="f-{{ $field }}" name="f[{{ $field }}]" rows="3" style="min-height:70px;font-family:inherit">{{ old('f.'.$field, $value) }}</textarea>
                    @else
                        <input id="f-{{ $field }}" name="f[{{ $field }}]" value="{{ old('f.'.$field, $value) }}">
                    @endif
                </div>
            @endforeach
        </div>
        <div class="panel-body"><button class="btn cyan">Save</button></div>
    </form>
@endsection
