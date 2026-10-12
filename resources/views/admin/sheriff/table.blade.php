@extends('admin.layouts.app')

@section('content')
    <form method="post" action="{{ app_route('data.update', $key) }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('admin.partials.page-head', ['title' => $name, 'sub' => 'Reference data used by enrollment, billing and the website. Saving replaces the table with what you see.',
            'actions' => '<button type="button" class="btn ghost" data-add-row="'.count($columns).'">Add Row</button><button class="btn cyan">Save</button>'])
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr>@foreach ($columns as $col)<th>{{ $col }}</th>@endforeach<th></th></tr></thead>
            <tbody id="ref-rows">@foreach ($rows as $i => $row)
                <tr>@foreach ($columns as $c => $col)
                        <td><input class="cell-input" name="rows[{{ $i }}][{{ $c }}]" value="{{ $row->cells[$c] ?? '' }}" aria-label="Row {{ $i + 1 }} {{ $col }}"></td>
                    @endforeach<td><button type="button" class="btn sm ghost" data-remove-row>Remove</button></td></tr>
            @endforeach</tbody>
        </table></div></div>
    </form>
@endsection
