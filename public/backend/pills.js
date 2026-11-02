/*
  Rows of more than 3 pills become dropdowns (radios → a select, checkboxes → a pick-several list,
  links → a select that goes to the link). The admin layout runs this inline right after the page
  content, before the page paints, so the dropdowns are there from the start (no pills flashing,
  nothing hidden while a deferred script loads). admin.js calls it again for content it swaps in.
*/
(function () {
  'use strict';
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

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
  var main = document.querySelector('.content');
  if (main) { pillsToSelects(main); document.documentElement.classList.add('pills-done'); }
})();
