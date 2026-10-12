<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Support\StyleSheets;
use Illuminate\View\View;

/**
 * Lando → Style Guide: one place to see every color and style variable the
 * website and the admin apps use, the components built from them, and try
 * new values live before changing public/shared/css/tokens.css.
 */
class StyleController extends Controller
{
    /** [title, selectors whose rules count as this component, sample HTML] */
    private const ADMIN = [
        ['Buttons', ['.btn'], '<div class="actions"><button type="button" class="btn">Default</button><button type="button" class="btn cyan">Cyan</button><button type="button" class="btn ghost">Ghost</button><button type="button" class="btn red">Red</button><button type="button" class="btn sm">Small</button></div>'],
        ['Pills', ['.pill'], '<div class="actions"><span class="pill">Plain</span><span class="pill ok">Sent</span><span class="pill warn">Pending</span><span class="pill bad">Failed</span><span class="pill info">Info</span></div>'],
        ['Messages', ['.flash', '.banner-note', '.alert'], '<div style="display:grid;gap:8px"><div class="flash ok">Saved.</div><div class="flash bad">That email and password do not match.</div><div class="banner-note">Twilio isn\'t set up in .env, so texts are logged, not sent.</div><div class="alert info"><b>Heads up.</b> An info alert.</div><div class="alert bad"><b>Problem.</b> A bad alert.</div></div>'],
        ['Stat cards', ['.stat'], '<div class="stat-row" style="display:grid;grid-template-columns:1fr 1fr;gap:10px"><div class="stat"><span>On Flow</span><strong>1,204</strong><small>utility accepted</small></div><div class="stat alert"><span>Past Due</span><strong>37</strong><small>over 30 days</small></div></div>'],
        ['Panel & table', ['.panel', '.table', 'table', 'th', 'td', 'tbody'], '<div class="panel"><div class="panel-head"><h2>Recent Payments</h2><span class="muted">last 30 days</span></div><div class="table-wrap"><table class="table"><thead><tr><th>Account</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody><tr><td>1219000000</td><td class="num">$171.68</td><td><span class="pill ok">Success</span></td></tr><tr><td>1219000001</td><td class="num">$84.10</td><td><span class="pill warn">Pending</span></td></tr></tbody></table></div></div>'],
        ['Form fields', ['.form-grid'], '<div class="form-grid"><label for="sg-a">Name</label><input id="sg-a" value="Fall Renewal Reminder"><label for="sg-b">Channel</label><select id="sg-b"><option>Email</option><option>SMS</option></select></div>'],
        ['Tabs', ['.tabs'], '<div class="tabs"><button type="button" class="on">Account</button><button type="button">Billing</button><button type="button">History</button></div>'],
        ['Counts & badges', ['.count-pill', '.menu .count'], '<div class="actions" style="align-items:center">Notes<span class="count-pill">12</span><span class="muted">· the red sidebar badges use --red</span></div>'],
    ];

    private const SITE = [
        ['Buttons', ['.btn', '.btn-outline', '.btn-white'], '<div style="display:flex;gap:12px;flex-wrap:wrap"><a class="btn" href="#">Sign Up</a><a class="btn btn-outline" href="#">Plan &amp; Renewal</a></div><div class="section-navy" style="padding:16px;margin-top:12px;border-radius:8px"><a class="btn btn-white" href="#">On navy</a></div>'],
        ['Alerts', ['.site-alert', '.form-msg'], '<div class="site-alert">Your request was received.</div><div class="site-alert ok">Payment received. Thank you!</div><div class="site-alert error">That card was declined.</div>'],
        ['Plan card', ['.plan', '.checks'], '<div style="max-width:340px"><article class="plan featured"><span class="plan-flag">Popular Pick</span><div class="plan-top"><span class="plan-term">36 months</span><h3>Clean &amp; Green 36</h3><div class="plan-rate"><strong>13.9¢</strong><span>per kWh</span></div><p class="plan-rate-note">Avg. price at 1,000 kWh</p></div><div class="plan-body"><ul class="checks"><li>Fixed rate for 3 years</li><li>100% renewable</li></ul><a class="btn btn-block" href="#">Sign Up</a></div></article></div>'],
        ['Card & form', ['.co-card', '.field'], '<div class="co-card" style="max-width:420px"><div class="field"><label for="sg-z">ZIP Code</label><input id="sg-z" value="77002"></div><button class="btn" style="margin-top:16px">Check Plans</button></div>'],
        ['My Account menu', ['.ma-nav'], '<nav class="ma-nav" style="max-width:260px;position:static"><div class="ma-who"><b>Avery Sample</b><span>Account 1219000000</span></div><h4>Account</h4><a class="on" href="#">Dashboard</a><a href="#">View Bills</a></nav>'],
        ['Header Sign Out', ['.nav-out', '.nav .nav-out'], '<nav class="nav" style="justify-content:flex-start;flex-wrap:wrap"><a class="btn" href="#">My Account</a><form class="nav-out"><button type="button">Sign Out</button></form></nav>'],
        ['Navy section', ['.section-navy', '.eyebrow'], '<div class="section-navy" style="padding:28px;border-radius:8px"><p class="eyebrow">Rewards</p><h3 style="color:var(--white)">Earn stars every month</h3><p>Text on navy uses --white.</p></div>'],
    ];

    public function index(): View
    {
        $vars = StyleSheets::variables();
        $chips = fn (array $list, string $file) => array_map(fn ($c) => ['title' => $c[0], 'html' => $c[2],
            'vars' => array_values(array_filter(StyleSheets::varsFor($c[1], $file), fn ($n) => $vars->has($n)))], $list);

        return view('backend.lando.styles', [
            'vars' => $vars,
            'groups' => $vars->groupBy('group')->sortBy(fn ($g, $k) => array_search($k, ['Brand colors', 'Status colors', 'App colors', 'Fonts', 'Sizes & effects'])),
            'literals' => StyleSheets::literals(),
            'files' => StyleSheets::FILES,
            'admin' => $chips(self::ADMIN, 'backend/admin.css'),
            'site' => $chips(self::SITE, 'frontend/css/style.css'),
        ]);
    }
}
