<?php

namespace Larasell\Larasell\Admin;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Admin\Commands\CreateAdminUserCommand;
use Larasell\Larasell\Admin\Models\AdminUser;

class LarasellAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/larasell-admin.php', 'larasell-admin');
    }

    public function boot(): void
    {
        $this->configureAuth();

        $this->loadMigrationsFrom(__DIR__.'/../../database/admin-migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'larasell-admin');

        Route::middleware(config('larasell-admin.middleware', ['web']))
            ->prefix(config('larasell-admin.path', 'admin'))
            ->name('larasell.admin.')
            ->group($this->routeFile());

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateAdminUserCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../config/larasell-admin.php' => config_path('larasell-admin.php'),
                __DIR__.'/../../database/admin-migrations/2026_08_17_000001_create_larasell_admin_users_table.php' => database_path('migrations/2026_08_17_000001_create_larasell_admin_users_table.php'),
                __DIR__.'/../../resources/css/admin.css' => resource_path('css/vendor/larasell/admin.css'),
                __DIR__.'/../../resources/js/admin' => resource_path('js/vendor/larasell/admin'),
                __DIR__.'/../../resources/views' => resource_path('views/vendor/larasell-admin'),
                __DIR__.'/../../routes/admin.php' => base_path('routes/larasell-admin.php'),
            ], 'larasell-admin');
        }
    }

    private function configureAuth(): void
    {
        $guard = config('larasell-admin.guard', 'larasell-admin');
        $provider = $guard.'-users';
        $passwords = config('larasell-admin.passwords', $provider);

        config()->set("auth.guards.$guard", config("auth.guards.$guard", [
            'driver' => 'session',
            'provider' => $provider,
        ]));

        config()->set("auth.providers.$provider", config("auth.providers.$provider", [
            'driver' => 'eloquent',
            'model' => config('larasell-admin.models.admin_user', AdminUser::class),
        ]));

        config()->set("auth.passwords.$passwords", config("auth.passwords.$passwords", [
            'provider' => $provider,
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ]));
    }

    private function routeFile(): string
    {
        $publishedRoutes = config('larasell-admin.routes');

        if (is_string($publishedRoutes) && file_exists($publishedRoutes)) {
            return $publishedRoutes;
        }

        return __DIR__.'/../../routes/admin.php';
    }
}
