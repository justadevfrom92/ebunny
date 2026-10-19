<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only admin users hold rights; a customer signed in to My Account never does.
        // Extra rights from config/admin.php (refunds) become gates: Gate::authorize('refunds')
        foreach (array_keys(config('admin.permissions')) as $perm) {
            Gate::define($perm, fn ($user) => $user instanceof User && $user->hasPerm($perm));
        }

        // Deleting is open to anyone who can open the app; changing API settings to anyone with Sheriff
        Gate::define('delete', fn ($user) => $user instanceof User && $user->active);
        Gate::define('configure', fn ($user) => $user instanceof User && $user->hasPerm('sheriff'));

        // Creating, editing and deleting admin apps from the launcher
        Gate::define('manage-apps', fn ($user) => $user instanceof User && $user->hasPerm('sheriff'));
    }
}
