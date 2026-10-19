<div style="font-family:Arial,sans-serif;color:#111;max-width:640px">
    <p>{{ $lang === 'es' ? 'Hola' : 'Hello' }} {{ $c->first_name }},</p>
    @if ($note)<p>{{ $note }}</p>@endif
    @if ($doc)
        <h2 style="font-size:18px">{{ $doc['title'] }}</h2>
        @foreach ($doc['paragraphs'] as $p)<p>{{ $p }}</p>@endforeach
        @if ($doc['rows'])<table style="border-collapse:collapse">@foreach ($doc['rows'] as $k => $v)<tr><th style="text-align:left;border:1px solid #ccc;padding:4px 8px">{{ $k }}</th><td style="border:1px solid #ccc;padding:4px 8px">{{ $v }}</td></tr>@endforeach</table>@endif
    @else
        <p>{{ $lang === 'es' ? 'Adjuntamos' : 'Attached is' }} {{ $file->name }}.</p>
    @endif
    <p>— {{ config('brand.name') }}</p>
</div>
