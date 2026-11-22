"""
Preview builder: crawls the running app (php artisan serve on 127.0.0.1:8127) as the sample admins and
customers, and writes one self-contained HTML file (the shell from the last published preview, with new
data: pages gzip+base64, shared assets, bill PDFs and the test sign-ins).
Usage (from the repo root, with `php artisan serve --port=8127` running on fresh seed data):
    php artisan migrate:fresh --seed && php artisan cache:clear
    python3 tools/preview/build.py [out.html]      # default: storage/app/preview/site-preview.html
"""
import base64, collections, gzip, html, http.cookiejar, json, os, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

B = 'http://127.0.0.1:8127'
O = 'http://localhost:8127'      # the origin the preview shell knows
LIMIT = 16_200_000               # stay under the 16 MB artifact limit
HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))   # the repo root

# Placeholder brand for the preview (the app's real brand stays in .env)
BRAND = [('ENERGY TEXAS', 'SAMPLE ENERGY'), ('Energy Texas', 'Sample Energy'), ('energytexas.com', 'sampleenergy.example'),
         ('POWERED BY TEXANS', 'ELECTRICITY PLANS'), ('Rangler Rewards', 'Power Rewards')]


def brand(s):
    for a, b in BRAND:
        s = s.replace(a, b)
    return s


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())

    def get(self, path, hops=5):
        url = B + path
        for _ in range(hops):
            try:
                r = self.op.open(urllib.request.Request(url, headers={'Accept': 'text/html'}), timeout=60)
                return r.status, r.headers.get('Content-Type', ''), r.read(), url[len(B):]
            except urllib.error.HTTPError as e:
                if e.code in (301, 302, 303) and e.headers.get('Location'):
                    url = urllib.parse.urljoin(url, e.headers['Location'])
                    if not url.startswith(B):
                        return e.code, '', b'', url
                    continue
                return e.code, e.headers.get('Content-Type', ''), e.read(), url[len(B):]
        return 0, '', b'', url

    def post(self, path, data):
        token = self.token(path.rsplit('/test/', 1)[0])
        body = urllib.parse.urlencode(dict(data, _token=token)).encode()
        try:
            self.op.open(urllib.request.Request(B + path, data=body), timeout=60)
        except urllib.error.HTTPError as e:
            if e.code not in (301, 302, 303):
                raise

    def token(self, page):
        _, _, b, _ = self.get(page)
        m = re.search(rb'name="csrf-token" content="([^"]+)"', b) or re.search(rb'name="_token" value="([^"]+)"', b)
        return m.group(1).decode() if m else ''


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def tinker(php):
    out = subprocess.run(['php', 'artisan', 'tinker', '--execute', php], cwd=ROOT, capture_output=True, text=True).stdout
    return json.loads(out.strip().splitlines()[-1])


# ---------- who can sign in (the shell's two dropdowns) ----------
people = tinker(r"""
$roles = App\Models\User::with('role')->where('active', true)->orderBy('id')->get()->filter(fn ($u) => $u->role)->unique(fn ($u) => $u->role->name.($u->company ?? ''))->take(11)->values()
  ->map(fn ($u) => array_filter(['id' => (string) $u->id, 'initials' => collect(explode(' ', $u->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''), 'name' => $u->name, 'role' => $u->role->name,
     'apps' => collect(array_keys(config('admin.apps')))->filter(fn ($a) => $u->hasPerm($a))->values()->all(),
     'agent' => App\Models\RangerAgent::where('user_id', $u->id)->where('level', 'agent')->exists() ?: null], fn ($v) => $v !== null));
$cust = App\Models\Customer::orderBy('id')->get()->unique('status')->take(6)->values()->map(fn ($c) => ['id' => $c->account, 'name' => $c->name,
     'detail' => $c->account.' · '.$c->type.' · '.$c->status.' · $'.number_format(abs((float) $c->balance), 2).((float) $c->balance < 0 ? ' credit' : ' due')]);
echo json_encode(['roles' => $roles, 'customers' => $cust]);
""")
# Friends & Family accounts, the actions a Lead approves on them, and the sample Lead Approvals
FF = tinker(r"""echo json_encode(['accounts' => App\Models\Customer::whereHas('flags', fn ($q) => $q->where('flag', 'VIP - Friends & Family'))->orderBy('id')->limit(2)->pluck('account'),
  'actions' => config('corral.flag_locks.VIP - Friends & Family.actions'), 'approvals' => App\Models\LeadApproval::orderBy('id')->pluck('id'), 'lead_roles' => config('corral.lead_roles')]);""")
order_page = tinker(r"""echo json_encode('/myaccount/orders/'.(App\Models\ShopOrder::whereHas('customer', fn ($q) => $q->where('account', '1219000000'))->value('number') ?? App\Models\ShopOrder::value('number')));""")

# ---------- crawl ----------
pages = {}          # key -> html
order = []          # keys in the order found (earlier = kept first when trimming)
pdfs = {}
json_ends = {}
SKIP = re.compile(r'/(logout|download|export|pdf|file|view|csv|template)(/|$|\?)|\.(csv|xlsx|txt|json|zip|png|jpe?g|svg|pdf)$|/admin/notifications$|/admin/my/notifications/\d+$|/attachment|/again$|/run$|/history/export')
CAPS = collections.Counter()


def pattern(k):
    p, _, q = k.partition('?')
    p = re.sub(r'/(\d+|[A-Z]{2}-\d+|RT-\d+|SO-\d+)(?=/|$)', '/N', p)
    p = re.sub(r'/1219\d{6}', '/ACCT', p)
    return p + ('?' + '&'.join(sorted(x.split('=')[0] for x in q.split('&'))) if q else '')


def cap_for(pat):
    if '?' in pat:
        return 3
    return 6 if '/N' in pat or '/ACCT' in pat else 50


def links(htm):
    out = []
    for m in re.finditer(r'(?:href|data-href)="([^"#][^"]*)"', htm):
        u = html.unescape(m.group(1))
        if u.startswith(B):
            u = u[len(B):]
        elif u.startswith('http') or u.startswith('mailto:') or u.startswith('tel:') or u.startswith('javascript'):
            continue
        u = u.split('#')[0]
        if u.startswith('/'):
            out.append(u)
    return out


def crawl(client, seeds, prefix, budget, as_suffix='', key_ok=None):
    queue = collections.deque(seeds)
    seen = set()
    n = 0
    while queue and n < budget:
        path = queue.popleft()
        if path in seen or SKIP.search(path.split('?')[0]):
            continue
        seen.add(path)
        key = path + (('&' if '?' in path else '?') + as_suffix if as_suffix else '')
        if key in pages:
            continue
        pat = pattern(path)
        if path not in seeds and CAPS[prefix + pat] >= cap_for(pat):
            continue
        status, ctype, body, final = client.get(path)
        if status != 200 or 'text/html' not in ctype:
            continue
        if final.split('?')[0] != path.split('?')[0] and path not in seeds:
            continue
        CAPS[prefix + pat] += 1
        htm = body.decode('utf-8', 'replace')
        pages[key] = htm
        order.append(key)
        n += 1
        if key_ok is None or key_ok(path):
            for l in links(htm):
                if l.startswith(prefix) and l not in seen:
                    queue.append(l)
        for l in re.findall(r'href="(?:' + re.escape(B) + r')?(/myaccount/[^"]*?/pdf)"', htm):
            if l not in pdfs:
                st, ct, b, _ = client.get(l)
                if st == 200 and b[:4] == b'%PDF':
                    pdfs[l] = base64.b64encode(b).decode()
    return n


def admin_login(c, uid):
    try:
        c.post(f'/admin/login/test/{uid}', {})
        return True
    except urllib.error.HTTPError as e:
        print('admin sign-in failed', uid, e.code, file=sys.stderr)
        return False


def cust_login(c, acct):
    try:
        c.post(f'/myaccount/login/test/{acct}', {})
        return True
    except urllib.error.HTTPError as e:
        print('customer sign-in failed', acct, e.code, file=sys.stderr)
        return False


# Website (signed out)
web = Client()
site_seeds = ['/index.html', '/plans.html', '/build-your-own-plan.html', '/business.html', '/shop', '/contact-us.html', '/404', '/admin/login', '/myaccount/login']
crawl(web, site_seeds, '/', 160, key_ok=lambda p: not p.startswith('/admin') and not p.startswith('/myaccount'))

# Admin: everything as the administrator, menu pages first
admin = Client()
admin_login(admin, people['roles'][0]['id'])
menu = []
_, _, b, _ = admin.get('/admin')
for app in dict.fromkeys(re.findall(r'href="' + re.escape(B) + r'(/admin/[a-z]+)"', b.decode())):
    _, _, ab, _ = admin.get(app)
    nav = re.search(r'<nav class="menu"[\s\S]*?</nav>', ab.decode())
    if nav:
        menu += [m[len(B):] for m in re.findall(r'href="(' + re.escape(B) + r'/admin[^"]*)"', nav.group(0))]
seeds = ['/admin', '/admin/files', '/admin/my/notifications', '/admin/my/settings'] + list(dict.fromkeys(html.unescape(m) for m in menu))
crawl(admin, seeds, '/admin', 2600)
st, ct, b, _ = admin.get('/admin/notifications')
if st == 200:
    json_ends['/admin/notifications'] = b.decode()
pages['/admin?as=' + people['roles'][0]['id']] = pages.get('/admin', '')

# Each sample admin: their launcher (and an agent's own Ranger lists)
for r in people['roles'][1:]:
    c = Client()
    if not admin_login(c, r['id']):
        r['skip'] = True
        continue
    # Friends & Family accounts look different to someone who isn't a Lead (Request Lead Approval), and Lead
    # Approvals shows each person's own view: keep those pages as this person sees them
    extra = []
    if 'corral' in r.get('apps', []):
        extra = ['/admin/corral/lead-approvals', '/admin/corral/lead-approvals?status=approved', '/admin/corral/lead-approvals?status=denied']
        extra += ['/admin/corral/lead-approvals/' + str(i) for i in FF['approvals']]
        for acct in FF['accounts']:
            extra.append('/admin/corral/customers/' + acct)
            if r['role'] not in FF['lead_roles']:   # a Lead just runs them, like on any account
                extra += ['/admin/corral/customers/' + acct + '/actions/' + a for a in FF['actions']]
    for p in ['/admin'] + (['/admin/ranger', '/admin/ranger/tickets'] if r.get('agent') else []) + extra:
        st, ct, b, _ = c.get(p)
        if st == 200 and 'text/html' in ct:
            k = p + '?as=' + r['id']
            pages[k] = b.decode('utf-8', 'replace')
            order.insert(0, k)

# My Account: the first customer fully, the others' main pages
for i, cu in enumerate(people['customers']):
    c = Client()
    if not cust_login(c, cu['id']):
        cu['skip'] = True
        continue
    if i == 0:
        crawl(c, ['/myaccount/dashboard', '/myaccount'], '/myaccount', 140)
        st, ct, b, _ = c.get(order_page)
        if st == 200:
            pages[order_page] = b.decode('utf-8', 'replace')
            order.append(order_page)
    for p in ['/myaccount/dashboard', '/myaccount/rewards', '/myaccount/products', '/myaccount/profile-and-preferences', '/myaccount/bill-and-payments/pay-bill',
              '/myaccount/bill-and-payments/view-bills', '/myaccount/plan-and-services/current-plan']:
        st, ct, b, _ = c.get(p)
        if st == 200 and 'text/html' in ct:
            k = p + '?as=' + cu['id']
            pages[k] = b.decode('utf-8', 'replace')
            order.insert(0, k)

people['roles'] = [r for r in people['roles'] if not r.get('skip')]
people['customers'] = [c for c in people['customers'] if not c.get('skip')]
print('crawled', len(pages), 'pages,', len(pdfs), 'PDFs', file=sys.stderr)

# ---------- shared assets (one copy each) ----------
def asset(path):
    st, ct, b, _ = web.get(path)
    return b.decode('utf-8', 'replace') if st == 200 else ''


IMG = {}


def data_uri(path):
    if path in IMG:
        return IMG[path]
    f = os.path.join(ROOT, 'public', path.lstrip('/').split('?')[0])
    if not os.path.isfile(f) or os.path.getsize(f) > 120_000:
        IMG[path] = None
        return None
    ext = f.rsplit('.', 1)[-1].lower()
    mime = {'svg': 'image/svg+xml', 'png': 'image/png', 'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'webp': 'image/webp', 'gif': 'image/gif', 'ico': 'image/x-icon'}.get(ext)
    if not mime:
        IMG[path] = None
        return None
    raw = open(f, 'rb').read()
    IMG[path] = f'data:{mime};base64,' + base64.b64encode(raw).decode()
    return IMG[path]


def inline_images(s):
    def rep(m):
        u = m.group(2)
        d = data_uri(u) if u.startswith('/') else None
        return m.group(1) + (d or u) + m.group(3)
    s = re.sub(r'(src=")(/[^"]+\.(?:svg|png|jpe?g|webp|gif))(")', rep, s)
    s = re.sub(r'(url\([\'"]?)(/[^)\'"]+\.(?:svg|png|jpe?g|webp|gif))([\'"]?\))', rep, s)
    return s


def css(path):
    """A stylesheet with its local @imports inlined (remote ones, like Google Fonts, moved to the top)."""
    text = asset(path)
    remote = []

    def rep(m):
        url = m.group(1)
        if url.startswith('http'):
            remote.append(m.group(0))
            return ''
        sub = urllib.parse.urljoin(path, url)
        inner = css(sub)
        found = re.findall(r'@import url\([\'"]?https?:[^)]*\)[^;]*;', inner)
        remote.extend(found)
        for f in found:
            inner = inner.replace(f, '')
        return inner
    text = re.sub(r'@import url\([\'"]?([^)\'"]+)[\'"]?\)[^;]*;', rep, text)
    return ''.join(dict.fromkeys(remote)) + '\n' + text


A = {
    '@@SHIM@@': open(os.path.join(HERE, 'shim.txt')).read(),
    '@@ADMIN_CSS@@': inline_images(css('/backend/admin.css')),
    '@@ADMIN_JS@@': asset('/backend/admin.js'),
    '@@DP_JS@@': asset('/shared/datepicker.js'),
    '@@PWT_JS@@': asset('/shared/password-toggle.js'),
    '@@SITE_CSS@@': inline_images(css('/frontend/css/style.css')),
    '@@SITE_JS@@': asset('/shared/config/brand.js') + '\n' + asset('/shared/config/catalog.js') + '\n' + asset('/shared/js/core.js') + '\n' + asset('/frontend/js/main.js'),
    '@@COMPONENTS@@': json.load(open(os.path.join(HERE, 'components.json')))['@@COMPONENTS@@'],
    '@@PVJ@@': json.dumps({k: v.replace(B, O) for k, v in json_ends.items()}),
    '@@ORDERPAGE@@': json.dumps(order_page),
}
A = {k: brand(v.replace(B, O)) for k, v in A.items()}


def prep(htm):
    s = htm.replace(B, O)
    s = s.replace('<link rel="stylesheet" href="/backend/admin.css">', '<style>@@ADMIN_CSS@@</style>')
    s = s.replace('<script src="/backend/admin.js" defer></script>', '@@SHIM@@<script>@@ADMIN_JS@@</script>')
    s = s.replace('<script src="/shared/datepicker.js" defer></script>', '<script>@@DP_JS@@</script>')
    s = s.replace('<script src="/shared/password-toggle.js" defer></script>', '<script>@@PWT_JS@@</script>')
    s = s.replace('<link rel="stylesheet" href="/frontend/css/style.css">', '<style>@@SITE_CSS@@</style>')
    s = re.sub(r'<script src="/shared/boot\.js" data-then="[^"]*"></script>', '@@SHIM@@<script>@@SITE_JS@@</script>', s)
    if '@@SHIM@@' not in s:
        s = s.replace('<head>', '<head>@@SHIM@@', 1)
    s = inline_images(s)
    return brand(s)


def pack(s):
    return base64.b64encode(gzip.compress(s.encode(), 9, mtime=0)).decode()


P = {}
for k in list(dict.fromkeys([k for k in pages if '?as=' in k] + order + list(pages))):
    if pages.get(k):
        P[k] = pack(prep(pages[k]))

shell = open(os.path.join(HERE, 'shell.html')).read().split('\n')
i = shell.index('@@DATA@@')
head, tail = shell[:i], shell[i + 1:]
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(ROOT, 'storage', 'app', 'preview', 'site-preview.html')
os.makedirs(os.path.dirname(os.path.abspath(OUT)), exist_ok=True)


def render(P, pdfs):
    data = 'var SIDE={}, PDFS=%s, PEOPLE=%s, P=%s, A=%s, role=null, cust=null;' % (json.dumps(pdfs), json.dumps(people, ensure_ascii=False), json.dumps(P), json.dumps(A, ensure_ascii=False))
    return '\n'.join(head + [data.replace('</', '<\\/')] + tail)   # "</script>" inside the data must not end the script tag


out = render(P, pdfs)
# Too big: keep the first-found pages (menus and their first records), drop the deepest ones
keys = list(P)
while len(out.encode()) > LIMIT:
    over = len(out.encode()) - LIMIT
    drop = 0
    while drop < over and keys:
        k = keys.pop()
        if '?as=' in k or k in seeds or k in site_seeds:
            keys.insert(0, k)
            if all(('?as=' in x or x in seeds or x in site_seeds) for x in keys):
                break
            continue
        drop += len(P.pop(k)) + len(k) + 6
    if len(pdfs) > 4:
        for k in list(pdfs)[4:]:
            pdfs.pop(k)
    out = render(P, pdfs)
open(OUT, 'w').write(out)
print('pages', len(P), 'bytes', len(out.encode()), file=sys.stderr)
