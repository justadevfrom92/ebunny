@if ($value)<span class="pill {{ config('corral.resolutions')[$value] ?? '' }}">{{ $value }}</span>@else<span class="pill">Open</span>@endif
