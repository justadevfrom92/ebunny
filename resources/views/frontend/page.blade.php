{{-- A website page built in Lando. --}}
@extends('frontend.layouts.main', ['siteName' => $site->name])

@section('title', $page->title)
@if ($page->html_title) @section('full-title', $page->html_title) @endif
@section('head')
  @if ($page->meta_description)<meta name="description" content="{{ $page->meta_description }}">@endif
  @if ($page->meta_keywords)<meta name="keywords" content="{{ $page->meta_keywords }}">@endif
  @if ($page->no_index || $page->status !== 'Published')<meta name="robots" content="noindex">@endif
  @if ($page->canonical)<link rel="canonical" href="{{ $page->liveUrl() }}">@endif
  @if ($hasAmp)<link rel="amphtml" href="{{ url('amp/'.ltrim($page->path, '/')) }}">@endif
  {!! $page->head_content !!}
@endsection
@section('body-attrs')data-page="{{ $page->path }}" @if ($page->promo_code) data-promo="{{ $page->promo_code }}" @endif @if ($page->rep_id) data-rep="{{ $page->rep_id }}" @endif @if ($page->market) data-market="{{ $page->market->name }}" @endif @endsection
@section('notice')
  @if ($page->status !== 'Published')<div style="background:#fff3d6;color:#7a5000;text-align:center;padding:8px;font-weight:600">Draft — only signed-in admin users can see this page.</div>@endif
@endsection
@if ($page->market_label) @section('eyebrow', $page->market_label) @endif
@section('heading', $title)
@section('banner')
  @if ($content['parent'])<div class="banner-sub">{!! $content['parent'] !!}</div>@endif
  @if ($page->phone ?: $site->phone)<p>Call <a href="tel:{{ preg_replace('/\D/', '', $page->phone ?: $site->phone) }}">{{ $page->phone ?: $site->phone }}</a></p>@endif
@endsection

@section('main')
    @if ($zones['top'])<section class="section cms-zone" data-zone="top"><div class="wrap">{!! $zones['top'] !!}</div></section>@endif

    <section class="section">
      <div class="wrap" @if ($zones['sidebar']) style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:40px;align-items:start" @endif>
        <div class="cms-content">
          {!! $content['primary'] !!}
          {!! $content['secondary'] !!}
          {!! $content['auxiliary'] !!}
          @if ($grid)@include('frontend.price-grid', ['grid' => $grid, 'label' => $page->market_label])@endif
          @if ($zones['main'])<div class="cms-zone" data-zone="main">{!! $zones['main'] !!}</div>@endif
        </div>
        @if ($zones['sidebar'])<aside class="cms-zone" data-zone="sidebar">{!! $zones['sidebar'] !!}</aside>@endif
      </div>
    </section>

    @if ($zones['bottom'])<section class="section cms-zone" data-zone="bottom"><div class="wrap">{!! $zones['bottom'] !!}</div></section>@endif
@endsection
