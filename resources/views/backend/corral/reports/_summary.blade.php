{{-- A report's summary: [header, rows] from the report class --}}
<div class="table-wrap"><table>
    <thead><tr>@foreach ($summary[0] as $h)<th @class(['num' => ! $loop->first])>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>@forelse ($summary[1] as $row)
        <tr>@foreach ($row as $v)<td @class(['num' => ! $loop->first])>{{ is_float($v) ? number_format($v, 2) : $v }}</td>@endforeach</tr>
    @empty <tr><td colspan="{{ count($summary[0]) }}" class="empty">Nothing matches.</td></tr> @endforelse</tbody>
</table></div>
