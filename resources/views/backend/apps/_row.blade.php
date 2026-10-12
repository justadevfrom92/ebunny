<tr>
    <td><input class="cell-input" name="menu[{{ $i }}][heading]" value="{{ $row['heading'] ?? '' }}" placeholder="Links" aria-label="Heading"></td>
    <td><input class="cell-input" name="menu[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="Link text" aria-label="Label"></td>
    <td><select name="menu[{{ $i }}][route]" aria-label="Admin screen">
        <option value="">— URL instead —</option>
        @foreach ($screens as $route => $label)<option value="{{ $route }}" @selected(($row['route'] ?? '') === $route)>{{ $label }}</option>@endforeach
    </select></td>
    <td><input class="cell-input" name="menu[{{ $i }}][url]" value="{{ $row['url'] ?? '' }}" placeholder="https://…" aria-label="URL"></td>
    <td><button type="button" class="btn sm ghost" data-remove-row>Remove</button></td>
</tr>
