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

  // Keep the sidebar menu where it was scrolled to when a link takes you to another page
  (function () {
    var side = $('#menu');
    if (!side) return;
    // One saved position per admin app (Corral's menu and Lando's menu scroll separately)
    var key = 'admin-sidebar-scroll:' + (location.pathname.split('/')[2] || 'home');
    try {
      var saved = sessionStorage.getItem(key);
      if (saved !== null) side.scrollTop = +saved;
      else { var on = $('.sidebar a.on'); if (on) side.scrollTop = on.offsetTop - side.offsetTop - side.clientHeight / 2; }
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

  // Any row of pills with more than 3 choices becomes one dropdown on its own line: radio pills become
  // a <select> with the same name, link pills (filters, views) a <select> that opens the picked link,
  // and button pills a <select> that presses the picked button. Checkbox pills (pick several) stay.
  function pillsToSelects(root) {
    $$('.chips', root).forEach(function (box) {
      if (box.hasAttribute('data-keep-pills') || box.closest('[data-keep-pills]')) return;
      var labels = $$(':scope > label', box), radios = labels.map(function (l) { return l.querySelector('input[type=radio]'); }).filter(Boolean);
      var links = $$(':scope > a.chip-link, :scope > button.chip-link', box);
      var text = function (el) {   // "Renewal — Converted: …" when a pill has a title and a note
        var b = el.querySelector('b'), sm = el.querySelector('small');
        return (b && sm ? b.textContent.trim() + ' — ' + sm.textContent.trim() : el.textContent).replace(/\s+/g, ' ').trim();
      };
      var sel = document.createElement('select');
      sel.className = 'pill-select';
      var lab = box.getAttribute('aria-label') || (box.previousElementSibling && box.previousElementSibling.tagName === 'LABEL' ? text(box.previousElementSibling) : '');
      if (lab) sel.setAttribute('aria-label', lab);
      if (radios.length > 3 && radios.length === labels.length && !box.querySelector('input[type=checkbox]')) {
        sel.name = radios[0].name;
        if (radios.some(function (r) { return r.required; })) sel.required = true;
        var change = radios[0].getAttribute('onchange'); if (change) sel.setAttribute('onchange', change.replace(/this\.form/g, 'this.form'));
        if (!radios.some(function (r) { return r.checked; })) { var none = new Option('Choose…', ''); none.disabled = !sel.required ? false : true; sel.add(none); none.selected = true; }
        labels.forEach(function (l, i) {
          var r = radios[i], o = new Option(text(l), r.value);
          o.selected = r.checked; o.disabled = r.disabled || l.hidden;
          [].forEach.call(l.attributes, function (a) { if (a.name.indexOf('data-') === 0) o.setAttribute(a.name, a.value); });
          sel.add(o);
        });
        box.replaceWith(sel);
      } else if (labels.length > 3 && labels.every(function (l) { return l.querySelector('input[type=checkbox]'); })) {
        // Pick-several pills: a dropdown of checkboxes (the same inputs, moved, so names and values don't change)
        var d = document.createElement('details'); d.className = 'multi'; d.setAttribute('data-multi', ''); d.dataset.placeholder = 'Any';
        d.innerHTML = '<summary><span data-multi-text>Any</span></summary><div class="multi-list" role="group"><div class="multi-tools"><button type="button" class="btn sm ghost" data-multi-all>Select all</button><button type="button" class="btn sm ghost" data-multi-none>Clear</button></div></div>';
        if (lab) d.querySelector('summary').setAttribute('aria-label', lab);
        var list = d.querySelector('.multi-list');
        labels.forEach(function (l) { var opt = document.createElement('label'), sp = document.createElement('span'); opt.className = 'multi-opt'; sp.textContent = text(l); opt.appendChild(l.querySelector('input')); opt.appendChild(document.createTextNode(' ')); opt.appendChild(sp); list.appendChild(opt); });
        box.replaceWith(d);
      } else if (links.length > 3 && !box.querySelector('input')) {
        var isLink = links[0].tagName === 'A', on = links.filter(function (a) { return a.classList.contains('on'); })[0];
        if (!on || !isLink) { var ph = new Option(isLink ? 'Any' : 'Pick one…', ''); ph.disabled = true; ph.selected = true; sel.add(ph); }
        links.forEach(function (a, i) { var o = new Option(text(a), isLink ? a.getAttribute('href') : String(i)); o.selected = a === on; sel.add(o); });
        sel.addEventListener('change', function () {
          if (isLink) { var go = document.createElement('a'); go.href = sel.value; go.hidden = true; document.body.appendChild(go); go.click(); }
          else { links[+sel.value].click(); sel.selectedIndex = 0; }
        });
        if (isLink) box.replaceWith(sel); else { box.hidden = true; box.parentNode.insertBefore(sel, box); }
      }
    });
  }
  window.pillsToSelects = pillsToSelects;

  document.addEventListener('DOMContentLoaded', function () {
    pillsToSelects(document);
    document.documentElement.classList.add('pills-done');

    // Heartbeat timeline: click (or Enter on) a 15-minute block to see what happened in it
    var hbPicked = null;
    function hbEsc(v) { return String(v == null ? '' : v).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function hbShow(strip, i) {
      var data = JSON.parse(strip.parentNode.querySelector('.hb-data').textContent), b = data.blocks[i], L = data.labels, box = strip.parentNode.querySelector('.hb-detail');
      if (!b || !box) return;
      var pill = function (st) { var l = L[st] || [st, '']; return '<span class="pill ' + l[1] + '">' + hbEsc(l[0]) + '</span>'; };
      $$('i', strip).forEach(function (x) { x.classList.toggle('picked', +x.getAttribute('data-i') === i); x.setAttribute('aria-pressed', +x.getAttribute('data-i') === i); });
      var h = '<div class="hb-detail-head"><div><b>' + hbEsc(b.t) + '</b><span class="help"> · ' + (b.n ? b.n + ' check' + (b.n === 1 ? '' : 's') + ' ran' : 'no check ran') + '</span></div>'
        + (b.n ? pill(b.s) : '') + '<button type="button" class="btn sm ghost" data-hb-close>Close</button></div>';
      if (!b.n) h += '<p class="help">No check ran in these 15 minutes: the scheduler wasn\'t running.</p>';
      else if (strip.getAttribute('data-hb-mode') === 'all') {
        h += b.rows.length ? '<div class="table-wrap"><table><thead><tr><th>Check</th><th>Worst</th><th>Detail</th><th class="num">Minutes</th></tr></thead><tbody>'
          + b.rows.map(function (r) { return '<tr class="click" data-href="' + hbEsc(r[4]) + '"><td><a href="' + hbEsc(r[4]) + '"><b>' + hbEsc(r[0]) + '</b></a></td><td>' + pill(r[1]) + '</td><td class="wrap">' + hbEsc(r[2]) + '</td><td class="num">' + r[3] + '</td></tr>'; }).join('')
          + '</tbody></table></div>' : '<p class="help">Every check was healthy.</p>';
      } else {
        h += '<div class="table-wrap"><table><thead><tr><th>Time</th><th>Result</th><th>Detail</th><th class="num">Checks</th></tr></thead><tbody>'
          + b.rows.map(function (r) { return '<tr><td class="nowrap">' + hbEsc(r[0] === r[1] ? r[0] : r[0] + ' – ' + r[1]) + '</td><td>' + pill(r[2]) + '</td><td class="wrap">' + hbEsc(r[3]) + '</td><td class="num">' + r[4] + '</td></tr>'; }).join('')
          + '</tbody></table></div>';
      }
      if (b.actions.length) h += '<h3 class="hb-detail-sub">Controls Used</h3><div class="table-wrap"><table><thead><tr><th>Time</th>' + (b.actions[0][1] ? '<th>Check</th>' : '') + '<th>Control</th><th>Who</th><th>Result</th></tr></thead><tbody>'
        + b.actions.map(function (a) { return '<tr><td class="nowrap">' + hbEsc(a[0]) + '</td>' + (a[1] ? '<td>' + hbEsc(a[1]) + '</td>' : '') + '<td><b>' + hbEsc(a[2]) + '</b></td><td>' + hbEsc(a[3]) + '</td><td>' + (a[4] ? '<span class="pill ok">Done</span>' : '<span class="pill bad">Failed</span>') + '</td></tr>'; }).join('') + '</tbody></table></div>';
      box.innerHTML = h; box.hidden = false; hbPicked = b.t;
    }
    document.addEventListener('click', function (e) {
      var blk = e.target.closest('.hb-strip i[data-i]');
      if (blk) return hbShow(blk.parentNode, +blk.getAttribute('data-i'));
      var close = e.target.closest('[data-hb-close]');
      if (close) { var box = close.closest('.hb-detail'); box.hidden = true; hbPicked = null; $$('.hb-strip i.picked').forEach(function (x) { x.classList.remove('picked'); }); }
    });
    document.addEventListener('keydown', function (e) {
      var blk = e.target.closest && e.target.closest('.hb-strip i[data-i]');
      if (blk && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); hbShow(blk.parentNode, +blk.getAttribute('data-i')); }
    });
    // The overview reloads itself: open the same block again
    document.addEventListener('hb:refresh', function () {
      if (!hbPicked) return;
      $$('.hb-strip').forEach(function (strip) {
        var blocks = JSON.parse(strip.parentNode.querySelector('.hb-data').textContent).blocks;
        for (var i = 0; i < blocks.length; i++) if (blocks[i].t === hbPicked) return hbShow(strip, i);
      });
    });

    // Account file PDFs: the page carries the PDF as base64; turn it into a blob for View PDF and Download PDF
    $$('.pdf-viewer[data-pdf64]').forEach(function (box) {
      var bin = atob(box.getAttribute('data-pdf64')), bytes = new Uint8Array(bin.length);
      for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
      var url = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' })), name = box.getAttribute('data-pdf-name');
      $$('[data-pdf-open]').forEach(function (a) { a.href = url; });
      $$('[data-pdf-download]').forEach(function (a) { a.href = url; a.setAttribute('download', name); });
    });

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

    // Live search: an input[data-live-filter] hides rows ([data-filter-row]) that don't contain every typed word,
    // and whole groups ([data-filter-group]) with nothing left; [data-live-count] shows how many match
    $$('[data-live-filter]').forEach(function (input) {
      var rows = $$('[data-filter-row]'), groups = $$('[data-filter-group]'), count = $('[data-live-count]'), empty = $('[data-live-empty]');
      function run() {
        var words = input.value.toLowerCase().trim().split(/\s+/).filter(Boolean), shown = 0;
        rows.forEach(function (r) { var hay = r.dataset.filterRow, ok = words.every(function (w) { return hay.indexOf(w) >= 0; }); r.hidden = !ok; if (ok) shown++; });
        groups.forEach(function (g) { g.hidden = !g.querySelector('[data-filter-row]:not([hidden])'); });
        if (count) count.textContent = words.length ? shown + ' of ' + rows.length : rows.length + ' people';
        if (empty) empty.hidden = shown > 0;
      }
      input.addEventListener('input', run); run();
    });

    // Search as you type: input[data-auto-search="#a,#b"] in a GET form reloads the page's results in
    // the background (no Search button) and swaps in the listed parts, keeping the cursor in the box
    $$('[data-auto-search]').forEach(function (input) {
      var form = input.form, sels = input.dataset.autoSearch.split(','), timer, ctl;
      form.addEventListener('submit', function (e) { e.preventDefault(); clearTimeout(timer); run(); });
      input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 300); });
      function run() {
        var url = new URL(form.getAttribute('action') || location.pathname, location.href), params = new URLSearchParams();
        new FormData(form).forEach(function (v, k) { if (String(v).trim() !== '') params.append(k, v); });
        url.search = params.toString();
        if (ctl) ctl.abort();
        ctl = window.AbortController ? new AbortController() : null;
        fetch(url, { signal: ctl ? ctl.signal : undefined, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
          .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
          .then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html'), found = 0;
            sels.forEach(function (sel) { var now = $(sel.trim()), fresh = doc.querySelector(sel.trim()); if (now && fresh) { now.replaceWith(fresh); pillsToSelects(fresh); found++; } });
            if (!found) throw new Error('no results');
            history.replaceState(null, '', url);
          }).catch(function (e) { if (!e || e.name !== 'AbortError') location.href = url; });   // fall back to loading the page
      }
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
      var preview = function () {
        var seen = {}, rows = [];
        // An email template's page wraps the body in the header and footer it picked (window.emailWrap)
        var source = window.emailWrap ? window.emailWrap(html.value) : html.value;
        var filled = source.replace(/\{\{\s*([a-z][a-z0-9_]*)\s*(?:\|([^}]*))?\}\}/gi, function (m, name, def) {
          def = (def || '').trim();
          if (!seen[name]) { seen[name] = 1; rows.push('<tr><td class="mono">' + esc(name) + '</td><td>' + (def ? esc(def) : '<span class="muted">none</span>') + '</td></tr>'); }
          return def ? esc(def) : '[' + esc(name) + ']';
        });
        frame.srcdoc = '<style>body{font-family:sans-serif;padding:12px;color:#102247}</style>' + filled;
        // The Variables panel shows only when the block has {{variables}}
        if (varList) { varList.innerHTML = rows.join(''); varList.closest('.panel').hidden = !rows.length; }
      };
      html.addEventListener('input', preview);
      document.addEventListener('email-parts-changed', preview);
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

    // User menu (top right): click your name for My Files, Switch user and Logout
    var um = $('[data-user-menu]');
    if (um) {
      var toggle = $('[data-user-toggle]', um), pop = $('[data-user-pop]', um), search = $('[data-up-search]', um);
      function setOpen(open) {
        pop.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && search) search.focus();
      }
      toggle.addEventListener('click', function (e) { e.stopPropagation(); setOpen(pop.hidden); });
      document.addEventListener('click', function (e) { if (!pop.hidden && !um.contains(e.target)) setOpen(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !pop.hidden) { setOpen(false); toggle.focus(); } });
      if (search) search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        $$('.up-users button', um).forEach(function (b) { b.hidden = q && b.dataset.name.indexOf(q) < 0; });
      });

      // Background reports: the badge counts finished files you haven't seen; a popup says when each one is ready
      var badge = $('[data-files-badge]', um), box = null, KEY = 'admin.notified';
      function notified() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
      function remember(ids) { try { localStorage.setItem(KEY, JSON.stringify(ids.slice(-100))); } catch (e) {} }
      function popup(it) {
        if (!box) { box = document.createElement('div'); box.className = 'notices'; box.setAttribute('aria-live', 'polite'); document.body.appendChild(box); }
        var n = document.createElement('div');
        n.className = 'notice-pop' + (it.ok ? '' : ' bad');
        n.innerHTML = '<div class="np-body"><b>' + (it.ok ? 'Report ready' : 'Report failed') + '</b><div class="np-sub">' + esc(it.title) +
          (it.ok ? ' · ' + Number(it.rows).toLocaleString() + ' rows · ' + esc(it.format) : '') + '</div><div class="np-links">' +
          (it.ok && it.download ? '<a class="btn sm cyan" href="' + esc(it.download) + '">Download</a>' : '') +
          '<a class="btn sm ghost" href="' + esc(it.url) + '">View</a></div></div><button type="button" class="np-x" aria-label="Close">&times;</button>';
        $('.np-x', n).addEventListener('click', function () { n.remove(); });
        box.appendChild(n);
        setTimeout(function () { n.remove(); }, 15000);
      }
      function check() {
        fetch(um.dataset.notify, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            if (!d) return;
            var before = Number(badge.textContent) || 0;
            badge.textContent = d.count;
            badge.hidden = !d.count;
            badge.setAttribute('aria-label', d.count + ' unread ' + (d.count === 1 ? 'notification' : 'notifications'));
            if (d.count > before) { badge.classList.remove('bump'); void badge.offsetWidth; badge.classList.add('bump'); }
            var seen = notified(), fresh = d.items.filter(function (it) { return seen.indexOf(it.id) < 0; });
            fresh.slice(0, 3).forEach(popup);
            if (fresh.length) remember(seen.concat(fresh.map(function (it) { return it.id; })));
            // Check more often while something of yours is still running
            clearTimeout(check.t);
            check.t = setTimeout(check, d.running ? 5000 : 20000);
          })
          .catch(function () { clearTimeout(check.t); check.t = setTimeout(check, 60000); });
      }
      if (badge && um.dataset.notify) check();
    }

    // Account ticket: switch between "By Item Type" and "Timeline" (both already on the page); remembered per browser
    var tv = $('[data-ticket-views]');
    if (tv) {
      function showView(v) {
        $$('[data-view]', tv).forEach(function (b) { var on = b.dataset.view === v; b.classList.toggle('on', on); b.setAttribute('aria-selected', on ? 'true' : 'false'); });
        $$('[data-view-pane]').forEach(function (p) { p.hidden = p.dataset.viewPane !== v; });
        try { localStorage.setItem('ticket.view', v); } catch (e) {}
      }
      tv.addEventListener('click', function (e) { var b = e.target.closest('[data-view]'); if (b) showView(b.dataset.view); });
      var saved = null; try { saved = localStorage.getItem('ticket.view'); } catch (e) {}
      if (location.hash === '#timeline') saved = 'timeline';
      if (saved === 'timeline') showView('timeline');
    }

    // Long tables: <table data-page="10"> shows 10 rows at a time with a ‹ 1 2 3 › pager under it.
    // Rows marked data-attached (an edit row under its row) stay with the row above them.
    // Any other table with more than 10 rows pages the same way, unless it already has a server pager,
    // is an edit grid (inputs in its rows), a pick list, a live-filtered list, or says data-page="0".
    $$('.table-wrap > table:not([data-page])').forEach(function (table) {
      var wrap = table.parentElement;
      if (wrap.classList.contains('pick-list') || wrap.closest('.pick-list')) return;
      if (wrap.nextElementSibling && wrap.nextElementSibling.matches('nav.pager')) return;
      if (wrap.parentElement && wrap.parentElement.querySelector(':scope > nav.pager, :scope > .pager')) return;
      if (table.querySelector('tbody input:not([type=hidden]), tbody select, tbody textarea, [data-filter-row]')) return;
      table.dataset.page = '10';
    });
    $$('table[data-page]').forEach(function (table) {
      var size = +table.dataset.page, body = table.tBodies[0];
      if (!size || !body) return;
      var groups = [];
      Array.prototype.forEach.call(body.rows, function (tr) {
        if (tr.hasAttribute('data-attached') && groups.length) groups[groups.length - 1].push(tr); else groups.push([tr]);
      });
      if (groups.length <= size) return;
      var pages = Math.ceil(groups.length / size), nav = document.createElement('nav');
      nav.className = 'pager'; nav.setAttribute('aria-label', 'Pages');
      (table.closest('.table-wrap') || table).insertAdjacentElement('afterend', nav);
      function show(page) {
        groups.forEach(function (g, i) {
          var on = Math.floor(i / size) === page - 1;
          // The row itself shows on its page; an attached edit row only if it was opened
          g.forEach(function (tr, j) { tr.hidden = !on || (j > 0 && !tr.classList.contains('open')); });
        });
        var nums = [1, pages], h = '';
        for (var n = page - 2; n <= page + 2; n++) if (n > 1 && n < pages) nums.push(n);
        nums = nums.filter(function (v, i, a) { return a.indexOf(v) === i; }).sort(function (a, b) { return a - b; });
        h += '<button type="button" class="btn sm ghost" data-p="' + (page - 1) + '"' + (page === 1 ? ' disabled aria-disabled="true"' : '') + ' aria-label="Previous page">‹</button>';
        nums.forEach(function (n, i) {
          if (i && n - nums[i - 1] > 1) h += '<span class="muted">…</span>';
          h += n === page ? '<span class="btn sm on" aria-current="page">' + n + '</span>' : '<button type="button" class="btn sm ghost" data-p="' + n + '">' + n + '</button>';
        });
        h += '<button type="button" class="btn sm ghost" data-p="' + (page + 1) + '"' + (page === pages ? ' disabled aria-disabled="true"' : '') + ' aria-label="Next page">›</button>';
        var from = (page - 1) * size + 1, to = Math.min(page * size, groups.length);
        h += '<span class="muted pager-of">' + from + '–' + to + ' of ' + groups.length + '</span>';
        nav.innerHTML = h;
      }
      nav.addEventListener('click', function (e) { var b = e.target.closest('[data-p]'); if (b && !b.disabled) show(+b.dataset.p); });
      show(1);
    });

    // A button that opens (or closes) the edit row right under its row
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-toggle-row]');
      if (!b) return;
      var tr = b.closest('tr'), edit = tr.hasAttribute('data-attached') ? tr : tr.nextElementSibling, opener = edit && edit.previousElementSibling;
      if (!edit || !edit.hasAttribute('data-attached')) return;
      var open = edit.hidden;
      edit.hidden = !open; edit.classList.toggle('open', open);
      var toggle = opener && opener.querySelector('[data-toggle-row]');
      if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) { var f = edit.querySelector('input:not([type=hidden])'); if (f) f.focus(); } else if (toggle) toggle.focus();
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

    // Walker → Export Columns: click a model key to add it; move rows up and down; keep the order numbers in step
    var ec = $('#export-columns');
    if (ec) {
      var body = $('#export-rows', ec);
      function renumber() { $$('tr', body).forEach(function (tr, i) { var o = $('.ec-order', tr); if (o) o.value = i + 1; }); }
      ec.addEventListener('click', function (e) {
        var mv = e.target.closest('[data-ec-move]'), rm = e.target.closest('[data-ec-remove]'), add = e.target.closest('[data-ec-add]');
        if (mv) {
          var tr = mv.closest('tr');
          if (mv.dataset.ecMove === '-1' && tr.previousElementSibling) body.insertBefore(tr, tr.previousElementSibling);
          if (mv.dataset.ecMove === '1' && tr.nextElementSibling) body.insertBefore(tr.nextElementSibling, tr);
          renumber(); mv.focus();
        } else if (rm) {
          rm.closest('tr').remove(); renumber();
        } else if (add) {
          var key = add.dataset.ecAdd, last = key.split('.').pop();
          body.insertAdjacentHTML('beforeend', $('#export-row').innerHTML.replace(/__i__/g, 'n' + Date.now()));
          var row = body.lastElementChild, ins = $$('input', row);
          ins[1].value = key;
          ins[2].value = last.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
          renumber();
        }
      });
      $$('[data-clone="#export-row"]', ec).forEach(function (b) { b.addEventListener('click', function () { setTimeout(renumber); }); });
    }

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
