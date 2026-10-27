@extends('backend.layouts.app')

{{-- A reference table: one input per cell. Tables can set a column order, a type per column (date opens the calendar) and widths (config/admin.php). --}}
@section('content')
    @php
        $order = $options['order'] ?? array_keys($columns);
        $types = $options['types'] ?? [];
        $widths = $options['widths'] ?? [];
        $cell = fn ($i, $c, $value, $label) => '<input class="cell-input" type="'.(($types[$c] ?? 'text') === 'date' ? 'date' : 'text').'" name="rows['.$i.']['.$c.']" value="'.e($value).'" aria-label="'.e($label).'">';
    @endphp
    <form method="post" action="{{ app_route('data.update', $key) }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', ['title' => $name, 'sub' => 'Reference data used by enrollment, billing and the website. Saving replaces the table with what you see.',
            'actions' => '<button type="button" class="btn ghost" data-add-row="'.count($columns).'">Add Row</button><button class="btn cyan">Save</button>'])
        <div class="panel"><div class="table-wrap"><table @if ($widths) class="ref-fixed" @endif>
            @if ($widths)<colgroup>@foreach ($order as $c)<col style="width:{{ $widths[$c] ?? 'auto' }}">@endforeach<col style="width:{{ $widths['remove'] ?? 'auto' }}"></colgroup>@endif
            <thead><tr>@foreach ($order as $c)<th>{{ $columns[$c] }}</th>@endforeach<th><span class="sr-only">Remove</span></th></tr></thead>
            <tbody id="ref-rows">@foreach ($rows as $i => $row)
                <tr>@foreach ($order as $c)<td>{!! $cell($i, $c, $row->cells[$c] ?? '', 'Row '.($i + 1).' '.$columns[$c]) !!}</td>@endforeach<td><button type="button" class="btn sm ghost" data-remove-row>Remove</button></td></tr>
            @endforeach</tbody>
        </table></div></div>
        {{-- A new row, for Add Row (__i__ becomes the row number) --}}
        <template id="ref-row-template"><tr>@foreach ($order as $c)<td>{!! $cell('__i__', $c, '', 'New row '.$columns[$c]) !!}</td>@endforeach<td><button type="button" class="btn sm ghost" data-remove-row>Remove</button></td></tr></template>
    </form>
@endsection
