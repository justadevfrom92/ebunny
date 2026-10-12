<?php

/*
| Brand settings shared by the website (served as /shared/config/brand.js)
| and the admin. Edit here only.
*/

return [
    'name' => env('BRAND_NAME', 'Energy Texas'),
    'wordmark' => env('BRAND_WORDMARK', 'ENERGY TEXAS'),
    'tagline' => 'POWERED BY TEXANS',
    'domain' => env('BRAND_DOMAIN', 'energytexas.com'),
    'phone' => env('BRAND_PHONE', '1-800-555-0100'),          // placeholder — set the real number in .env
    'phoneHref' => env('BRAND_PHONE_HREF', 'tel:18005550100'),
    'hours' => 'Mon – Fri, 8am – 6pm CT',
    'puct' => env('BRAND_PUCT', 'XXXXX'),                    // placeholder — PUCT certificate number
    'rewards' => 'Rangler Rewards',
    // Testing: the header's My Account Sign Out only hides itself and My Account shows it again,
    // instead of signing out. On everywhere but production unless MYACCOUNT_SIGNOUT_TEST says otherwise.
    'signoutTest' => (bool) env('MYACCOUNT_SIGNOUT_TEST', env('APP_ENV', 'production') !== 'production'),
    'nav' => [
        ['index.html', 'Home'],
        ['plans.html', 'Plans'],
        ['build-your-own-plan.html', 'Build Your Own Plan'],
        ['business.html', 'Business'],
        ['shop', 'Shop'],
        ['contact-us.html', 'Contact Us'],
    ],
];
