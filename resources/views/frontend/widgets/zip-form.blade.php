{{-- [[zip_form]]: the same zip lookup as the home page --}}
<form class="zip-card" data-zip-form novalidate>
  <h2>{{ $title ?? 'Find your plan' }}</h2>
  <p>Enter your zip code to see rates available at your address.</p>
  <div class="toggle-group" role="group" aria-label="Service type">
    <button type="button" class="on" data-type="home">Home</button>
    <button type="button" data-type="business">Business</button>
  </div>
  <div class="field">
    <label>Service Zip Code <input name="zip" inputmode="numeric" maxlength="5" placeholder="e.g. 77001" autocomplete="postal-code"></label>
  </div>
  <button class="btn btn-block" type="submit">View Plans</button>
  <div class="form-msg" role="status"></div>
</form>
