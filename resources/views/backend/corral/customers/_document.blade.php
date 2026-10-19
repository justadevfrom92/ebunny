{{-- A rendered account document (title, paragraphs, a table of values). $doc --}}
<div class="cust-doc">
    <h2>{{ $doc['title'] }}</h2>
    @foreach ($doc['paragraphs'] as $p)<p>{{ $p }}</p>@endforeach
    @if ($doc['rows'])<table class="cust-doc-table">@foreach ($doc['rows'] as $k => $v)<tr><th>{{ $k }}</th><td>{{ $v }}</td></tr>@endforeach</table>@endif
</div>
