@extends('backend.layouts.app')

@section('crumb', $part->exists ? $part->name : 'New '.\App\Models\EmailPart::KINDS[$part->kind])

@section('content')
    @php $label = \App\Models\EmailPart::KINDS[$part->kind]; @endphp
    @include('backend.partials.page-head', ['title' => $part->exists ? $part->name : 'New '.$label, 'sub' => 'Email '.strtolower($label).'. Fill-ins work here too: <span class="mono">&#123;&#123;first_name&#125;&#125;</span> <span class="mono">&#123;&#123;account&#125;&#125;</span> <span class="mono">&#123;&#123;balance&#125;&#125;</span> <span class="mono">&#123;&#123;plan&#125;&#125;</span>.',
        'actions' => '<a class="btn ghost btn-back" href="'.route('rodeo.parts').'">Headers &amp; Footers</a>'
            .($part->exists && ! $part->is_default ? '<form method="post" action="'.route('rodeo.parts.destroy', $part).'" class="inline" data-confirm="Delete '.e($part->name).'?|Templates using it switch to the default '.strtolower($label).'.|Delete">'.csrf_field().method_field('delete').'<button class="btn red">Delete</button></form>' : '')])

    <div class="grid-2">
        <form method="post" action="{{ $part->exists ? route('rodeo.parts.update', $part) : route('rodeo.parts.store') }}" class="panel">@csrf @if ($part->exists) @method('put') @else<input type="hidden" name="kind" value="{{ $part->kind }}">@endif
            <div class="panel-body form-grid" style="grid-template-columns:1fr">
                <label for="name">Name</label><input id="name" name="name" required maxlength="100" value="{{ old('name', $part->name) }}">
                <label for="b-html">{{ $label }} (HTML)</label><textarea id="b-html" name="html" spellcheck="false" style="min-height:260px">{{ old('html', $part->html) }}</textarea>
                <label class="check"><input type="hidden" name="is_default" value="0"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $part->is_default)) @disabled($part->is_default)> Default {{ strtolower($label) }} (for templates that don't pick one)</label>
            </div>
            <div class="panel-body"><button class="btn cyan">Save {{ $label }}</button></div>
        </form>
        <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
            <div class="panel"><div class="panel-head"><h2>Preview</h2></div>
                <div class="panel-body"><iframe id="b-prev" title="Preview" sandbox="" style="width:100%;min-height:240px;border:1px solid var(--line);border-radius:6px;background:#fff"></iframe></div></div>
            @if ($part->exists)
                <div class="panel"><div class="panel-head"><h2>Templates Using It</h2><span class="muted">{{ $templates->count() }}</span></div><div class="table-wrap"><table><tbody>
                    @forelse ($templates as $t)<tr class="click" data-href="{{ route('rodeo.templates.edit', $t) }}"><td><a href="{{ route('rodeo.templates.edit', $t) }}">{{ $t->name }}</a></td><td class="help">{{ ($part->kind === 'header' ? $t->email_header_id : $t->email_footer_id) ? 'picked' : 'by default' }}</td></tr>
                    @empty <tr><td class="empty">No templates use it yet.</td></tr> @endforelse
                </tbody></table></div></div>
            @endif
        </div>
    </div>
@endsection
