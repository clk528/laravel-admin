<?php

namespace Encore\Admin\Tests\Compatibility;

use Encore\Admin\Auth\Database\Role;
use Encore\Admin\Grid;
use Encore\Admin\Grid\Exporter;
use Encore\Admin\Grid\Exporters\CsvExporter;
use Encore\Admin\Grid\Exporters\ExporterInterface;
use Encore\Admin\Grid\Exporters\GridExporterInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;

class ExporterResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetResolver();
    }

    protected function tearDown(): void
    {
        $this->resetResolver();
        parent::tearDown();
    }

    private function resetResolver(): void
    {
        (new ReflectionProperty(Exporter::class, 'exporter'))->setValue(null, null);
        (new ReflectionProperty(Exporter::class, 'drivers'))->setValue(null, []);
    }

    #[DataProvider('scopes')]
    public function test_resolves_standalone_exporters_using_the_legacy_interface(string $scope): void
    {
        $grid = new Grid(new Role());
        $driver = new StandaloneGridExporter();

        $exporter = (new Exporter($grid))->resolve($driver)->withScope($scope);

        $this->assertSame($driver, $exporter);
        $this->assertSame(['table' => 'admin_roles', 'scope' => $scope], $exporter->export());
        $this->assertFalse((new ReflectionProperty($grid->model(), 'usePaginate'))->getValue($grid->model()));
    }

    public function test_resolves_exporters_implementing_the_complete_grid_contract(): void
    {
        $driver = new class extends StandaloneGridExporter implements GridExporterInterface {};
        $exporter = (new Exporter(new Grid(new Role())))->resolve($driver)->withScope('all');

        $this->assertSame(['table' => 'admin_roles', 'scope' => 'all'], $exporter->export());
    }

    #[DataProvider('builtInScopes')]
    public function test_built_in_exporters_keep_the_requested_rows(string $scope, array $expectedIds): void
    {
        $this->migrateAdmin();
        foreach (['First', 'Second', 'Third'] as $name) {
            Role::create(['name' => $name, 'slug' => strtolower($name)]);
        }
        $grid = new Grid(new Role());
        $grid->model()->setPerPage(1);
        $grid->model()->orderBy('id');
        $driver = new CsvExporter();

        $exporter = (new Exporter($grid))->resolve($driver)->withScope($scope);

        $this->assertSame($driver, $exporter);
        $this->assertSame($expectedIds, $exporter->getQuery()->pluck('id')->all());
    }

    public function test_built_in_exporters_implement_the_complete_contract(): void
    {
        $this->assertInstanceOf(GridExporterInterface::class, new CsvExporter());
    }

    #[DataProvider('defaultDrivers')]
    public function test_preserves_default_csv_resolution(?string $driver): void
    {
        $exporter = (new Exporter(new Grid(new Role())))->resolve($driver);

        $this->assertInstanceOf(CsvExporter::class, $exporter);
        $this->assertSame('admin_roles', $exporter->getTable());
    }

    public function test_preserves_registered_driver_names(): void
    {
        Exporter::extend('standalone', StandaloneGridExporter::class);
        $exporter = (new Exporter(new Grid(new Role())))->resolve('standalone')->withScope('all');

        $this->assertSame(['table' => 'admin_roles', 'scope' => 'all'], $exporter->export());
    }

    #[DataProvider('invalidDrivers')]
    public function test_rejects_invalid_drivers_before_array_key_lookup(mixed $driver): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Exporter(new Grid(new Role())))->resolve($driver);
    }

    public static function scopes(): array
    {
        return [['all'], ['page:2'], ['selected:1,3']];
    }

    public static function builtInScopes(): array
    {
        return [['all', [1, 2, 3]], ['page:2', [2]], ['selected:1,3', [1, 3]]];
    }

    public static function defaultDrivers(): array
    {
        return [[null], ['unknown-driver']];
    }

    public static function invalidDrivers(): array
    {
        return [
            'object' => [new \stdClass()],
            'array' => [[]],
            'export only' => [new class implements ExporterInterface {
                public function export() {}
            }],
            'missing scope' => [new class implements ExporterInterface {
                public function setGrid(Grid $grid) { return $this; }
                public function export() {}
            }],
            'missing grid' => [new class implements ExporterInterface {
                public function withScope(string $scope) { return $this; }
                public function export() {}
            }],
        ];
    }
}

// An independent application exporter: deliberately does not extend AbstractExporter.
class StandaloneGridExporter implements ExporterInterface
{
    private ?Grid $grid = null;
    private string $scope = 'all';

    public function __construct(?Grid $grid = null)
    {
        if ($grid !== null) {
            $this->setGrid($grid);
        }
    }

    public function setGrid(Grid $grid): static
    {
        $this->grid = $grid;

        return $this;
    }

    public function withScope(string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function export(): array
    {
        return ['table' => $this->grid->model()->getOriginalModel()->getTable(), 'scope' => $this->scope];
    }
}
