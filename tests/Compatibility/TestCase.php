<?php

namespace Encore\Admin\Tests\Compatibility;

use Encore\Admin\AdminServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutDeprecationHandling();
    }

    protected function getPackageProviders($app)
    {
        return [AdminServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return ['Admin' => \Encore\Admin\Facades\Admin::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('admin', require __DIR__.'/../../config/admin.php');
        $app['config']->set('admin.directory', __DIR__.'/fixtures/admin');
        $app['config']->set('admin.bootstrap', __DIR__.'/fixtures/admin/bootstrap.php');
        $app['config']->set('admin.auth.controller', \Encore\Admin\Controllers\AuthController::class);
        $app['config']->set('admin.database.connection', 'testing');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('session.serialization', 'json');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('hashing.bcrypt.rounds', 4);
        $app['config']->set(\Illuminate\Support\Arr::dot($app['config']->get('admin.auth'), 'auth.'));
        $app->useLangPath(__DIR__.'/../../resources/lang');
    }

    protected function migrateAdmin(): void
    {
        require_once __DIR__.'/../../database/migrations/2016_01_04_173148_create_admin_tables.php';
        (new \CreateAdminTables())->up();
        $this->assertTrue(Schema::hasTable(config('admin.database.users_table')));
    }
}
