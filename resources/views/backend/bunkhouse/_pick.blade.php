{{--
  Pick one person (radio) or several (checkboxes) from a table, with a filter box. No dropdowns.
  @include('backend.bunkhouse._pick', ['name' => 'user_id', 'people' => $people, 'multiple' => false, 'selected' => [id, …]])
--}}
@php $multiple = $multiple ?? false; $selected = array_map('strval', (array) old(rtrim($name, '[]'), $selected ?? [])); $key = 'pk'.\Illuminate\Support\Str::random(5); @endphp
<div class="person-pick" data-pick>
    <input type="search" placeholder="Type a name, title or company to filter" aria-label="Filter people" data-pick-filter style="margin-bottom:8px">
    <div class="table-wrap pick-list"><table class="table"><tbody>@foreach ($people as $p)
        <tr data-pick-row="{{ strtolower($p->name.' '.$p->title.' '.($p->company ?? $p->department)) }}"><td style="width:32px"><input type="{{ $multiple ? 'checkbox' : 'radio' }}" id="{{ $key.$p->id }}" name="{{ $name }}" value="{{ $p->id }}" @checked(in_array((string) $p->id, $selected, true))></td>
            <td><label for="{{ $key.$p->id }}"><b>{{ $p->name }}</b></label><div class="help">{{ $p->title }}</div></td><td class="help">{{ $p->company ? $p->company.' (3rd party)' : $p->department }}</td></tr>
    @endforeach</tbody></table></div>
</div>
@once
<script>
document.addEventListener('input', function (e) {
    if (!e.target.matches('[data-pick-filter]')) return;
    var q = e.target.value.trim().toLowerCase();
    e.target.closest('[data-pick]').querySelectorAll('[data-pick-row]').forEach(function (r) { r.hidden = q && r.dataset.pickRow.indexOf(q) === -1 && !r.querySelector('input:checked'); });
});
</script>
@endonce
