/*
  Admin page behaviour. Plain JavaScript, no build step. Pages are rendered by
  Laravel; this only adds conveniences on top of normal forms and links.
*/
(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Keep the sidebar where it was scrolled to when a link takes you to another page
  (function () {
    var side = $('#sidebar');
    if (!side) return;
    // One saved position per admin app (Corral's menu and Lando's menu scroll separately)
    var key = 'admin-sidebar-scroll:' + (location.pathname.split('/')[2] || 'home');
    try {
      var saved = sessionStorage.getItem(key);
      if (saved !== null) side.scrollTop = +saved;
      else { var on = $('.sidebar a.on'); if (on) on.scrollIntoView({ block: 'center' }); }
    } catch (e) {}
    function save() { try { sessionStorage.setItem(key, String(side.scrollTop)); } catch (e) {} }
    side.addEventListener('click', save, true);
    side.addEventListener('scroll', function () { clearTimeout(save.t); save.t = setTimeout(save, 120); });
    window.addEventListener('pagehide', save);
  })();

  function toast(msg) {
    var t = $('#toast');
    if (!t || !msg) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toast.t);
    toast.t = setTimeout(function () { t.classList.remove('show'); }, 3200);
  }

  // A small dialog built into the page (the browser's confirm() is not used)
  function modal(html, onSubmit) {
    var bg = document.createElement('div');
    bg.className = 'modal-bg';
    bg.innerHTML = '<form class="modal" role="dialog" aria-modal="true">' + html + '</form>';
    document.body.appendChild(bg);
    var form = $('form', bg);
    function close() { bg.remove(); document.removeEventListener('keydown', onKey); }
    function onKey(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', onKey);
    bg.addEventListener('click', function (e) { if (e.target === bg || e.target.hasAttribute('data-x')) close(); });
    form.addEventListener('submit', function (e) { e.preventDefault(); close(); onSubmit(form); });
    var first = $('input, button.red, button.cyan', form);
    if (first) first.focus();
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Mobile menu
    var menuBtn = $('#menu-btn');
    if (menuBtn) menuBtn.addEventListener('click', function () { $('#sidebar').classList.toggle('open'); });

    // Flash message from the server
    var t = $('#toast');
    if (t && t.dataset.message) toast(t.dataset.message);

    // Tabs: <div data-tabs> <button data-tab="x"> … <div data-pane="x">
    $$('[data-tabs]').forEach(function (box) {
      var buttons = $$('[data-tab]', box);
      function show(name) {
        buttons.forEach(function (b) { b.classList.toggle('on', b.dataset.tab === name); b.setAttribute('aria-selected', b.dataset.tab === name); });
        $$('[data-pane]', box).forEach(function (p) { p.hidden = p.dataset.pane !== name; });
      }
      buttons.forEach(function (b) {
        b.addEventListener('click', function () { show(b.dataset.tab); history.replaceState(null, '', '#' + b.dataset.tab); });
      });
      // The URL's #tab, else data-tab-initial (e.g. the tab a form was sent from)
      var initial = location.hash.slice(1);
      if (!buttons.some(function (b) { return b.dataset.tab === initial; })) initial = box.dataset.tabInitial || '';
      show(buttons.some(function (b) { return b.dataset.tab === initial; }) ? initial : buttons[0].dataset.tab);
    });

    // Multi-select dropdowns: the closed field lists what's ticked
    $$('[data-multi]').forEach(function (box) {
      var boxes = $$('input[type=checkbox]', box), text = $('[data-multi-text]', box);
      function update() {
        var on = boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.parentNode.textContent.trim(); });
        text.textContent = on.length ? (on.length > 3 ? on.length + ' selected: ' + on.join(', ') : on.join(', ')) : box.dataset.placeholder;
      }
      boxes.forEach(function (b) { b.addEventListener('change', update); });
      var all = $('[data-multi-all]', box), none = $('[data-multi-none]', box);
      if (all) all.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = true; }); update(); });
      if (none) none.addEventListener('click', function () { boxes.forEach(function (b) { b.checked = false; }); update(); });
      update();
    });
    // Close an open dropdown when clicking elsewhere or pressing Escape
    document.addEventListener('click', function (e) {
      $$('[data-multi][open]').forEach(function (d) { if (!d.contains(e.target)) d.open = false; });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') $$('[data-multi][open]').forEach(function (d) { d.open = false; d.querySelector('summary').focus(); });
    });

    // Clickable table rows (delegated, so rows swapped in later, like Walker's live tables, work too)
    document.addEventListener('click', function (e) {
      var tr = e.target.closest && e.target.closest('tr[data-href]');
      if (tr && !e.target.closest('a, button, input, select, form')) location.href = tr.dataset.href;
    });

    // Selects that submit their form on change
    $$('[data-autosubmit]').forEach(function (s) { s.addEventListener('change', function () { s.form.requestSubmit ? s.form.requestSubmit() : s.form.submit(); }); });

    // Forms that need a confirmation first: data-confirm="Title|Body|Button label"
    $$('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed) return;
        e.preventDefault();
        var parts = form.dataset.confirm.split('|');
        modal('<h2>' + esc(parts[0]) + '</h2><p>' + esc(parts[1] || '') + '</p>' +
          '<div class="actions" style="justify-content:flex-end"><button type="button" class="btn ghost" data-x>Cancel</button>' +
          '<button class="btn red">' + esc(parts[2] || 'Confirm') + '</button></div>', function () {
          form.dataset.confirmed = '1';
          form.requestSubmit ? form.requestSubmit() : form.submit();
        });
      });
    });

    // Highlight edited cells in grids
    $$('input[data-track]').forEach(function (i) {
      var orig = i.type === 'checkbox' ? i.checked : i.value;
      i.addEventListener('input', function () {
        var now = i.type === 'checkbox' ? i.checked : i.value;
        i.classList.toggle('dirty', now !== orig);
        if (i.type === 'number') i.classList.toggle('neg', +i.value < 0);
        var label = i.closest('label') && $('[data-label]', i.closest('label'));
        if (label) label.textContent = i.checked ? label.dataset.on : label.dataset.off;
        // Changing a TDSP fee unlocks its effective date
        if (i.dataset.dateFor) {
          var d = document.getElementById(i.dataset.dateFor);
          if (d) { d.disabled = false; if (!d.value || d.value < d.min) d.value = d.min; }
        }
      });
    });

    // Rates: add or subtract an amount from every rate on the screen
    var adjust = $('[data-adjust]');
    if (adjust) adjust.addEventListener('click', function () {
      modal('<h2>Adjust all rates</h2><p>Add an amount to every rate on this screen. Use a negative number to lower rates. Nothing is saved until you choose Save Rates.</p>' +
        '<div class="field"><label for="adj">Change (¢/kWh)</label><input id="adj" type="number" step="0.001" value="0.100" required></div>' +
        '<div class="actions" style="justify-content:flex-end;margin-top:16px"><button type="button" class="btn ghost" data-x>Cancel</button><button class="btn cyan">Apply</button></div>',
        function (form) {
          var d = +$('#adj', form).value;
          $$('input[data-rate]').forEach(function (i) {
            if (i.value === '') return;
            i.value = (+i.value + d).toFixed(3);
            i.dispatchEvent(new Event('input'));
          });
          toast('Adjusted. Review the highlighted cells, then Save Rates.');
        });
    });

    // Content block editor: live preview, Tab inserts spaces, Ctrl+S saves
    var html = $('#b-html'), frame = $('#b-prev');
    if (html && frame) {
      // Variables ({{name}} or {{name|default}}) show their default text (or [name]) in the preview, and are listed under it
      var varList = $('#b-vars');
      var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
      var preview = function () {
        var seen = {}, rows = [];
        var filled = html.value.replace(/\{\{\s*([a-z][a-z0-9_]*)\s*(?:\|([^}]*))?\}\}/gi, function (m, name, def) {
          def = (def || '').trim();
          if (!seen[name]) { seen[name] = 1; rows.push('<tr><td class="mono">' + esc(name) + '</td><td>' + (def ? esc(def) : '<span class="muted">none</span>') + '</td></tr>'); }
          return def ? esc(def) : '[' + esc(name) + ']';
        });
        frame.srcdoc = '<style>body{font-family:sans-serif;padding:12px;color:#102247}</style>' + filled;
        if (varList) varList.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="2" class="empty">No variables: this block shows the same text everywhere.</td></tr>';
      };
      html.addEventListener('input', preview);
      preview();
      html.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); html.form.requestSubmit ? html.form.requestSubmit() : html.form.submit(); }
        if (e.key === 'Tab') { e.preventDefault(); html.setRangeText('  ', html.selectionStart, html.selectionEnd, 'end'); }
      });
    }

    // New plan: fill the URL slug from the name
    var name = $('[data-slug-source]'), slug = $('[data-slug-target]');
    if (name && slug) name.addEventListener('input', function () {
      slug.value = name.value.toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    });

    // Sheriff → Users: Add User dialog
    var addUser = $('[data-add-user]');
    if (addUser) addUser.addEventListener('click', function () {
      var tpl = $('#add-user-form');
      var bg = document.createElement('div');
      bg.className = 'modal-bg';
      bg.appendChild(tpl.content.cloneNode(true));
      document.body.appendChild(bg);
      bg.addEventListener('click', function (e) { if (e.target === bg || e.target.hasAttribute('data-x')) bg.remove(); });
      $('input', bg).focus();
    });

    // Sheriff → Data: add a blank row to a reference table
    var addRow = $('[data-add-row]');
    if (addRow) addRow.addEventListener('click', function () {
      var body = $('#ref-rows');
      var cols = +addRow.dataset.addRow;
      var i = $$('tr', body).length;
      var tr = document.createElement('tr');
      var cells = '';
      for (var c = 0; c < cols; c++) cells += '<td><input class="cell-input" name="rows[' + i + '][' + c + ']" aria-label="Row ' + (i + 1) + ' column ' + (c + 1) + '"></td>';
      tr.innerHTML = cells + '<td><button type="button" class="btn sm ghost" data-remove-row>Remove</button></td>';
      body.appendChild(tr);
      $('input', tr).focus();
    });
    document.addEventListener('click', function (e) {
      var rm = e.target.closest('[data-remove-row]');
      if (rm) rm.closest('tr').remove();
    });

    // Account actions menus: go to the chosen action's page
    $$('form[data-action-form]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var v = $('select', form).value;
        if (v) location.href = form.dataset.actionForm.replace('__action__', encodeURIComponent(v));
      });
    });

    // Notes: the Action list only shows the actions for the chosen Category
    $$('[data-note-category]').forEach(function (cat) {
      var act = $('[data-note-action]', cat.form);
      if (!act) return;
      function filter() {
        $$('optgroup', act).forEach(function (g) { g.hidden = !!cat.value && g.label !== cat.value; g.disabled = g.hidden; });
        var sel = act.selectedOptions[0];
        if (sel && sel.parentNode.disabled) act.value = '';
      }
      cat.addEventListener('change', filter);
      filter();
    });

    // A select that shows its form's extra input only for one choice
    $$('select[data-reveal-when]').forEach(function (s) {
      var input = $('input:not([type=hidden])', s.form);
      function sync() { if (input) { input.hidden = s.value !== s.dataset.revealWhen; input.required = !input.hidden; } }
      s.addEventListener('change', sync);
      sync();
    });

    // Plan group order: move a row up or down
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-move]');
      if (!b) return;
      var li = b.closest('li');
      if (b.dataset.move === '-1' && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
      if (b.dataset.move === '1' && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
      b.focus();
    });

    // Add a row from a <template>: data-clone="#template" data-into="#tbody"; __i__ becomes a unique index
    $$('[data-clone]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tpl = $(btn.dataset.clone), into = $(btn.dataset.into);
        var html = tpl.innerHTML.replace(/__i__/g, 'n' + Date.now());
        into.insertAdjacentHTML('beforeend', html);
        var first = into.lastElementChild && $('input, select', into.lastElementChild);
        if (first) first.focus();
      });
    });
  });
})();
