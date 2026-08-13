<?php
namespace HoangPhamDev\SimpleAdminGenerator;

use HoangPhamDev\SimpleAdminGenerator\Console\GenerateControllerCommand;
use HoangPhamDev\SimpleAdminGenerator\Console\GenerateHomeControllerCommand;
use HoangPhamDev\SimpleAdminGenerator\Console\GenerateUiCommand;
use HoangPhamDev\SimpleAdminGenerator\Console\InstallCommand;
use HoangPhamDev\SimpleAdminGenerator\Console\SeedAdminCommand;
use HoangPhamDev\SimpleAdminGenerator\Console\SeedMenuCommand;
use HoangPhamDev\SimpleAdminGenerator\Http\Middleware\AuthAdmin;
use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'sag');
    }

    public function boot()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
        $this->loadViewsFrom(__DIR__.'/resources/views', 'sag');
        if ($this->app->runningInConsole()) {
            // Publish the package configuration to the host application.
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('sag.php'),
            ], 'sag-config');

            // Publish assets
            $this->publishes([
                __DIR__.'/resources/assets' => public_path('sag'),
            ], 'assets');
        }
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('admin', AuthAdmin::class);

        Config::set('auth.guards.admin', [
            'driver' => 'session',
            'provider' => 'admin',
        ]);
        Config::set('auth.providers.admin', [
            'driver' => 'eloquent',
            'model' => \HoangPhamDev\SimpleAdminGenerator\Models\Admin::class,
        ]);

        View::composer('sag.layouts.sidebar', function ($view) {
            $view->with(
                'sagMenuItems',
                $this->app->make(AdminMenuManager::class)->sidebarTree()
            );
        });

        $this->consoleConfiguration();
    }



    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('sag.prefix', 'admin'),
            'middleware' => config('sag.middleware', ['web']),
        ];
    }

    protected function consoleConfiguration(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                GenerateControllerCommand::class,
                GenerateUiCommand::class,
                GenerateHomeControllerCommand::class,
                SeedAdminCommand::class,
                SeedMenuCommand::class,
            ]);
        }
    }
}
