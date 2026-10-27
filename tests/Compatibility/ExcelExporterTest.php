<?php

namespace Encore\Admin\Tests\Compatibility;

use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Exporters\ExcelExporter;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Storage;

class ExcelExporterTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), \Maatwebsite\Excel\ExcelServiceProvider::class];
    }

    public function test_exports_a_grid_to_xlsx_with_excel_four(): void
    {
        $this->migrateAdmin();
        Role::create(['name' => 'Auditor', 'slug' => 'auditor']);
        $exporter = new class(new Grid(new Role())) extends ExcelExporter {
            protected $fileName = 'roles.xlsx';
            protected $columns = ['id' => 'ID', 'name' => 'Name'];
        };
        $this->assertSame(['ID', 'Name'], $exporter->headings());
        $this->assertSame('Auditor', $exporter->query()->first()->name);
        $this->assertStringStartsWith('PK', $exporter->raw(Excel::XLSX));
        Storage::fake('exports');
        $this->assertTrue($exporter->store('roles.xlsx', 'exports'));
        Storage::disk('exports')->assertExists('roles.xlsx');
        $response = $exporter->download();
        $this->assertStringContainsString('roles.xlsx', $response->headers->get('Content-Disposition'));
        $response->deleteFileAfterSend(true);
        unlink($response->getFile()->getPathname());
    }

    public function test_exports_without_an_explicit_column_selection(): void
    {
        $this->migrateAdmin();
        Role::create(['name' => 'Reader', 'slug' => 'reader']);
        $exporter = new class(new Grid(new Role())) extends ExcelExporter {};
        $this->assertSame('Reader', $exporter->query()->first()->name);
        $this->assertStringStartsWith('PK', $exporter->raw(Excel::XLSX));
    }
}
