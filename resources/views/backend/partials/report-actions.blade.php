{{--
  Report buttons: a save icon (download the report) and a reload icon (run it again).
  @include('backend.partials.report-actions', [
      'download' => url or null, 'downloadLabel' => 'Download .xlsx',
      'rerun' => url or null, 'rerunMethod' => 'post' | 'get',
      'size' => 'page' (icon + label, page top right) | 'row' (icon only, in tables),
      'why' => why a button is unavailable (shown when it is),
  ])
--}}
@php
    $size = $size ?? 'row';
    $dl = $downloadLabel ?? 'Download';
    $save = '<svg class="ri" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>';
    $reload = '<svg class="ri" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>';
@endphp
<span @class(['report-actions', 'ra-page' => $size === 'page'])>
    @if (! empty($download))
        <a class="btn {{ $size === 'page' ? 'ghost' : 'sm ghost icon' }}" href="{{ $download }}" aria-label="{{ $dl }}" data-tip="{{ $dl }}">{!! $save !!}@if ($size === 'page')<span>{{ $dl }}</span>@endif</a>
    @else
        <span class="btn {{ $size === 'page' ? 'ghost' : 'sm ghost icon' }} disabled" aria-disabled="true" role="img" aria-label="Download unavailable: {{ $why ?? 'no file saved for this run' }}" data-tip="No download: {{ $why ?? 'no file saved for this run' }}">{!! $save !!}@if ($size === 'page')<span>{{ $dl }}</span>@endif</span>
    @endif
    @if (! empty($rerun))
        @if (($rerunMethod ?? 'post') === 'get')
            <a class="btn {{ $size === 'page' ? 'cyan' : 'sm ghost icon' }}" href="{{ $rerun }}" aria-label="Rerun report" data-tip="Rerun report">{!! $reload !!}@if ($size === 'page')<span>Rerun</span>@endif</a>
        @else
            <form method="post" action="{{ $rerun }}" class="inline">@csrf<button class="btn {{ $size === 'page' ? 'cyan' : 'sm ghost icon' }}" aria-label="Rerun report" data-tip="Rerun report">{!! $reload !!}@if ($size === 'page')<span>Rerun</span>@endif</button></form>
        @endif
    @endif
</span>
