<?php

use App\Http\Controllers\Backend;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\MyAccount;
use App\Http\Controllers\Frontend\PlanController;
use App\Http\Controllers\Frontend\ShopController;
use App\Http\Controllers\Frontend\SignupController;
use App\Http\Controllers\Frontend\SiteController;
use App\Http\Controllers\Frontend\SitePageController;
use App\Http\Controllers\Frontend\SurveyController;
use App\Http\Middleware\CacheAdminTables;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Website
|--------------------------------------------------------------------------
| The website's pages are static files in public/. Laravel only supplies the
| data they load and handles the contact form.
*/
Route::get('/', [SiteController::class, 'home']);
// The built-in website pages live in public/frontend/ and keep their addresses (/plans.html …)
Route::get('{page}.html', [SiteController::class, 'file'])->whereIn('page', ['index', 'plans', 'build-your-own-plan', 'business', 'contact-us']);
Route::get('/shared/config/brand.js', [SiteController::class, 'brand']);
Route::get('/shared/config/catalog.js', [SiteController::class, 'catalog']);
Route::get('/site/session', [SiteController::class, 'session'])->middleware('throttle:120,1');
Route::post('/site/ping', [SiteController::class, 'ping'])->middleware('throttle:30,1');
Route::post('/site/consent', [SiteController::class, 'consent'])->middleware('throttle:20,1');
Route::post('/contact', [SiteController::class, 'contact'])->middleware('throttle:10,1');
Route::get('/site/components', [SitePageController::class, 'components']);

// My Account: the customer portal (guard "customer", separate from admin sign-in)
Route::prefix('myaccount')->name('myaccount.')->group(function () {
    Route::get('quickpay', [MyAccount\PortalController::class, 'quickpay'])->middleware('throttle:30,1')->name('quickpay');
    // Testing: sign in as a sample customer in one click (404 unless admin.test_logins is on)
    Route::post('login/test/{customer}', [MyAccount\AuthController::class, 'testLogin'])->middleware('throttle:30,1')->name('login.test');
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [MyAccount\AuthController::class, 'show'])->name('login');
        Route::post('login', [MyAccount\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
        Route::get('create-account', [MyAccount\AuthController::class, 'registerForm'])->name('register');
        Route::post('create-account', [MyAccount\AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
        Route::get('forgot-{what}', [MyAccount\AuthController::class, 'forgotForm'])->whereIn('what', ['password', 'username'])->name('forgot');
        Route::post('forgot-{what}', [MyAccount\AuthController::class, 'forgot'])->whereIn('what', ['password', 'username'])->middleware('throttle:5,1')->name('forgot.send');
        Route::get('reset-password/{token}', [MyAccount\AuthController::class, 'resetForm'])->name('reset');
        Route::post('reset-password', [MyAccount\AuthController::class, 'reset'])->middleware('throttle:10,1')->name('reset.store');
    });
    Route::middleware('auth:customer')->group(function () {
        Route::get('/', fn () => redirect()->route('myaccount.dashboard'));
        Route::post('logout', [MyAccount\AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [MyAccount\PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('bill-and-payments/view-bills', [MyAccount\PortalController::class, 'bills'])->name('bills');
        Route::get('bill-and-payments/pay-bill', [MyAccount\PortalController::class, 'payForm'])->name('pay');
        Route::post('bill-and-payments/pay-bill', [MyAccount\PortalController::class, 'pay'])->middleware('throttle:10,1')->name('pay.store');
        Route::delete('bill-and-payments/scheduled/{scheduled}', [MyAccount\PortalController::class, 'cancelScheduled'])->name('scheduled.cancel');
        Route::post('property/{account}', [MyAccount\PortalController::class, 'switchProperty'])->name('property');
        Route::get('products', [MyAccount\PortalController::class, 'products'])->name('products');
        Route::get('bill-and-payments/payment-methods', [MyAccount\PortalController::class, 'methods'])->name('methods');
        Route::delete('payment-methods/{method}', [MyAccount\PortalController::class, 'removeMethod'])->name('methods.remove');
        Route::post('payment-methods/{method}/default', [MyAccount\PortalController::class, 'defaultMethod'])->name('methods.default');
        Route::get('energy-insights', [MyAccount\PortalController::class, 'insights'])->name('insights');
        Route::get('enroll/{product}', [MyAccount\PortalController::class, 'product'])->name('product');
        Route::post('enroll/{product}', [MyAccount\PortalController::class, 'toggleProduct'])->name('product.toggle');
        Route::get('profile-and-preferences', [MyAccount\PortalController::class, 'profile'])->name('profile');
        Route::put('profile-and-preferences', [MyAccount\PortalController::class, 'updateProfile'])->name('profile.update');
        Route::put('change-password', [MyAccount\PortalController::class, 'password'])->name('password');
        Route::post('authorized-users', [MyAccount\PortalController::class, 'authorizedUser'])->name('authorized.store');
        Route::delete('authorized-users/{index}', [MyAccount\PortalController::class, 'removeAuthorizedUser'])->whereNumber('index')->name('authorized.remove');
        Route::post('link-accounts', [MyAccount\PortalController::class, 'linkAccount'])->name('link');
        Route::get('plan-and-services/current-plan', [MyAccount\PortalController::class, 'plan'])->name('plan');
        Route::post('plan-and-services/renew-plan', [MyAccount\PortalController::class, 'renew'])->name('renew');
        Route::get('plan-and-services/transfer-service', [MyAccount\PortalController::class, 'transferForm'])->name('transfer');
        Route::post('plan-and-services/transfer-service', [MyAccount\PortalController::class, 'transfer'])->name('transfer.store');
        Route::get('rewards', [MyAccount\PortalController::class, 'rewards'])->name('rewards');
        Route::post('rewards', [MyAccount\PortalController::class, 'redeem'])->name('rewards.redeem');
        Route::get('refer-a-friend', [MyAccount\PortalController::class, 'refer'])->name('refer');
        Route::get('message-center', [MyAccount\PortalController::class, 'messages'])->name('messages');
        // Shop: checkout (the cart itself is /shop/cart) and orders
        Route::get('checkout', [MyAccount\CheckoutController::class, 'form'])->name('checkout');
        Route::post('checkout', [MyAccount\CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('checkout.place');
        Route::get('orders', [MyAccount\CheckoutController::class, 'orders'])->name('orders');
        Route::get('orders/{order}', [MyAccount\CheckoutController::class, 'order'])->name('order');
        Route::get('message-center/{message}', [MyAccount\PortalController::class, 'message'])->whereNumber('message')->name('message');
        Route::get('bill-and-payments/bills/{bill}/pdf', [MyAccount\PortalController::class, 'billPdf'])->whereNumber('bill')->name('bill.pdf');
        // The chat bubble (bottom right): the bot answers until a person takes over
        Route::get('chat', [MyAccount\ChatController::class, 'show'])->middleware('throttle:120,1')->name('chat');
        Route::post('chat', [MyAccount\ChatController::class, 'send'])->middleware('throttle:20,1')->name('chat.send');
        Route::post('chat/person', [MyAccount\ChatController::class, 'person'])->middleware('throttle:10,1')->name('chat.person');
    });
});

// Shop: a sample product catalog with filters and sorts
// A page for each plan
Route::get('plans/{page}', [PlanController::class, 'show'])->where('page', '[a-z0-9-]+')->name('plans.show');
Route::get('shop', [ShopController::class, 'index'])->name('shop');
Route::get('shop/cart', [CartController::class, 'show'])->name('shop.cart');
Route::post('shop/cart', [CartController::class, 'add'])->middleware('throttle:60,1')->name('shop.cart.add');
Route::patch('shop/cart/{sku}', [CartController::class, 'update'])->name('shop.cart.update');
Route::delete('shop/cart/{sku}', [CartController::class, 'remove'])->name('shop.cart.remove');
Route::get('shop/{slug}', [ShopController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('shop.show');

// Website sign-up (the original /checkout flow)
// Customer surveys (built in Rodeo → Surveys)
Route::get('survey/{survey:slug}', [SurveyController::class, 'show'])->name('survey.show');
Route::post('survey/{survey:slug}', [SurveyController::class, 'store'])->middleware('throttle:10,1')->name('survey.store');

Route::prefix('checkout')->name('checkout')->group(function () {
    Route::get('/', [SignupController::class, 'start']);
    Route::post('address', [SignupController::class, 'address'])->name('.address');
    Route::get('plan', [SignupController::class, 'plans'])->name('.plan');
    Route::post('plan', [SignupController::class, 'plan'])->name('.plan.store');
    Route::get('about', [SignupController::class, 'aboutForm'])->name('.about');
    Route::post('about', [SignupController::class, 'about'])->name('.about.store');
    Route::get('review', [SignupController::class, 'review'])->name('.review');
    Route::post('review', [SignupController::class, 'submit'])->middleware('throttle:10,1')->name('.submit');
    Route::get('accepted', [SignupController::class, 'accepted'])->name('.accepted');
    Route::post('save', [SignupController::class, 'save'])->middleware('throttle:10,1')->name('.save');
    Route::get('saved', [SignupController::class, 'saved'])->name('.saved');
    Route::get('resume/{token}', [SignupController::class, 'resume'])->name('.resume');
    Route::get('deposit', [SignupController::class, 'deposit'])->middleware('throttle:30,1')->name('.deposit');
    Route::post('cancel', [SignupController::class, 'cancel'])->middleware('throttle:10,1')->name('.cancel');
    Route::get('{page}', [SignupController::class, 'page'])->whereIn('page', ['alternatives', 'frozen', 'start-call', 'cancel', 'error'])->name('.page');
});

/*
|--------------------------------------------------------------------------
| Admin tools
|--------------------------------------------------------------------------
| Menus for each app are in config/admin.php. Each app group checks that the
| user's role includes that app (middleware 'app:<key>').
*/
Route::prefix('admin')->group(function () {
    // Admin sign-in is the employee login (guard "web"); a My Account customer login never opens these pages
    Route::middleware('guest:web')->group(function () {
        Route::get('login', [Backend\AuthController::class, 'show'])->name('admin.login');
        Route::post('login', [Backend\AuthController::class, 'login'])->middleware('throttle:10,1')->name('admin.login.attempt');
    });

    // Testing: sign in as a sample admin in one click (404 unless admin.test_logins is on)
    Route::post('login/test/{user}', [Backend\AuthController::class, 'testLogin'])->middleware('throttle:30,1')->name('admin.login.test');

    // Every admin page keeps a cached copy of its table (Sheriff → Cached Tables)
    Route::middleware(['auth:web', CacheAdminTables::class])->group(function () {
        Route::post('logout', [Backend\AuthController::class, 'logout'])->name('admin.logout');
        Route::get('/', Backend\LauncherController::class)->name('admin.launcher');

        // Admin apps created from the launcher's "New Admin App" button
        Route::get('apps/create', [Backend\AppController::class, 'create'])->name('apps.create');
        Route::post('apps', [Backend\AppController::class, 'store'])->name('apps.store');
        Route::get('apps/{app}/edit', [Backend\AppController::class, 'edit'])->name('apps.edit');
        Route::put('apps/{app}', [Backend\AppController::class, 'update'])->name('apps.update');
        Route::delete('apps/{app}', [Backend\AppController::class, 'destroy'])->name('apps.destroy');

        // Corral — customer service
        Route::prefix('corral')->name('corral.')->middleware('app:corral')->group(function () {
            Route::get('/', [Backend\Corral\CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{customer}', [Backend\Corral\CustomerController::class, 'show'])->name('customers.show');
            Route::patch('customers/{customer}/status', [Backend\Corral\CustomerController::class, 'updateStatus'])->name('customers.status');
            Route::post('customers/{customer}/bookmark', [Backend\Corral\CustomerController::class, 'bookmark'])->name('customers.bookmark');
            Route::post('customers/{customer}/notes', [Backend\Corral\CustomerController::class, 'addNote'])->name('customers.notes');
            Route::post('payments/{payment}/reverse', [Backend\Corral\CustomerController::class, 'reversePayment'])->name('payments.reverse');
            Route::get('history/{item}', [Backend\HistoryController::class, 'show'])->name('history.show');
            // The original data item pages: corral/data/item/{id}/{Model} (config/items.php)
            Route::controller(Backend\Corral\ItemController::class)->where(['id' => '[0-9]+', 'model' => '\w+_model'])->group(function () {
                Route::get('data/item/{id}/{model}', 'show')->name('items.show');
                Route::get('data/edit/{id}/{model}', 'edit')->name('items.edit');
                Route::put('data/edit/{id}/{model}', 'update')->name('items.update');
                Route::delete('data/delete/{id}/{model}', 'destroy')->name('items.destroy');
                Route::post('data/act/{id}/{model}/{action}', 'act')->middleware('throttle:30,1')->name('items.act');
            });
            Route::get('customers/{customer}/ticket', [Backend\HistoryController::class, 'ticket'])->name('customers.ticket');
            Route::get('customers/{customer}/logs/{log}', [Backend\HistoryController::class, 'log'])->name('customers.log');

            // Account page sections
            Route::get('customers/{customer}/actions/{action}', [Backend\Corral\AccountController::class, 'actionForm'])->name('customers.action');
            Route::post('customers/{customer}/actions/{action}', [Backend\Corral\AccountController::class, 'runAction'])->name('customers.action.run');
            Route::post('customers/{customer}/flags', [Backend\Corral\AccountController::class, 'addFlag'])->name('customers.flags.add');
            Route::delete('flags/{flag}', [Backend\Corral\AccountController::class, 'removeFlag'])->name('flags.remove');
            Route::post('customers/{customer}/products', [Backend\Corral\AccountController::class, 'addProduct'])->name('customers.products.add');
            Route::delete('products/{product}', [Backend\Corral\AccountController::class, 'removeProduct'])->name('products.remove');
            Route::delete('ledger/{entry}', [Backend\Corral\AccountController::class, 'deleteLedger'])->name('ledger.delete');
            Route::post('payment-methods/{method}/autopay', [Backend\Corral\AccountController::class, 'setAutopay'])->name('methods.autopay');
            Route::delete('payment-methods/{method}', [Backend\Corral\AccountController::class, 'removeMethod'])->name('methods.remove');
            Route::post('customers/{customer}/files', [Backend\Corral\AccountController::class, 'uploadFile'])->name('customers.files.upload');
            Route::get('files/{file}', [Backend\Corral\AccountController::class, 'downloadFile'])->name('files.download');
            Route::patch('customers/{customer}/marketing', [Backend\Corral\AccountController::class, 'updateMarketing'])->name('customers.marketing');
            Route::get('customers/{customer}/stars', [Backend\Corral\AccountController::class, 'stars'])->name('customers.stars');
            Route::post('customers/{customer}/stars', [Backend\Corral\AccountController::class, 'redeem'])->name('customers.stars.redeem');
            Route::post('customers/{customer}/stars/recalculate', [Backend\Corral\AccountController::class, 'recalculateStars'])->name('customers.stars.recalculate');
            Route::post('customers/{customer}/queues', [Backend\Corral\AccountController::class, 'addQueue'])->name('customers.queues.add');
            Route::patch('queue-logs/{log}', [Backend\Corral\AccountController::class, 'moveQueue'])->name('queues.move');
            Route::post('plan-terms/{term}', [Backend\Corral\AccountController::class, 'planTermAction'])->name('plan-terms.action');
            Route::patch('addresses/{address}', [Backend\Corral\AccountController::class, 'updateAddress'])->name('addresses.update');
            Route::post('ercot/{tx}', [Backend\Corral\AccountController::class, 'ercotAction'])->name('ercot.action');
            Route::get('customers/{customer}/contact-log', [Backend\Corral\AccountController::class, 'contactLog'])->name('customers.contact-log');
            Route::post('customers/{customer}/contact-log/marketing', [Backend\Corral\AccountController::class, 'toggleMarketing'])->name('customers.marketing-toggle');
            Route::get('customers/{customer}/api-log', [Backend\Corral\AccountController::class, 'apiLog'])->name('customers.api-log');
            Route::get('esiid', [Backend\Corral\EsiidController::class, 'index'])->name('esiid');
            Route::get('orders/create', [Backend\Corral\OrderController::class, 'create'])->name('orders.create');
            Route::get('orders/create-biz', [Backend\Corral\OrderController::class, 'createBiz'])->name('orders.create-biz');
            Route::post('orders', [Backend\Corral\OrderController::class, 'store'])->name('orders.store');
            Route::get('reports/orders', [Backend\Corral\ReportController::class, 'orders'])->name('reports.orders');
            Route::get('reports/download/{run}', [Backend\Corral\ReportController::class, 'download'])->name('reports.download');
            Route::get('reports/notes', [Backend\Corral\ReportController::class, 'notes'])->name('reports.notes');
            Route::get('reports/phonecalls', [Backend\Corral\ReportController::class, 'phonecalls'])->name('reports.phonecalls');
            Route::get('sms', [Backend\Corral\SmsController::class, 'index'])->name('sms');
            Route::get('sms/new', [Backend\Corral\SmsController::class, 'create'])->name('sms.create');
            Route::get('sms/{message}', [Backend\Corral\SmsController::class, 'show'])->whereNumber('message')->name('sms.show');
            Route::get('calls', [Backend\Corral\CallController::class, 'index'])->name('calls');
            Route::get('calls/{call}/transcript', [Backend\Corral\CallController::class, 'transcript'])->name('calls.transcript');
            Route::post('sms', [Backend\Corral\SmsController::class, 'send'])->middleware('throttle:30,1')->name('sms.send');
            Route::get('ercot', [Backend\Corral\ErcotController::class, 'index'])->name('ercot');
            Route::get('queues', [Backend\Corral\QueueController::class, 'index'])->name('queues.index');
            Route::get('queues/{queue}', [Backend\Corral\QueueController::class, 'show'])->name('queues.show');
            Route::post('work-items/{item}/resolve', [Backend\Corral\QueueController::class, 'resolve'])->name('queues.resolve');
            Route::get('messages', [Backend\Corral\MessageController::class, 'index'])->name('messages.index');
            Route::post('messages/{message}/handled', [Backend\Corral\MessageController::class, 'handled'])->name('messages.handled');
            Route::get('messages/{message}', [Backend\Corral\MessageController::class, 'show'])->name('messages.show');
            Route::post('messages/{message}/reply', [Backend\Corral\MessageController::class, 'reply'])->name('messages.reply');
        });

        // Website pages, plan groups and plans: Lando's screens, which Astro's menu also has.
        // Registered under each app's own URL and route names so neither links into the other.
        $websiteScreens = function () {
            Route::resource('plans', Backend\Lando\PlanController::class)->except(['show', 'destroy']);
            Route::resource('groups', Backend\Lando\PlanGroupController::class)->except(['show', 'destroy']);
            Route::post('groups/{group}/plans', [Backend\Lando\PlanGroupController::class, 'attach'])->name('groups.attach');
            Route::delete('groups/{group}/plans/{plan}', [Backend\Lando\PlanGroupController::class, 'detach'])->name('groups.detach');
        };

        // Reference data tables, each listed in the app that owns it (config admin.reference_tables)
        $dataTables = function () {
            Route::get('data/{table}', [Backend\Sheriff\SystemController::class, 'table'])->name('data.show');
            Route::put('data/{table}', [Backend\Sheriff\SystemController::class, 'updateTable'])->name('data.update');
        };

        // Lando — website: dashboard, pages, page templates, content blocks
        Route::prefix('lando')->name('lando.')->middleware('app:lando')->group(function () use ($dataTables) {
            Route::get('styles', [Backend\Lando\StyleController::class, 'index'])->name('styles');
            // Site: dashboard (Lando's home), health, visitors, blocked IPs and areas
            Route::get('/', [Backend\Lando\DashboardController::class, 'dashboard'])->name('dashboard');
            Route::get('health', [Backend\Lando\DashboardController::class, 'health'])->name('health');
            Route::get('visitors', [Backend\Lando\DashboardController::class, 'visitors'])->name('visitors');
            Route::get('cookie-consents', [Backend\Lando\ConsentController::class, 'index'])->name('consents');
            foreach (['ips', 'areas'] as $kind) {
                Route::controller(Backend\Lando\BlockedController::class)->prefix('blocked-'.$kind)->name('blocked.'.$kind)->group(function () {
                    Route::get('/', 'index');
                    Route::get('new', 'create')->name('.create');
                    Route::post('/', 'store')->name('.store');
                    Route::get('{block}/edit', 'edit')->name('.edit');
                    Route::put('{block}', 'update')->name('.update');
                    Route::post('{block}/toggle', 'toggle')->name('.toggle');
                    Route::delete('{block}', 'destroy')->name('.destroy');
                });
            }
            Route::get('pages/{page}/activity', [Backend\Lando\PageController::class, 'activity'])->name('pages.activity');
            Route::resource('pages', Backend\Lando\PageController::class)->except('show');
            Route::post('pages/{page}/components', [Backend\Lando\PageController::class, 'addComponent'])->name('pages.components.store');
            Route::post('page-components/{component}/move', [Backend\Lando\PageController::class, 'moveComponent'])->name('pages.components.move');
            Route::patch('page-components/{component}', [Backend\Lando\PageController::class, 'updateComponent'])->name('pages.components.update');
            Route::delete('page-components/{component}', [Backend\Lando\PageController::class, 'removeComponent'])->name('pages.components.destroy');
            Route::post('sitemap', [Backend\Lando\PageController::class, 'sitemap'])->name('pages.sitemap');
            Route::resource('templates', Backend\Lando\TemplateController::class)->except(['show', 'destroy']);
            Route::resource('blocks', Backend\Lando\BlockController::class)->except(['show']);
            Route::get('block-categories/new', [Backend\Lando\BlockController::class, 'categoryForm'])->name('blocks.categories.create');
            Route::post('block-categories', [Backend\Lando\BlockController::class, 'addCategory'])->name('blocks.categories.store');
            $dataTables();
        });

        // Astro — pricing modifiers
        Route::prefix('astro')->name('astro.')->middleware('app:astro')->group(function () use ($websiteScreens, $dataTables) {
            Route::get('/', fn () => redirect()->route('astro.plans.index'));
            Route::get('rates', [Backend\Lando\RateController::class, 'index'])->name('rates.index');
            Route::get('rates/edit', [Backend\Lando\RateController::class, 'edit'])->name('rates.edit');
            Route::put('rates', [Backend\Lando\RateController::class, 'update'])->name('rates.update');
            Route::get('fees', [Backend\Lando\FeeController::class, 'index'])->name('fees.index');
            Route::put('fees', [Backend\Lando\FeeController::class, 'update'])->name('fees.update');
            $dataTables();
            Route::get('terms', [Backend\Astro\PricingController::class, 'terms'])->name('terms.edit');
            Route::put('terms', [Backend\Astro\PricingController::class, 'updateTerms'])->name('terms.update');
            Route::get('etfs', [Backend\Astro\PricingController::class, 'etfs'])->name('etfs.edit');
            Route::put('etfs', [Backend\Astro\PricingController::class, 'updateEtfs'])->name('etfs.update');
            Route::get('products', [Backend\Astro\PricingController::class, 'products'])->name('products.index');
            Route::put('products', [Backend\Astro\PricingController::class, 'updateProducts'])->name('products.update');
            Route::get('modifiers/products', [Backend\Astro\PricingController::class, 'modifierProducts'])->name('modifiers.products');
            Route::put('modifiers/products', [Backend\Astro\PricingController::class, 'updateModifierProducts'])->name('modifiers.products.update');
            $websiteScreens(); // Website → Pages, Groups and Plans → Plans, New Plan, at /admin/astro/…
            Route::get('uploads/byop', [Backend\Astro\PricingController::class, 'byopUpload'])->name('byop.upload');
            Route::post('uploads/byop', [Backend\Astro\PricingController::class, 'byopImport'])->name('byop.import');
        });

        // Walker — reporting: report runs by status, report library, uploaded reports
        Route::prefix('walker')->name('walker.')->middleware('app:walker')->group(function () {
            Route::get('/', [Backend\Walker\ReportController::class, 'home'])->name('home');
            Route::get('runs/{run}', [Backend\Walker\ReportController::class, 'show'])->name('runs.show');
            Route::post('runs/{run}/rerun', [Backend\Walker\ReportController::class, 'rerun'])->name('runs.rerun');
            Route::get('runs/{run}/download', [Backend\Walker\ReportController::class, 'download'])->name('runs.download');
            Route::get('reports', [Backend\Walker\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{key}', [Backend\Walker\ReportController::class, 'report'])->name('reports.show');
            Route::post('reports/{key}', [Backend\Walker\ReportController::class, 'run'])->name('reports.run');
            Route::get('uploads', [Backend\Walker\UploadController::class, 'index'])->name('uploads.index');
            Route::get('uploads/new', [Backend\Walker\UploadController::class, 'create'])->name('uploads.create');
            Route::post('uploads', [Backend\Walker\UploadController::class, 'store'])->name('uploads.store');
            Route::get('uploads/{upload}', [Backend\Walker\UploadController::class, 'download'])->name('uploads.download');
            Route::delete('uploads/{upload}', [Backend\Walker\UploadController::class, 'destroy'])->name('uploads.destroy');
        });

        // Caboose — finance
        Route::prefix('caboose')->name('caboose.')->middleware('app:caboose')->controller(Backend\Caboose\FinanceController::class)->group(function () use ($dataTables) {
            $dataTables();
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('payments', 'payments')->name('payments');
            Route::get('payments/new', 'paymentForm')->name('payments.create');
            Route::post('payments', 'recordPayment')->name('payments.record');
            Route::post('payments/{payment}/reverse', 'reverse')->name('payments.reverse');
            Route::get('credits-debits', 'ledger')->name('ledger');
            Route::get('credits-debits/{entry}', 'ledgerShow')->name('ledger.show');
            Route::post('credits-debits/{entry}', 'decideLedger')->name('ledger.decide');
            Route::get('refunds', 'refunds')->name('refunds');
            Route::get('refunds/new', 'refundForm')->name('refunds.create');
            Route::post('refunds', 'requestRefund')->name('refunds.request');
            Route::get('refunds/{refund}', 'refundShow')->name('refunds.show');
            Route::post('refunds/{refund}', 'decideRefund')->name('refunds.decide');
            Route::get('deposits', 'deposits')->name('deposits');
            Route::get('aging', 'aging')->name('aging');
            Route::get('journal', 'journal')->name('journal');
        });

        // Bounty — rewards
        Route::prefix('bounty')->name('bounty.')->middleware('app:bounty')->controller(Backend\Bounty\RewardsController::class)->group(function () use ($dataTables) {
            $dataTables();
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('offers', 'offers')->name('offers');
            Route::put('offers', 'saveOffers')->name('offers.save');
            Route::get('rules', 'rules')->name('rules');
            Route::put('rules', 'saveRules')->name('rules.save');
            Route::post('award', 'award')->name('award');
            Route::get('redemptions', 'redemptions')->name('redemptions');
            Route::post('redemptions/{entry}', 'fulfil')->name('fulfil');
            Route::get('members', 'members')->name('members');
            Route::post('members/{customer}', 'adjust')->name('adjust');
            Route::get('drawing', 'drawing')->name('drawing');
            Route::get('drawing/draw', 'drawForm')->name('drawing.draw');
            Route::post('drawing', 'draw')->name('draw');
        });

        // Rodeo — marketing
        Route::prefix('rodeo')->name('rodeo.')->middleware('app:rodeo')->controller(Backend\Rodeo\MarketingController::class)->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('campaigns', 'campaigns')->name('campaigns');
            Route::get('campaigns/new', 'campaignForm')->name('campaigns.create');
            Route::get('campaigns/count', 'campaignCount')->name('campaigns.count');
            Route::post('campaigns', 'saveCampaign')->name('campaigns.store');
            Route::get('campaigns/{campaign}', 'campaignForm')->name('campaigns.edit');
            Route::put('campaigns/{campaign}', 'saveCampaign')->name('campaigns.update');
            Route::post('campaigns/{campaign}/send', 'sendCampaign')->name('campaigns.send');
            Route::post('campaigns/{campaign}/schedule', 'scheduleCampaign')->name('campaigns.schedule');
            Route::post('campaigns/{campaign}/unschedule', 'unscheduleCampaign')->name('campaigns.unschedule');
            Route::post('campaigns/{campaign}/duplicate', 'duplicateCampaign')->name('campaigns.duplicate');
            Route::get('templates', 'templates')->name('templates');
            Route::get('templates/new', 'templateForm')->name('templates.create');
            Route::post('templates', 'saveTemplate')->name('templates.store');
            Route::get('templates/{template}', 'templateForm')->name('templates.edit');
            Route::put('templates/{template}', 'saveTemplate')->name('templates.update');
            Route::controller(Backend\Rodeo\TemplateTestController::class)->prefix('templates/{template}/test')->name('templates.test')->group(function () {
                Route::get('/', 'show');
                Route::post('/', 'send')->middleware('throttle:20,1')->name('.send');
                Route::get('recipients', 'recipientsPreview')->name('.recipients');
                Route::get('preview', 'preview')->name('.preview');
                Route::post('{send}/again', 'again')->middleware('throttle:20,1')->name('.again');
            });
            Route::controller(Backend\Rodeo\EmailController::class)->prefix('emails')->name('emails')->group(function () {
                Route::get('/', 'overview');
                Route::get('sent', 'sent')->name('.sent');
                Route::get('sent/{email}', 'show')->name('.sent.show');
                Route::get('categories', 'categories')->name('.categories');
                Route::get('categories/new', 'categoryForm')->name('.categories.create');
                Route::get('categories/{category}/edit', 'categoryForm')->name('.categories.edit');
                Route::post('categories', 'saveCategory')->name('.categories.store');
                Route::put('categories/{category}', 'saveCategory')->name('.categories.update');
                Route::delete('categories/{category}', 'deleteCategory')->name('.categories.destroy');
                Route::get('suppressions', 'suppressions')->name('.suppressions');
                Route::get('suppressions/new', 'suppressionForm')->name('.suppressions.create');
                Route::post('suppressions', 'saveSuppression')->name('.suppressions.store');
                Route::delete('suppressions/{suppression}', 'deleteSuppression')->name('.suppressions.destroy');
            });
            Route::controller(Backend\Rodeo\SurveyController::class)->prefix('surveys')->name('surveys')->group(function () {
                Route::get('/', 'index');
                Route::get('new', 'create')->name('.create');
                Route::post('/', 'store')->name('.store');
                Route::get('responses', 'responses')->name('.responses');
                Route::get('responses/{response}', 'response')->name('.responses.show');
                Route::get('bank', 'bank')->name('.bank');
                Route::get('bank/new', 'bankForm')->name('.bank.create');
                Route::get('bank/{question}/edit', 'bankForm')->name('.bank.edit');
                Route::post('bank', 'saveBank')->name('.bank.store');
                Route::put('bank/{question}', 'saveBank')->name('.bank.update');
                Route::delete('bank/{question}', 'deleteBank')->name('.bank.destroy');
                Route::get('answer-sets', 'answers')->name('.answers');
                Route::get('answer-sets/new', 'answerForm')->name('.answers.create');
                Route::get('answer-sets/{set}/edit', 'answerForm')->name('.answers.edit');
                Route::post('answer-sets', 'saveAnswerSet')->name('.answers.store');
                Route::put('answer-sets/{set}', 'saveAnswerSet')->name('.answers.update');
                Route::delete('answer-sets/{set}', 'deleteAnswerSet')->name('.answers.destroy');
                Route::get('categories', 'categories')->name('.categories');
                Route::get('categories/new', 'categoryForm')->name('.categories.create');
                Route::get('categories/{category}/edit', 'categoryForm')->name('.categories.edit');
                Route::post('categories', 'saveCategory')->name('.categories.store');
                Route::put('categories/{category}', 'saveCategory')->name('.categories.update');
                Route::delete('categories/{category}', 'deleteCategory')->name('.categories.destroy');
                Route::get('{survey}', 'edit')->name('.edit');
                Route::put('{survey}', 'update')->name('.update');
                Route::delete('{survey}', 'destroy')->name('.destroy');
                Route::post('{survey}/copy', 'copy')->name('.copy');
                Route::get('{survey}/results', 'results')->name('.results');
                Route::get('{survey}/export', 'export')->name('.export');
            });
            Route::get('channels', 'channels')->name('channels');
            Route::post('channels', 'saveChannel')->name('channels.save');
            Route::get('channels/new', 'channelForm')->name('channels.create');
            Route::get('channels/{channel}/edit', 'channelForm')->name('channels.edit');
            Route::get('promos/new', 'promoForm')->name('promos.create');
            Route::get('promos/{promo}/edit', 'promoForm')->name('promos.edit');
            Route::post('promos', 'savePromo')->name('promos.save');
        });

        // Deputy — AI agents
        Route::prefix('deputy')->name('deputy.')->middleware('app:deputy')->controller(Backend\Deputy\DeputyController::class)->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('conversations', 'conversations')->name('conversations');
            Route::get('conversations/{conversation}', 'conversation')->name('conversations.show');
            Route::post('conversations/{conversation}/take-over', 'takeOver')->name('conversations.take');
            Route::post('conversations/{conversation}/reply', 'reply')->middleware('throttle:60,1')->name('conversations.reply');
            Route::post('conversations/{conversation}/hand-back', 'handBack')->name('conversations.handback');
            Route::post('conversations/{conversation}/close', 'close')->name('conversations.close');
            Route::post('conversations/{conversation}/call', 'call')->middleware('throttle:20,1')->name('conversations.call');
            Route::get('agents', 'agents')->name('agents');
            Route::get('agents/new', 'agentForm')->name('agents.create');
            Route::post('agents', 'saveAgent')->name('agents.store');
            Route::get('agents/{agent}/edit', 'agentForm')->name('agents.edit');
            Route::put('agents/{agent}', 'saveAgent')->name('agents.update');
            Route::get('agents/{agent}/test', 'testForm')->name('agents.test');
            Route::post('agents/{agent}/test', 'runTest')->middleware('throttle:10,1')->name('agents.test.run');
            Route::get('models', 'models')->name('models');
            Route::get('models/new', 'modelForm')->name('models.create');
            Route::post('models', 'saveModel')->name('models.store');
            Route::get('models/{model}/edit', 'modelForm')->name('models.edit');
            Route::put('models/{model}', 'saveModel')->name('models.update');
            Route::post('models/{model}/check', 'checkModel')->middleware('throttle:20,1')->name('models.check');
        });

        // Sheriff — users, roles, integrations, jobs, reference data
        Route::prefix('sheriff')->name('sheriff.')->middleware('app:sheriff')->group(function () {
            Route::get('/', fn () => redirect()->route('sheriff.users.index'));
            Route::get('users', [Backend\Sheriff\UserController::class, 'index'])->name('users.index');
            Route::post('users', [Backend\Sheriff\UserController::class, 'store'])->name('users.store');
            Route::get('users/new', [Backend\Sheriff\UserController::class, 'create'])->name('users.create');
            Route::get('users/{user}/edit', [Backend\Sheriff\UserController::class, 'edit'])->name('users.edit');
            Route::patch('users/{user}', [Backend\Sheriff\UserController::class, 'update'])->name('users.update');
            Route::get('users/{user}/history', [Backend\HistoryController::class, 'user'])->name('users.history');
            Route::get('history/{item}', [Backend\HistoryController::class, 'show'])->name('history.show');
            Route::get('roles', [Backend\Sheriff\RoleController::class, 'index'])->name('roles.index');
            Route::controller(Backend\Sheriff\AutomationController::class)->prefix('automations')->name('automations')->group(function () {
                Route::get('/', 'index');
                Route::get('new', 'create')->name('.create');
                Route::post('/', 'store')->name('.store');
                Route::get('{agent}', 'show')->name('.show');
                Route::get('{agent}/edit', 'edit')->name('.edit');
                Route::put('{agent}', 'update')->name('.update');
                Route::post('{agent}/toggle', 'toggle')->name('.toggle');
            });
            Route::put('roles', [Backend\Sheriff\RoleController::class, 'update'])->name('roles.update');
            Route::get('integrations', [Backend\Sheriff\SystemController::class, 'integrations'])->name('integrations.index');
            Route::get('integrations/{integration}', [Backend\Sheriff\SystemController::class, 'integration'])->name('integrations.show');
            Route::get('cached-tables', [Backend\Sheriff\CacheController::class, 'index'])->name('cache');
            Route::get('cached-tables/{snapshot}', [Backend\Sheriff\CacheController::class, 'show'])->name('cache.show');
            Route::get('integrations/{integration}/configure', [Backend\Sheriff\IntegrationConfigController::class, 'show'])->name('integrations.configure');
            Route::post('integrations/{integration}/configure', [Backend\Sheriff\IntegrationConfigController::class, 'upload'])->middleware('throttle:20,1')->name('integrations.upload');
            Route::post('integrations/{integration}/test', [Backend\Sheriff\IntegrationConfigController::class, 'test'])->middleware('throttle:20,1')->name('integrations.test');
            Route::get('sitemap', [Backend\Sheriff\SystemController::class, 'sitemap'])->name('sitemap');
            Route::post('sitemap', [Backend\Lando\PageController::class, 'sitemap'])->name('sitemap.run');
            Route::resource('markets', Backend\Lando\MarketController::class)->except(['show', 'destroy']);
            Route::get('jobs', [Backend\Sheriff\SystemController::class, 'jobs'])->name('jobs.index');
            Route::post('jobs/run', [Backend\Sheriff\SystemController::class, 'runJob'])->name('jobs.run');
        });

        // A custom app's home page, e.g. /admin/marketing (built-in app routes above take priority)
        Route::get('{appKey}', [Backend\AppController::class, 'show'])->where('appKey', '[a-z0-9][a-z0-9-]*')->name('custom.show');
    });
});

/*
|--------------------------------------------------------------------------
| Website pages built in Lando
|--------------------------------------------------------------------------
| Anything not matched above (and not a file in public/) is looked up as a
| page on the site for this host name. Kept last so it never shadows a route.
*/
Route::get('amp/{path}', [SitePageController::class, 'amp'])->where('path', '.*')->name('site.amp');
Route::get('{path}', [SitePageController::class, 'show'])->where('path', '.*')->name('site.page');
