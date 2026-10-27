<?php

namespace Encore\Admin\Tests\Compatibility;

use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Auth\Database\AdminTablesSeeder;
use Encore\Admin\Auth\Database\Menu;
use Encore\Admin\Auth\Database\Permission;
use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form\Field\File;
use Encore\Admin\Form\Field\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminIntegrationTest extends TestCase
{
    protected function defineRoutes($router)
    {
        Admin::routes();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
        $this->migrateAdmin();
        (new AdminTablesSeeder())->run();
        Storage::fake('admin');
        config(['admin.upload.disk' => 'admin']);
    }

    public function test_login_page_authentication_and_logout(): void
    {
        $this->get('/admin/auth/roles')->assertRedirect('/admin/auth/login');
        $this->get('/admin/auth/login')->assertOk()->assertSee('username');
        $this->post('/admin/auth/login', ['username' => 'admin', 'password' => 'admin'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs(Administrator::first(), 'admin');
        $this->get('/admin/auth/logout')->assertRedirect('/admin');
        $this->assertGuest('admin');
    }

    public function test_builtin_grids_create_edit_detail_and_menu_pages_render(): void
    {
        $this->actingAs(Administrator::first(), 'admin');
        foreach ([
            '/admin/auth/users', '/admin/auth/users/create', '/admin/auth/users/1/edit', '/admin/auth/users/1',
            '/admin/auth/roles', '/admin/auth/roles/create', '/admin/auth/roles/1/edit', '/admin/auth/roles/1',
            '/admin/auth/permissions', '/admin/auth/permissions/create', '/admin/auth/permissions/1/edit',
            '/admin/auth/menu', '/admin/auth/logs',
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('pjax-container', false);
        }
    }

    public function test_roles_can_be_created_updated_and_deleted_through_forms(): void
    {
        $this->actingAs(Administrator::first(), 'admin');
        $this->withHeader('X-Requested-With', 'XMLHttpRequest');
        $this->postJson('/admin/auth/roles', ['name' => 'Auditor', 'slug' => 'auditor', 'permissions' => [1]])
            ->assertOk()->assertJson(['status' => true]);
        $role = Role::where('slug', 'auditor')->firstOrFail();
        $this->assertTrue($role->permissions()->whereKey(1)->exists());
        $this->putJson('/admin/auth/roles/'.$role->id, ['name' => 'Auditor updated', 'slug' => 'auditor', 'permissions' => [2]])
            ->assertOk()->assertJson(['status' => true]);
        $this->assertSame('Auditor updated', $role->fresh()->name);
        $this->assertSame([2], $role->fresh()->permissions->modelKeys());
        $this->deleteJson('/admin/auth/roles/'.$role->id)->assertOk()->assertJson(['status' => true]);
        $this->assertNull($role->fresh());
        $this->assertDatabaseMissing(config('admin.database.role_permissions_table'), ['role_id' => $role->id]);
    }

    public function test_permissions_and_menu_tree_work_with_modern_eloquent(): void
    {
        $user = Administrator::create(['username' => 'reader', 'name' => 'Reader', 'password' => 'unused']);
        $role = Role::create(['name' => 'Reader', 'slug' => 'reader']);
        $permission = Permission::where('slug', 'dashboard')->firstOrFail();
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);
        $this->assertTrue($user->can('dashboard'));
        $this->assertFalse($user->can('auth.management'));
        $this->assertFalse($user->isAdministrator());
        $this->assertNotEmpty((new Menu())->toTree());
        $this->assertNotEmpty(Menu::selectOptions());
        $user->delete();
        $this->assertDatabaseMissing(config('admin.database.role_users_table'), ['user_id' => $user->id]);
    }

    public function test_file_and_image_fields_store_uploads_with_flysystem_three(): void
    {
        $file = new File('attachment');
        $path = $file->prepare(UploadedFile::fake()->create('report.pdf', 5, 'application/pdf'));
        Storage::disk('admin')->assertExists($path);
        $image = new Image('avatar');
        $imagePath = $image->prepare(UploadedFile::fake()->image('avatar.png'));
        Storage::disk('admin')->assertExists($imagePath);
    }

    public function test_grid_pagination_and_pjax_requests_render(): void
    {
        $this->actingAs(Administrator::first(), 'admin');
        foreach (range(1, 25) as $number) {
            Role::create(['name' => 'Role '.$number, 'slug' => 'role-'.$number]);
        }
        $this->get('/admin/auth/roles?page=2')->assertOk()->assertSee('pagination', false);
        $this->get('/admin/auth/roles?_sort[column]=id&_sort[type]=desc')->assertOk();
        $this->get('/admin/auth/roles', ['X-PJAX' => 'true', 'X-PJAX-CONTAINER' => '#pjax-container'])
            ->assertOk()->assertHeader('X-PJAX-URL', '/admin/auth/roles')->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_sortable_columns_accept_casts_without_dynamic_properties(): void
    {
        $column = (new \Encore\Admin\Grid(new Role()))->column('name');
        $this->assertSame($column, $column->sortable('string'));
        $this->assertSame($column, $column->cast('string'));
    }
}
