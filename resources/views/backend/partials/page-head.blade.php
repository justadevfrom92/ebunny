<div class="page-head">
    <div>
        <h1>{{ $title }}</h1>
        @isset($sub)<p class="muted">{!! $sub !!}</p>@endisset
    </div>
    @isset($actions)<div class="actions">{!! $actions !!}</div>@endisset
</div>
