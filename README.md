# Energy Texas — Website & Admin Tools

The public website (with sign-up and the My Account portal) plus the internal admin tools (Corral, Ranger, Lando, Astro, Walker, Caboose, Bounty, Rodeo, Deputy, Sheriff), served by **Laravel 13** on PHP 8.3.
No Node, no React and no build step: the admin is server-rendered Blade with plain CSS and a small plain-JavaScript file.

## Run it (Linux, macOS or Windows)

Requirements: PHP 8.3+ with `pdo_sqlite` (or `pdo_mysql`), and Composer.

```sh
composer install
php artisan start       # sets everything up, then serves http://127.0.0.1:8000
```

`php artisan start` does, in order:

1. creates `.env` from `.env.example` and an app key, if they're missing
2. creates the SQLite database file, if that's the connection and it doesn't exist yet
3. runs the migrations, and loads the sample data (`db:seed`) when the database is empty
4. creates the storage folders and the `public/storage` link, and clears caches
5. starts a queue worker (background reports, emails) and the scheduler (crons) in the background
6. runs `php artisan serve` — Ctrl+C stops the server, the worker and the scheduler

Options: `--fresh` wipes the database and reloads the sample data, `--port=8080` / `--host=0.0.0.0` change where it
serves, `--no-serve` only sets things up, `--no-worker` skips the queue worker and scheduler.

Click **Admin** (bottom right of the website footer, or go to `/admin`). It asks you to sign in, then opens the launcher in a new tab.

Demo users (all fictional; password is `ADMIN_DEMO_PASSWORD`, default `password`):

| Email | Role | Apps |
| --- | --- | --- |
| admin@example.com | Administrator | everything, including refunds |
| csr@example.com | Customer Service | Corral, Bounty |
| pricing@example.com | Pricing | Lando, Astro |
| marketing@example.com | Content Editor | Lando, Rodeo |
| finance@example.com | Finance | Corral, Walker, Caboose, refunds |
| paige@example.com | Content Editor (+ Corral, Walker, Astro) | Corral, Lando, Astro, Walker, Rodeo |

Website customer login (My Account, at `/myaccount`): username `demo.customer`, password `password` (fictional account 1219000000).

Other commands: `php artisan test` (feature tests), `php artisan start --fresh` (reset the sample data and start).

### Demo site (GitHub Pages)

`php artisan demo:export` writes the pages listed in `config/demo.php` to `build/demo` as a static site with the sample data
and a placeholder brand. Links between those pages work; links to any page that isn't listed are switched off, and forms
don't save anything. Add or remove pages in `config/demo.php`, or add one-offs with `--page=/admin/ranger/tickets/12`.
Open `build/demo/index.html` to check it, then publish the folder anywhere static. To publish it on GitHub Pages: Settings →
Pages → Source: GitHub Actions, then Actions → **Demo site** → Run workflow (it sets up the sample data, exports and deploys).

### Production notes
- Point the web server's document root at `public/` (Apache or Nginx + PHP-FPM, the usual Laravel setup).
- Use MySQL by setting `DB_CONNECTION=mysql` and the `DB_*` values in `.env`, then `php artisan migrate --force`.
- Scheduled jobs need one cron entry: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
- Every report (Walker and Corral) runs in the background and needs a queue worker (`php artisan start` runs one; in production use a systemd service or Supervisor running `php artisan queue:work --tries=1`). Without it they stay "Queued" and show as "Did not finish" after an hour. When one finishes, the number by your name (top right) goes up and a "Report ready" popup appears; the files are under your name → My Files.
- Set `APP_ENV=production`, `APP_DEBUG=false`, change or remove the demo users, and run `php artisan config:cache route:cache view:cache`.

## How it fits together

```
public/
  frontend/                the website: built-in pages (index.html, plans.html …), css/style.css, js/main.js
  backend/                 the admin portal's admin.css and admin.js
  shared/boot.js           one <script> per page; loads brand.js, catalog.js and core.js
  shared/css/tokens.css    brand, text and status colors and fonts for the website AND the admin (see Lando → Style Guide)
config/brand.php           company name, phone, PUCT number, website nav  → /shared/config/brand.js
config/admin.php           every admin app: name, icon, menu, queues, reference tables, jobs, integrations
app/Services/Catalog.php   current rates/fees and the EFL average price  → /shared/config/catalog.js
app/Http/Controllers/
  Frontend/                the website and My Account (what customers see)
    SiteController.php       website data, session/CSRF token, cookie choices, Contact Us form
    SignupController.php, ShopController.php, SurveyController.php, SitePageController.php
    MyAccount/               the customer portal (sign-in, pages, chat bubble)
  Backend/                 the admin portal (what employees see)
    {Corral,Lando,Astro,Walker,Caboose,Bounty,Rodeo,Deputy,Sheriff}/   one folder of controllers per app
app/Reports/               report definitions shared by Walker and Corral (config/walker.php lists them)
resources/views/
  frontend/                website pages Laravel builds: myaccount/, checkout/, shop, surveys, CMS pages, layouts/main
  backend/                 admin portal pages
    layouts/app.blade.php    the one layout every app uses (sidebar, switcher, menu from config/admin.php)
    {corral,lando,astro,walker,caboose,bounty,rodeo,deputy,sheriff}/   each app's screens
  components/frontend/     My Account frame, chat bubble, sign-up steps (<x-frontend.*>)
  components/              shared by both: bar chart and its hover tooltip
app/Console/Commands/      scheduled jobs (et:*), each run logged for Sheriff → Crons
database/migrations, seeders/data/*.json   schema and fictional sample data
```

**Configure, don't copy.** Apps share one layout, and their menus, queues, reference tables, jobs and integrations are entries in `config/admin.php`. Adding a menu item or a whole app is a config change plus its controller and views. Brand values live in `config/brand.php` and colors in `public/shared/css/tokens.css`, used by both the website and the admin.

**The website reads live data.** `/shared/config/catalog.js` is generated from the database on each request, so a rate saved in Lando, a plan's bullets, a hidden product in Astro or a changed ETF shows on the website on the next page load. Rates and TDSP fees keep history: saving adds rows with an effective date, and the website switches on that date.

## Admin apps
| App | What it does |
| --- | --- |
| **Corral** | Customer search (status/exception filters, bookmarks), account detail with billing, payments (reversal needs the *refunds* right), notes; ESIID lookup; phone orders (resi & biz) and renew/change plan; orders report (screen, summary, CSV); exception queues; website Contact Us messages |
| **Lando** | **Style Guide** (every color and style variable from the shared, admin and website stylesheets, where each is used, sample components, hard-coded colors, live color pickers that export the changed CSS); page tree and editor (delete needs the *delete* right), sitemap rebuild, templates, content blocks with live preview, plans (incl. website bullets and filters), plan groups, rate search with EFL average price, bulk rate editor with effective dates, TDSP fees, markets |
| **Astro** | Term discounts by region, ETF by term, Build Your Own Plan products (price and whether the website shows them) |
| **Ranger** | Support tickets customers open from My Account → Support Tickets: the queue (urgent first, unassigned, mine, joined), each ticket's conversation with replies (the customer sees them) and internal notes (they don't), status and priority; managers can **Take Over** or **Join In** and assign tickets; the **Ticket Team** (in-office and 3rd-party people, add someone already in the admin or a new person); **Approval Ratings** (👍 out of rated tickets) for in-office vs 3rd party, week by week and per person |
| **Walker** | Files & reporting: report runs that are running, completed, or errored / did not finish; each run has its own page (file details, settings, first rows, download) with **Rerun** top right; report library with export format (Excel, CSV, tab-separated, JSON) and **Export Columns** (model key → column title, in order, or a nested key array); file folders (ETL, Legal Documents, Customer Templates, Finance, Utility / ERCOT, Vendor); ETL feeds with every run |
| **Caboose** | Finance: dashboard, payments (record check/money order/cash/wire, reverse), pending credits & debits to apply, refunds (request → approve → paid; deciding needs the *refunds* right), deposits held and due, receivables aging, journal export (CSV/XLSX) |
| **Bounty** | Rewards: reward offers, star earning rules (used by the `et:rewards-stars` job), redemptions to fulfil, member star balances and adjustments, monthly drawing |
| **Rodeo** | Marketing: email/SMS campaigns with an audience builder; email templates with placeholders, each with its own **Send Test** page (just you, typed addresses, account numbers, your Corral bookmarks, an admin team or a customer category; check recipients, live desktop/phone preview, subject prefix and note, test history with Send Again; up to 25 people). **Surveys** section: all surveys with status and NPS, a builder (questions filed by category, answers from reusable answer sets or your own, required, help text, open/close dates, thank-you message), results grouped by category (NPS, scale averages, answer breakdowns, written answers, CSV export), every response, the Question Bank, Answer Sets and Categories. MSIDs and promo codes |
| **Sheriff** | Staff directory (profiles, access, log in as), role permissions, **Timeline** of everything that ran (cron jobs, ETL feeds, automations, reports, API calls, what people did; searchable, per person), API integrations with Test Connection (one or all), scheduled jobs with Run Now and history, reference data tables |

## Website sign-up, My Account and surveys
- **Sign up** (`/checkout`): address → plan → about you → review → submit, with save-and-resume and a deposit step. Orders land in Corral like phone orders.
- **My Account** (`/myaccount`): dashboard, bills and payments, usage insights, pay a bill, payment methods, products, profile and password, authorized users, linked accounts, renew/change plan, transfer service, rewards, refer a friend, messages. QuickPay at `/myaccount/quickpay` needs no sign-in.
- **Surveys** (`/survey/<slug>`): built in Rodeo → Surveys; questions show under their category headings. Put `{{survey:<slug>}}` in an email template; each customer gets a signed link so their answer is tied to their account.

Test emails go out through Laravel's mailer: set `MAIL_MAILER` (e.g. `smtp`) and the `MAIL_*` values in `.env`; with the default `log` they are written to `storage/logs` instead of delivered. Card payments, campaign emails and texts need Stripe, Salesforce and Twilio keys in `.env`; until then payments are recorded as Pending and campaign messages are logged but not sent.

## Adding an admin app
On the launcher, **+ New Admin App** (roles with Sheriff, e.g. Administrator) creates a new app: name, description, icon, which roles can open it, and its sidebar links. Each link points to any existing admin screen or to a URL, grouped under headings. The app gets a tile on the launcher, opens at `/admin/<address>` in the shared admin layout, and appears as a column in Sheriff → Roles. Edit or delete it from its **Edit App** button. These apps are stored in the `admin_apps` table; the built-in apps stay in `config/admin.php`.

## Original Corral item pages
Every item model from the original Corral has its own page at `/admin/corral/data/item/{id}/{Model}` (49 models, e.g. `ItemEmailSalesforce_model`, `ItemPayment_model`, `ItemProductGiddyup_model`, `ItemErcot81405_model`): its fields in the original order, its own buttons (Edit, Resend, Check Status, Duplicate, Mark as Deposit, Regenerate, Check Current Account, Link to All Accounts, Recalculate, Regen Welcome Packet, Delete), Process Logs, and Parent Tickets. The account ticket and each Logs ticket list their items under Child Items.

- `config/items.php` lists each model's fields, buttons and parent tickets (generated from the original pages' field names; no customer data).
- `App\Support\Items` reads each model from the app's own records where they exist (payments, notes, bills, emails, EDI transactions, products, payment methods, files, addresses, the account) and from `data_items` for the rest (attributes, content viewed, devices, reviews, forecast models, usage buckets). Original fields the app doesn't track are kept on a `data_items` row tied to the record.
- Sensitive fields (SSN, driver's license, card number, CVV, tokens, password hashes, reset codes) are always masked and can't be edited. Delete needs the *delete* right.
- Buttons that depend on an outside service (SalesForce, Utilibill, Amazon) say so and log the request when the service isn't configured.

## History
Every change to accounts, payments, bills, notes, queues, plans, rates, fees, pricing, pages, blocks, users and roles is recorded automatically in `history_items`: who, when, the record's model name (the original system's naming, e.g. `ItemPayment_model`), each field's old → new value and a snapshot of the record. Sign-ins, plan-group edits, reference-table saves and job runs are recorded too. Passwords are never stored in history.

- **Corral → account → History:** counts per model grouped like the old *Logs* pages (Products, Payments, EDI Transactions, Emails…), the full timeline (filter by clicking a model), Usage History by month and Rewards History.
- **Each entry** has a detail page: its fields, changes, parent account and other entries of the same model (*Process Logs*).
- **Sheriff → Users → History:** everything one admin user did.

Which models are tracked, their model names and groups are set in `config/history.php`.

## Security
- Passwords are hashed; sign-in is rate limited; all forms are CSRF protected; app access and the *delete*/*refunds* rights are checked on the server.
- API keys go in `.env` only. Sheriff → APIs shows whether each is set, never the value.
- Jobs that need an outside service (Utilibill, Stripe, Amazon) report **Skipped** until their keys are set, and **Failed** with an explanation once keys are set, because those API clients still have to be written.

## Placeholders to replace before going live
- Rates and TDSP fees in the seed data are illustrative.
- `BRAND_PHONE` and `BRAND_PUCT` in `.env`.
- Every customer and user in the seed data is fictional.
