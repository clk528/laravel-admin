<?php

namespace Encore\Admin\Grid;

use Encore\Admin\Grid;
use Encore\Admin\Grid\Exporters\CsvExporter;
use Encore\Admin\Grid\Exporters\ExporterInterface;
use InvalidArgumentException;

class Exporter
{
    /**
     * Export scope constants.
     */
    const SCOPE_ALL = 'all';
    const SCOPE_CURRENT_PAGE = 'page';
    const SCOPE_SELECTED_ROWS = 'selected';

    /**
     * @var Grid
     */
    protected $grid;

    /**
     * Available exporter drivers.
     *
     * @var array
     */
    protected static $drivers = [];

    /**
     * Export query name.
     *
     * @var string
     */
    public static $queryName = '_export_';

    /**
     * @var ExporterInterface
     */
    protected static $exporter;

    /**
     * Create a new Exporter instance.
     *
     * @param Grid $grid
     */
    public function __construct(Grid $grid)
    {
        $this->grid = $grid;

        $this->grid->model()->usePaginate(false);
    }

    /**
     * Set export query name.
     *
     * @param $name
     */
    public static function setQueryName($name)
    {
        static::$queryName = $name;
    }

    /**
     * Extends new exporter driver.
     *
     * @param $driver
     * @param $extend
     */
    public static function extend($driver, $extend)
    {
        static::$drivers[$driver] = $extend;
    }

    /**
     * Resolve export driver.
     *
     * @param string|ExporterInterface|null $driver
     *
     * @return ExporterInterface
     */
    public function resolve($driver)
    {
        if ($driver instanceof ExporterInterface) {
            // Also accept existing standalone exporters using the original interface.
            if (!is_callable([$driver, 'setGrid']) || !is_callable([$driver, 'withScope'])) {
                throw new InvalidArgumentException(
                    'Grid exporters must provide public setGrid() and withScope() methods. Implement GridExporterInterface for the complete contract.'
                );
            }

            return $driver->setGrid($this->grid);
        }

        if ($driver !== null && !is_string($driver)) {
            throw new InvalidArgumentException('An export driver must be a name, an ExporterInterface instance, or null.');
        }

        return $this->getExporter($driver ?? '');
    }

    /**
     * Get export driver.
     *
     * @param string $driver
     *
     * @return ExporterInterface
     */
    protected function getExporter($driver)
    {
        if (static::$exporter) {
            return static::$exporter;
        }

        if (!array_key_exists($driver, static::$drivers)) {
            return static::$exporter = $this->getDefaultExporter();
        }

        return static::$exporter = new static::$drivers[$driver]($this->grid);
    }

    /**
     * Get default exporter.
     *
     * @return CsvExporter
     */
    public function getDefaultExporter()
    {
        return new CsvExporter($this->grid);
    }

    /**
     * Format query for export url.
     *
     * @param int  $scope
     * @param null $args
     *
     * @return array
     */
    public static function formatExportQuery($scope = '', $args = null)
    {
        $query = '';

        if ($scope == static::SCOPE_ALL) {
            $query = 'all';
        }

        if ($scope == static::SCOPE_CURRENT_PAGE) {
            $query = "page:$args";
        }

        if ($scope == static::SCOPE_SELECTED_ROWS) {
            $query = "selected:$args";
        }

        return [static::$queryName => $query];
    }
}
