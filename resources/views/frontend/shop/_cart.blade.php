{{-- Cart bar on Shop pages: shows what's in the cart with Cart and Checkout buttons. Every .shop-add button adds its data-sku (and the qty field named in data-qty). --}}
@php $count = app(\App\Services\Cart::class)->count(); @endphp
<div class="shop-cart" id="shop-cart" @if (! $count) hidden @endif role="status" aria-live="polite">
  <span class="shop-cart-msg" id="shop-cart-msg">Cart: <b id="shop-cart-n">{{ $count }}</b> {{ \Illuminate\Support\Str::plural('item', $count) }}</span>
  <a class="btn btn-outline" href="{{ route('shop.cart') }}">View Cart</a>
  <a class="btn" href="{{ route('myaccount.checkout') }}">Checkout</a>
</div>
<script>
(function () {
  var box = document.getElementById('shop-cart'), msg = document.getElementById('shop-cart-msg');
  var token = (document.querySelector('meta[name=csrf-token]') || {}).content;
  function setCount(n, text) {
    box.hidden = !n; msg.innerHTML = '';
    var b = document.createElement('b'); b.id = 'shop-cart-n'; b.textContent = n;
    msg.append(text ? text + ' · Cart: ' : 'Cart: ', b, ' ' + (n === 1 ? 'item' : 'items'));
    document.querySelectorAll('.cart-n').forEach(function (el) { el.textContent = n; el.hidden = !n; });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.shop-add'); if (!b || b.disabled) return;
    e.preventDefault();
    var q = Math.max(1, Math.min(10, parseInt((document.getElementById(b.dataset.qty || '') || {}).value || '1', 10) || 1));
    var label = b.textContent; b.disabled = true; b.textContent = 'Adding…';
    fetch('{{ route('shop.cart.add') }}', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ sku: b.dataset.sku, qty: q }) })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (j) { setCount(j.count, j.message); b.textContent = 'Added ✓'; })
      .catch(function () { b.textContent = 'Try again'; })
      .finally(function () { setTimeout(function () { b.disabled = false; b.textContent = label; }, 1200); });
  });
})();
</script>
