@once
{{-- Instant tooltip for anything with data-tip (bars in every chart). Native title tooltips take a second or two to appear. --}}
<style>
.chart-tip { position: fixed; z-index: 9999; pointer-events: none; background: #102247; color: #fff; font: 600 12px/1.3 system-ui, sans-serif; padding: 6px 9px; border-radius: 6px; box-shadow: 0 4px 14px rgba(0,0,0,.25); white-space: nowrap; transform: translate(-50%, calc(-100% - 8px)); }
.chart-tip[hidden] { display: none; }
</style>
<script>
(function () {
  if (window.__chartTip) return; window.__chartTip = true;
  var tip = document.createElement('div'); tip.className = 'chart-tip'; tip.hidden = true; tip.setAttribute('role', 'tooltip');
  function show(el) {
    if (!tip.isConnected) document.body.appendChild(tip);
    tip.textContent = el.getAttribute('data-tip'); tip.hidden = false;
    var r = el.getBoundingClientRect();
    tip.style.left = Math.min(Math.max(r.left + r.width / 2, 60), window.innerWidth - 60) + 'px';
    tip.style.top = Math.max(r.top, 36) + 'px';
  }
  function hide() { tip.hidden = true; }
  document.addEventListener('pointerover', function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); el ? show(el) : hide(); });
  document.addEventListener('focusin', function (e) { var el = e.target.closest && e.target.closest('[data-tip]'); if (el) show(el); });
  document.addEventListener('focusout', hide);
  window.addEventListener('scroll', hide, true);
})();
</script>
@endonce
