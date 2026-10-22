<?php

/*
|--------------------------------------------------------------------------
| Demo export (php artisan demo:export)
|--------------------------------------------------------------------------
| The pages to publish as a static demo site (for example on GitHub Pages).
| Only these pages are written; links to any other page are switched off,
| so visitors can only move between the pages listed here. Uses the sample
| (fictional) data in the database, so run `php artisan start --fresh` first.
|
| Pages under /admin are viewed as `admin_user`; pages under /myaccount as
| the My Account login `customer`. A page can include a query string.
*/

return [
    'output' => env('DEMO_OUTPUT', 'build/demo'),

    'admin_user' => 'admin@example.com',

    'customer' => 'test.credit',

    /*
    | A public demo shows a placeholder brand: these words are swapped in every
    | exported page and script (skip with --keep-brand). Longer phrases first.
    */
    'replace' => [
        'ENERGY TEXAS' => 'SAMPLE ENERGY',
        'Energy Texas' => 'Sample Energy',
        'energytexas.com' => 'sampleenergy.example',
        'Energytexas' => 'Brand',
        'POWERED BY TEXANS' => 'ELECTRICITY PLANS',
        'RANGLER REWARDS' => 'POWER REWARDS',
        'Rangler' => 'Power',
        '\'rangler' => '\'power',
        'Just Electricity, Y\'all' => 'Simple Monthly',
        'Just Electricity, Y\\u0027all' => 'Simple Monthly',
        'Just Electricity, Y&#039;all' => 'Simple Monthly',
        'Come & Take It' => 'Starter Fixed',
        'Come &amp; Take It' => 'Starter Fixed',
        'Come \\u0026 Take It' => 'Starter Fixed',
        'Freedom Flex' => 'Flex Fixed',
        'Freedom Fix' => 'Rate Lock',
        'FreedomFix' => 'RateLock',
        'Pardner Preferred' => 'Preferred',
        'Pardner' => 'Friend',
        'The Gruene 18' => 'Green 18',
        'Bigger Than Texas 24' => 'Big Value 24',
        '36 Inflation Fix' => 'Long-Term Fix 36',
        'Inflation Fix' => 'Long-Term Fix',
        'ecobee Clean & Green 36' => 'Smart Home Green 36',
        'ecobee Clean &amp; Green 36' => 'Smart Home Green 36',
        'ecobee Clean \\u0026 Green 36' => 'Smart Home Green 36',
        'ecobee' => 'smart',
        'Takin\' Care of Business' => 'Business Choice',
        'Takin\\u0027 Care of Business' => 'Business Choice',
        'Takin&#039; Care of Business' => 'Business Choice',
        'Peak Perks' => 'Peak Rewards',
        'Giddy Up Due Date' => 'Pick Your Due Date',
        'Giddyup' => 'DueDate',
        'Giddy Up' => 'Due Date',
        'giddyup' => 'duedate',
        'giddy-up' => 'due-date',
        'Get to Learnin\'' => 'Energy Tips',
        'Get to Learnin\\u0027' => 'Energy Tips',
        'Get to Learnin\\\\\'' => 'Energy Tips',
        'Get to Learnin&#039;' => 'Energy Tips',
        'get-to-learnin' => 'energy-tips',
        '<em>bigger</em> than Texas.' => '<em>simple</em> and fair.',
        '<em>bigger<\\/em> than Texas.' => '<em>simple<\\/em> and fair.',
        '&lt;em&gt;bigger&lt;/em&gt; than Texas.' => '&lt;em&gt;simple&lt;/em&gt; and fair.',
        'bigger-than-texas' => 'big-value',
        'just-electricity-yall' => 'simple-monthly',
        'come-and-take-it' => 'starter-fixed',
        'freedom-flex' => 'flex-fixed',
        'pardner-preferred' => 'preferred',
        'the-gruene' => 'green',
        '36-inflation-fix' => 'long-term-fix-36',
        'ecobee-clean-and-green' => 'smart-home-green',
        'takin-care-of-business' => 'business-choice',
        'energy-texas-monthly' => 'sample-energy-monthly',
        'cinco-de-mayo' => 'promo-monthly',
        'fixin-to' => 'fixed',
        'september-savin' => 'seasonal-saver',
        'the-seguin' => 'fixed',
        'the-abilene' => 'fixed',
        'texan-pride' => 'short-term',
        'lone-star' => 'fixed',
        'very-important-texan' => 'vip',
        'true-texan' => 'classic',
        'taste-of-energy-texas' => 'intro',
        'Mill Creek Variable' => 'Business Variable A',
        'mill-creek-variable' => 'business-variable-a',
        'Epicenter' => 'Business Fixed 36',
        'Cinco de Mayo' => 'Promo Monthly',
        'Fixin\' to 14' => 'Fixed 14',
        'Fixin\\u0027 to 14' => 'Fixed 14',
        'Fixin&#039; to 14' => 'Fixed 14',
        'The Seguin 15' => 'Fixed 15',
        'The Abilene 13' => 'Fixed 13',
        'September Savin\'' => 'Seasonal Saver',
        'September Savin\\u0027' => 'Seasonal Saver',
        'September Savin&#039;' => 'Seasonal Saver',
        'Texan Pride 3' => 'Short Term 3',
        'Lone Star 6' => 'Fixed 6',
        'Very Important Texan 12' => 'VIP 12',
        'True Texan 12' => 'Classic 12',
        'Taste of Sample Energy 3' => 'Intro 3',
        'Get to Learnin\\\'' => 'Energy Tips',
        'Ready to switch, pardner?' => 'Ready to switch?',
        'bigger than Texas.' => 'simple and fair.',
    ],

    'pages' => [
        // The website
        '/',
        '/plans',
        '/shop',

        // My Account
        '/myaccount/dashboard',
        '/myaccount/support',

        // Admin
        '/admin',
        '/admin/corral',
        '/admin/ranger',
        '/admin/ranger/tickets',
        '/admin/ranger/tickets?view=unassigned',
        '/admin/ranger/team',
        '/admin/ranger/satisfaction',
        '/admin/lando',
        '/admin/lando/cookie-consents',
    ],
];
