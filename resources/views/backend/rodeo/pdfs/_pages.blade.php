{{-- PDF pages drawn as images, plus the PDF itself as base64 for View PDF / Download PDF ($pages, $pdf64, $name). --}}
<div class="pdf-viewer pdf-pages" data-pdf64="{{ $pdf64 }}" data-pdf-name="{{ $name }}">
    @foreach ($pages as $i => $svg)
        <div class="pdf-page" aria-label="Page {{ $i + 1 }} of {{ count($pages) }}">{!! $svg !!}</div>
    @endforeach
</div>
