<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests;

use HoangPhamDev\SimpleAdminGenerator\PackageServiceProvider;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('s', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set(
            'database.connections.testing',
            getenv('SAG_TEST_DB_DATABASE')
                ? [
                    'driver' => 'mysql',
                    'host' => getenv('SAG_TEST_DB_HOST') ?: '127.0.0.1',
                    'port' => getenv('SAG_TEST_DB_PORT') ?: '3306',
                    'database' => getenv('SAG_TEST_DB_DATABASE'),
                    'username' => getenv('SAG_TEST_DB_USERNAME'),
                    'password' => getenv('SAG_TEST_DB_PASSWORD'),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                ]
                : [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'prefix' => '',
                ]
        );
        $app['config']->set('sag.prefix', 'admin');
        $app['config']->set('sag.middleware', ['web']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!getenv('SAG_TEST_DB_DATABASE') && !extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for database tests.');
        }

        Route::middleware('web')->get('/admin/dashboard', static fn () => 'dashboard')
            ->name('sag.dashboard');
        Route::middleware('web')->get('/example/{id?}', static fn () => 'example')
            ->name('example.route');

        $this->artisan('migrate')->run();
    }
}
