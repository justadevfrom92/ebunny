{{-- A dropdown of checkboxes: @include('backend.partials.multiselect', ['id' => …, 'name' => 'x[]', 'options' => [...], 'selected' => [...], 'placeholder' => 'Any']) --}}
@php $selected = $selected ?? []; $placeholder = $placeholder ?? 'Any'; @endphp
<details class="multi" id="{{ $id }}" data-multi data-placeholder="{{ $placeholder }}" @if (! empty($disabled)) data-disabled @endif>
    <summary aria-label="{{ $label ?? 'Choose' }}"><span data-multi-text>{{ $selected ? implode(', ', $selected) : $placeholder }}</span></summary>
    <div class="multi-list" role="group" aria-label="{{ $label ?? 'Choose' }}">
        <div class="multi-tools"><button type="button" class="btn sm ghost" data-multi-all>Select all</button><button type="button" class="btn sm ghost" data-multi-none>Clear</button></div>
        @foreach ($options as $value => $text)
            @php $value = is_int($value) ? $text : $value; @endphp
            <label class="multi-opt"><input type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked(in_array($value, $selected, true))> <span>{{ $text }}</span></label>
        @endforeach
    </div>
</details>
