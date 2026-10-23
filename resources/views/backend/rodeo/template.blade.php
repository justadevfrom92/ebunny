@extends('backend.layouts.app')

@section('crumb', $template->exists ? $template->name : 'New Template')

@section('content')
    @include('backend.partials.page-head', ['title' => $template->exists ? $template->name : 'New Template', 'sub' => 'Fill-ins: <span class="mono">&#123;&#123;first_name&#125;&#125;</span> <span class="mono">&#123;&#123;account&#125;&#125;</span> <span class="mono">&#123;&#123;balance&#125;&#125;</span> <span class="mono">&#123;&#123;plan&#125;&#125;</span> and <span class="mono">&#123;&#123;survey:slug&#125;&#125;</span> for a survey link.',
        'actions' => $template->exists ? '<a class="btn ghost" href="'.route('rodeo.templates.heatmap', $template).'">Click Heat Map</a><a class="btn cyan" href="'.route('rodeo.templates.test', $template).'">Send Test</a>' : null])
    <div class="grid-2">
        <form method="post" action="{{ $template->exists ? route('rodeo.templates.update', $template) : route('rodeo.templates.store') }}" class="panel">@csrf @if ($template->exists) @method('put') @endif
            <div class="panel-body form-grid" style="grid-template-columns:1fr">
                <label for="name">Name</label><input id="name" name="name" required value="{{ old('name', $template->name) }}">
                <label for="email_category_id">Category</label><select id="email_category_id" name="email_category_id"><option value="">None</option>@foreach ($categories as $cat)<option value="{{ $cat->id }}" @selected(old('email_category_id', $template->email_category_id) == $cat->id)>{{ $cat->name }}</option>@endforeach</select>
                <label for="subject">Subject</label><input id="subject" name="subject" required value="{{ old('subject', $template->subject) }}">
                @foreach (['header' => 'Header', 'footer' => 'Footer'] as $kind => $label)
                    @php $field = 'email_'.$kind.'_id'; $default = $parts[$kind]->firstWhere('is_default', true); @endphp
                    <label for="{{ $field }}">{{ $label }}</label>
                    <div><select id="{{ $field }}" name="{{ $field }}" data-part="{{ $kind }}">
                        <option value="" @selected(! old($field, $template->$field))>Default{{ $default ? ' ('.$default->name.')' : '' }}</option>
                        @foreach ($parts[$kind] as $p)<option value="{{ $p->id }}" @selected(old($field, $template->$field) == $p->id)>{{ $p->name }}</option>@endforeach
                    </select><div class="help"><a href="{{ route('rodeo.parts') }}">Edit headers &amp; footers</a></div></div>
                @endforeach
                <label for="b-html">Body (HTML)</label><textarea id="b-html" name="body" spellcheck="false">{{ old('body', $template->body) }}</textarea>
            </div>
            <div class="panel-body"><button class="btn cyan">Save Template</button></div>
        </form>
        <div class="panel"><div class="panel-head"><h2>Preview</h2>@if ($template->exists && $sample)<span class="muted">for {{ $sample->first_name }} ({{ $sample->account }})</span>@endif</div>
            <div class="panel-body"><iframe id="b-prev" title="Email preview" sandbox="" style="width:100%;min-height:520px;border:1px solid var(--line);border-radius:6px;background:#fff"></iframe></div>
        </div>
    </div>

    {{-- The header and footer HTML, for the live preview --}}
    @foreach (['header', 'footer'] as $kind)
        <template data-part-html="{{ $kind }}:">{!! $parts[$kind]->firstWhere('is_default', true)?->html !!}</template>
        @foreach ($parts[$kind] as $p)<template data-part-html="{{ $kind }}:{{ $p->id }}">{!! $p->html !!}</template>@endforeach
    @endforeach
    <script>
    (function () {
        function part(kind) {
            var picked = document.querySelector('select[data-part="' + kind + '"]');
            var t = document.querySelector('template[data-part-html="' + kind + ':' + (picked ? picked.value : '') + '"]');
            return t ? t.innerHTML : '';
        }
        window.emailWrap = function (body) { return part('header') + body + part('footer'); };
        document.querySelectorAll('select[data-part]').forEach(function (r) { r.addEventListener('change', function () { document.dispatchEvent(new Event('email-parts-changed')); }); });
    })();
    </script>
@endsection
