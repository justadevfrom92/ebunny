{{-- A small sample cart kept in this browser (the shop has no checkout yet). Any .shop-add button adds its data-sku (and data-qty). --}}
<div class="shop-cart" id="shop-cart" hidden role="status"><span>Cart: <b id="shop-cart-n">0</b></span><button type="button" class="link-btn" id="shop-cart-clear">Clear</button><small>Sample shop: checkout isn't open yet.</small></div>
<script>
(function () {
  var key = 'et-shop-cart', cart = []; try { cart = JSON.parse(localStorage.getItem(key)) || []; } catch (e) {}
  var box = document.getElementById('shop-cart'), n = document.getElementById('shop-cart-n');
  function show() { n.textContent = cart.length; box.hidden = !cart.length; try { localStorage.setItem(key, JSON.stringify(cart)); } catch (e) {} }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.shop-add'); if (!b) return;
    var q = Math.max(1, Math.min(10, parseInt((document.getElementById(b.dataset.qty || '') || {}).value || '1', 10) || 1));
    for (var i = 0; i < q; i++) cart.push(b.dataset.sku);
    show(); var t = b.textContent; b.textContent = 'Added ✓'; setTimeout(function () { b.textContent = t; }, 1200);
  });
  document.getElementById('shop-cart-clear').addEventListener('click', function () { cart = []; show(); });
  show();
})();
</script>
