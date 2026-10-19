<?php

/*
|--------------------------------------------------------------------------
| Admin tools configuration
|--------------------------------------------------------------------------
| One place that describes every admin app. The layout, launcher, app switcher
| and permission checks all read from here, so adding a menu item or a whole
| app is a config change, not a copy of another app's files.
|
| Menu items: [label, route name, optional badge key]
*/

$integration = fn (string $name, string $purpose, array $env) => [
    'name' => $name,
    'purpose' => $purpose,
    // Values are read once here (so `php artisan config:cache` works) and are
    // only ever checked for presence — the admin never displays them.
    'env' => collect($env)->mapWithKeys(fn ($key) => [$key => env($key)])->all(),
];

return [

    'apps' => [
        'corral' => [
            'name' => 'Corral',
            'desc' => 'Customer service and CRM: search accounts, create orders, work exception queues, and the sales pipeline of leads, follow-ups and partners.',
            'icon' => 'users',
            'home' => 'corral.customers.index',
            // Same order as the original Corral menu. Unnamed (numbered) groups have no heading.
            'menu' => [
                0 => [
                    ['Customer Search', 'corral.customers.index'],
                    ['ESIID Lookup', 'corral.esiid'],
                    ['Create Order', 'corral.orders.create'],
                    ['Create Order - Biz', 'corral.orders.create-biz'],
                    ['SMS', 'corral.sms', 'sms'],
                    ['Phone Calls', 'corral.calls'],
                ],
                'CRM' => [
                    ['Pipeline', 'corral.crm.pipeline'],
                    ['Leads', 'corral.crm.leads'],
                    ['Follow-ups', 'corral.crm.tasks', 'crm_due'],
                    ['Partners & Brokers', 'corral.crm.partners'],
                ],
                'Reports' => [
                    ['Orders', 'corral.reports.orders'],
                    ['Notes', 'corral.reports.notes'],
                    ['Phonecalls', 'corral.reports.phonecalls'],
                ],
                'Website' => [
                    ['Web Messages', 'corral.messages.index', 'messages'], // contact form inbox (not in the original menu)
                ],
                1 => [
                    ['ERCOT', 'corral.ercot'],
                ],
                'Exceptions' => 'queues', // one menu item per queue below, plus the overview
            ],
        ],
        'lando' => [
            'name' => 'Lando',
            'desc' => 'Website CMS: site health and visitors, pages, page templates and content blocks.',
            'icon' => 'layout',
            'home' => 'lando.dashboard',
            'menu' => [
                'Site' => [
                    ['Dashboard', 'lando.dashboard'],
                    ['Site Health', 'lando.health'],
                    ['Visitors', 'lando.visitors'],
                    ['Blocked Areas', 'lando.blocked.areas'],
                    ['Blocked IPs', 'lando.blocked.ips'],
                    ['Cookie Consents', 'lando.consents'],
                ],
                'Pages' => [
                    ['List Pages', 'lando.pages.index'],
                    ['Add a Page', 'lando.pages.create'],
                    ['Page Templates', 'lando.templates.index'],
                ],
                'Content' => [
                    ['Content Blocks', 'lando.blocks.index'],
                    ['Add a Content Block', 'lando.blocks.create'],
                ],
                'Design' => [
                    ['Style Guide', 'lando.styles'],
                ],
                'Data' => 'reference_tables',
            ],
        ],
        'astro' => [
            'name' => 'Astro',
            'desc' => 'Plans and pricing: plans, plan groups, rates, TDSP fees, term discounts, ETFs and Build Your Own Plan products.',
            'icon' => 'chart',
            'home' => 'astro.plans.index',
            'menu' => [
                'Plans' => [
                    ['View Plans', 'astro.plans.index'],
                    ['Add a Plan', 'astro.plans.create'],
                    ['Plan Groups', 'astro.groups.index'],
                    ['Add a Group', 'astro.groups.create'],
                ],
                'Rates' => [
                    ['View Rates', 'astro.rates.index'],
                    ['Update Rates', 'astro.rates.edit'],
                    ['TDSP Fees', 'astro.fees.index'],
                ],
                'Modifiers' => [
                    ['Term', 'astro.terms.edit'],
                    ['ETF', 'astro.etfs.edit'],
                    ['Products', 'astro.modifiers.products'],
                ],
                'BYOP' => [
                    ['Products', 'astro.products.index'],
                ],
                'Upload' => [
                    ['BYOP Discounts', 'astro.byop.upload'],
                ],
                'Data' => 'reference_tables',
            ],
        ],
        'walker' => [
            'name' => 'Walker',
            'desc' => 'Reporting: report runs and their status, the report library, and uploaded reports.',
            'icon' => 'file',
            'home' => 'walker.home',
            'menu' => [
                'Reports' => [
                    ['Report Status', 'walker.home', 'report_problems'],
                    ['Run a Report', 'walker.reports.index'],
                ],
                'Files' => [
                    ['Uploaded Reports', 'walker.uploads.index'],
                ],
            ],
        ],
        'caboose' => [
            'name' => 'Caboose',
            'desc' => 'Finance: payments, credits and debits approval, refunds, deposits, receivables aging and the journal export.',
            'icon' => 'train',
            'home' => 'caboose.dashboard',
            'menu' => [
                'Finance' => [
                    ['Dashboard', 'caboose.dashboard'],
                    ['Payments', 'caboose.payments'],
                    ['Credits & Debits', 'caboose.ledger', 'ledger_pending'],
                    ['Refunds', 'caboose.refunds', 'refunds_pending'],
                    ['Deposits', 'caboose.deposits'],
                ],
                'Accounting' => [
                    ['Receivables Aging', 'caboose.aging'],
                    ['Journal', 'caboose.journal'],
                ],
                'Data' => 'reference_tables',    // pricing, deposits, taxes, fraud indicators and CIS note dispositions
            ],
        ],
        'bounty' => [
            'name' => 'Bounty',
            'desc' => 'Rewards: offers, how stars are earned, redemptions to fulfil, member balances and the monthly drawing.',
            'icon' => 'gift',
            'home' => 'bounty.dashboard',
            'menu' => [
                'Rewards' => [
                    ['Dashboard', 'bounty.dashboard'],
                    ['Offers', 'bounty.offers'],
                    ['Earning Rules', 'bounty.rules'],
                ],
                'Members' => [
                    ['Redemptions', 'bounty.redemptions', 'redemptions_open'],
                    ['Members', 'bounty.members'],
                    ['Monthly Drawing', 'bounty.drawing'],
                ],
                'Data' => 'reference_tables',
            ],
        ],
        'rodeo' => [
            'name' => 'Rodeo',
            'desc' => 'Marketing: email and SMS campaigns, email templates, customer surveys, MSIDs and promo codes.',
            'icon' => 'megaphone',
            'home' => 'rodeo.dashboard',
            'menu' => [
                'Marketing' => [
                    ['Dashboard', 'rodeo.dashboard'],
                    ['Campaigns', 'rodeo.campaigns'],
                ],
                'Emails' => [
                    ['Overview', 'rodeo.emails'],
                    ['Sent Emails', 'rodeo.emails.sent'],
                    ['Templates', 'rodeo.templates'],
                    ['New Template', 'rodeo.templates.create'],
                    ['Categories', 'rodeo.emails.categories'],
                    ['Suppression List', 'rodeo.emails.suppressions'],
                ],
                'Surveys' => [
                    ['All Surveys', 'rodeo.surveys'],
                    ['New Survey', 'rodeo.surveys.create'],
                    ['Responses', 'rodeo.surveys.responses'],
                    ['Question Bank', 'rodeo.surveys.bank'],
                    ['Answer Sets', 'rodeo.surveys.answers'],
                    ['Categories', 'rodeo.surveys.categories'],
                ],
                'Tracking' => [
                    ['MSIDs & Promo Codes', 'rodeo.channels'],
                ],
            ],
        ],
        'deputy' => [
            'name' => 'Deputy',
            'desc' => 'AI agents on open-weight models (Qwen, Kimi, DeepSeek, Llama…): texts and the My Account chat with take-over by a person, models and testing.',
            'icon' => 'bolt',
            'home' => 'deputy.dashboard',
            'menu' => [
                'AI Agents' => [
                    ['Dashboard', 'deputy.dashboard'],
                    ['Conversations', 'deputy.conversations', 'ai_waiting'],
                ],
                'Setup' => [
                    ['Agents', 'deputy.agents'],
                    ['New Agent', 'deputy.agents.create'],
                    ['Models', 'deputy.models'],
                    ['Add a Model', 'deputy.models.create'],
                ],
            ],
        ],
        'sheriff' => [
            'name' => 'Sheriff',
            'desc' => 'Settings: the staff directory (profiles, permissions, log in as), roles, markets, API integrations, scheduled jobs and cron automations.',
            'icon' => 'star',
            'home' => 'sheriff.users.index',
            'menu' => [
                'Access' => [
                    ['Staff Directory', 'sheriff.users.index'],
                    ['Roles', 'sheriff.roles.index'],
                ],
                'Config' => [
                    ['Crons', 'sheriff.jobs.index', 'failed_jobs'],
                    ['Cron Automations', 'sheriff.automations'],
                    ['Update Sitemap', 'sheriff.sitemap'],
                    ['Cached Tables', 'sheriff.cache'],
                ],
                'Markets' => [
                    ['View Markets', 'sheriff.markets.index'],
                    ['Add a Market', 'sheriff.markets.create'],
                ],
                'APIs' => 'integrations',        // one page per integration below, plus the overview
            ],
        ],
    ],

    // Detail pages whose menu item isn't named after them: route area => menu route (Walker run pages sit under Report Status)
    'menu_parents' => [
        'walker.runs' => 'walker.home',
        'corral.items' => 'corral.customers.index',
    ],

    // Sample sign-ins: the login page lists every sample admin for one-click sign-in, and the
    // header gets a "Switch test user" menu. Always off in production.
    'test_logins' => env('APP_ENV', 'production') !== 'production'
        && filter_var(env('ADMIN_TEST_LOGINS', true), FILTER_VALIDATE_BOOL),

    // Departments admin users (employees) belong to
    'departments' => ['Customer Service', 'Pricing', 'Marketing', 'Finance', 'AI Operations', 'IT', 'Management'],

    // Extra permissions a role can hold besides app access
    'permissions' => [
        'refunds' => 'Refunds & reversals',
    ],

    // Sheriff → APIs → Configure writes uploaded values into this file
    'env_path' => env('ADMIN_ENV_PATH', base_path('.env')),

    'customer_statuses' => [
        'Submitted' => 'info',
        'Pending - Credit' => 'warn',
        'Pending - Deposit Due' => 'warn',
        'Pending - No Deposit Due' => 'warn',
        'Pending - Utility Not Answered' => 'warn',
        'Good - On Flow' => 'ok',
        'Rejected - By Utility' => 'bad',
        'Dropped - Churned' => 'bad',
        'Pending - Disconnect' => 'warn',
        'Disconnected' => 'bad',
        'Moved Out' => 'bad',
        'Cancelled' => 'bad',
    ],

    'exceptions' => [
        'Not sent to UtiliBill', 'Not sent to Utility', 'No ESIID', 'Non-Resi Meter',
        'Permit Required', 'Switch Hold', 'Possible Duplicate', 'Other Exception',
    ],

    'queues' => [
        'ercot-exceptions' => ['ERCOT Exceptions', 'Enrollment transactions rejected or stuck at ERCOT'],
        'duplicate-ips' => ['Duplicate IPs', 'Orders placed from the same IP within 24 hours'],
        'duplicated-payments' => ['Duplicated Payments', 'Same card and amount charged twice'],
        'unapplied-deposits' => ['Unapplied Deposits', 'Deposits received but not applied to an account'],
        'unapplied-payments' => ['Unapplied Payments', 'Payments not yet posted to the billing system'],
        'unapplied-credits' => ['Unapplied Credits', 'Bill credits and promos waiting to post'],
        'unapplied-debits' => ['Unapplied Debits', 'Manual debits waiting to post'],
        'unapplied-plan-changes' => ['Unapplied Plan Changes', 'Renewals and plan switches not yet sent'],
        'unapplied-transfers' => ['Unapplied Transfers', 'Move-in / move-out transfers waiting'],
        'unbilled-orders' => ['Unbilled Orders', 'On-flow accounts with no first bill'],
        'unprocessed-autopays' => ['Unprocessed Autopays', 'AutoPay drafts that did not run'],
        'unprocessed-bills' => ['Unprocessed Bills', 'Bills received but not generated'],
        'unprocessed-orders' => ['Unprocessed Orders', 'Orders waiting to be sent to the utility'],
    ],

    // Rows are stored in the reference_rows table.
    // Reference data tables: key => [name, columns, the app whose menu lists it (its "Data" section)]
    'reference_tables' => [
        'deposit-thresholds' => ['Deposit Thresholds', ['Credit Score From', 'Credit Score To', 'Deposit'], 'caboose'],
        'max-deposits' => ['Max Deposits', ['Customer Type', 'Max Deposit'], 'caboose'],
        'tax-rates' => ['Tax Rates', ['City', 'Sales Tax', 'Gross Receipts', 'PUC Assessment'], 'caboose'],
        'interest-rates' => ['Interest Rates', ['Year', 'Deposit Interest'], 'caboose'],
        'bill-credits' => ['Bill Credits & Promos', ['Code', 'Description', 'Amount'], 'caboose'],
        'charge-codes' => ['Charge Codes', ['Code', 'Description'], 'caboose'],
        'rate-exports' => ['Rate Exports', ['Email', 'Customer Type', 'Markets', 'Frequency'], 'caboose'],
        'blackout-days' => ['Blackout Days', ['Date', 'Reason'], 'astro'],
        'ips' => ['IPs', ['IP Address', 'Action', 'Note'], 'lando'],
        'monthly-drawing' => ['Drawing Winners', ['Month', 'Prize', 'Winner Account', 'Drawn On'], 'bounty'],
        'fraud-indicators' => ['Fraud Indicators', ['Rule', 'Action'], 'caboose'],
        'note-dispositions' => ['CIS Note Dispositions', ['Code', 'Label'], 'caboose'],
    ],

    // Scheduled jobs (registered in routes/console.php). Run them on Linux with:
    //   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
    'jobs' => [
        'et:send-orders' => ['Send orders to utility', '*/15 * * * *'],
        'et:ercot-responses' => ['Pull ERCOT 814 responses', '*/10 * * * *'],
        'et:autopay' => ['Process AutoPay drafts', '0 6 * * *'],
        'et:scheduled-payments' => ['Pay scheduled My Account payments', '* * * * *'],
        'et:campaigns' => ['Send scheduled marketing campaigns', '*/5 * * * *'],
        'et:sync-payments' => ['Sync payments to Utilibill', '*/30 * * * *'],
        'et:welcome-packets' => ['Generate welcome packets', '0 * * * *'],
        'et:rewards-stars' => ['Rangler Rewards stars', '0 2 * * *'],
        'et:export-rates' => ['Export rates file', '30 5 * * *'],
        'et:sitemap' => ['Rebuild sitemap', '0 3 * * 0'],
    ],

    // Third-party integrations. Put the values in .env — never in the database or repo.
    'integrations' => [
        'aig' => $integration('AIG', 'Home protection products', ['AIG_ENDPOINT', 'AIG_PARTNER_ID', 'AIG_API_KEY']),
        'amazon' => $integration('Amazon', 'S3 document storage & SES email', ['AWS_DEFAULT_REGION', 'AWS_BUCKET', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY']),
        'apple' => $integration('Apple', 'Push notifications (APNs)', ['APNS_TEAM_ID', 'APNS_KEY_ID', 'APNS_BUNDLE_ID', 'APNS_PRIVATE_KEY']),
        'authnet' => $integration('Auth.net', 'Card processing (legacy)', ['AUTHNET_LOGIN_ID', 'AUTHNET_TRANSACTION_KEY']),
        'ibm' => $integration('IBM', 'Weather data for forecasting', ['IBM_WEATHER_ENDPOINT', 'IBM_WEATHER_API_KEY']),
        'innowatts' => $integration('Innowatts', 'Load forecasting', ['INNOWATTS_ENDPOINT', 'INNOWATTS_CLIENT_ID', 'INNOWATTS_CLIENT_SECRET']),
        'experian' => $integration('Experian', 'Credit checks', ['EXPERIAN_ENDPOINT', 'EXPERIAN_SUBSCRIBER_CODE', 'EXPERIAN_USERNAME', 'EXPERIAN_PASSWORD']),
        'plaid' => $integration('Plaid', 'Bank account linking', ['PLAID_ENV', 'PLAID_CLIENT_ID', 'PLAID_SECRET']),
        'quickbooks' => $integration('QuickBooks', 'Accounting journal entries', ['QUICKBOOKS_REALM_ID', 'QUICKBOOKS_CLIENT_ID', 'QUICKBOOKS_CLIENT_SECRET']),
        'salesforce' => $integration('SalesForce', 'Marketing Cloud email', ['SALESFORCE_SUBDOMAIN', 'SALESFORCE_CLIENT_ID', 'SALESFORCE_CLIENT_SECRET']),
        'stripe' => $integration('Stripe', 'Card & ACH payments', ['STRIPE_KEY', 'STRIPE_SECRET', 'STRIPE_WEBHOOK_SECRET']),
        'utilibill' => $integration('Utilibill', 'Billing system (UB)', ['UTILIBILL_ENDPOINT', 'UTILIBILL_USERNAME', 'UTILIBILL_PASSWORD']),
        'sms' => $integration('Twilio', 'Text messages (SMS)', ['TWILIO_SID', 'TWILIO_TOKEN', 'TWILIO_FROM']),
        'open_models' => $integration('Open Models API', 'Hosted open-weight models for Deputy (Qwen, Kimi, DeepSeek…) through an OpenAI-compatible endpoint', ['OPEN_MODELS_URL', 'OPEN_MODELS_API_KEY']),
        'local_models' => $integration('Local Models', 'Downloaded open-weight models for Deputy, served by Ollama on your own server', ['LOCAL_MODELS_URL']),
    ],

];
