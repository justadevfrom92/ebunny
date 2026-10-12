<?php

/*
|--------------------------------------------------------------------------
| Lando website settings
|--------------------------------------------------------------------------
| Where page components (content blocks) can be placed, and which built-in
| pages are files in public/ rather than built from page content.
*/

return [

    // zone => label. Every page template has these four places.
    'zones' => [
        'top' => 'Top of page (under the banner)',
        'main' => 'Main content (after the page content)',
        'sidebar' => 'Sidebar (beside the page content)',
        'bottom' => 'Bottom of page (above the footer)',
    ],

    // Built-in pages: page path => file in public/frontend/. Their layout is in the file;
    // Lando adds components to their top and bottom zones.
    'files' => [
        '/' => 'index.html',
        'plans' => 'plans.html',
        'byop' => 'build-your-own-plan.html',
        'business' => 'business.html',
        'contact-us' => 'contact-us.html',
    ],

    // Price Grid Type: how a page lists the plans in its price grid group
    'price_grids' => [
        'cards' => 'Plan cards',
        'table' => 'Price table (500 / 1,000 / 2,000 kWh)',
    ],

    // Rating Formula: which price the price grid shows for each plan
    'rating_formulas' => [
        'avg_1000' => 'Average price at 1,000 kWh',
        'avg_500' => 'Average price at 500 kWh',
        'avg_2000' => 'Average price at 2,000 kWh',
        'energy' => 'Energy charge only',
    ],

    // REP ID: the retail electric provider a page sells for (sent with orders started on it)
    'reps' => [
        1 => env('BRAND_NAME', 'Energy Texas'),
    ],

    // Paths a website page can never use (the admin, Laravel's own endpoints)
    'reserved' => ['admin', 'site', 'shared', 'frontend', 'backend', 'contact', 'up', 'storage', 'amp', 'survey', 'checkout', 'myaccount', 'shop'],

];
