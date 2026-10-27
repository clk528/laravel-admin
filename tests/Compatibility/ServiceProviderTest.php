<?php

namespace Encore\Admin\Tests\Compatibility;

use Encore\Admin\AdminServiceProvider;
use Orchestra\Testbench\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [AdminServiceProvider::class];
    }

    public function test_unpublished_config_registers_default_admin_guard(): void
    {
        $this->assertSame('admin', config('admin.route.prefix'));
        $this->assertInstanceOf(\Illuminate\Auth\SessionGuard::class, auth()->guard('admin'));
    }
}
