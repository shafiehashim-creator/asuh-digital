<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        // Parent account passwords. Admins get stricter rules in asuh:create-admin.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // CHIP and the payment pages require HTTPS; never build http:// links in production.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
