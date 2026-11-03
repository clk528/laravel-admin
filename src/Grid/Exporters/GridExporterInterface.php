<?php

namespace Encore\Admin\Grid\Exporters;

use Encore\Admin\Grid;

/**
 * The fluent lifecycle required by Grid::exporter().
 */
interface GridExporterInterface extends ExporterInterface
{
    /**
     * @return $this
     */
    public function setGrid(Grid $grid);

    /**
     * @param string $scope all, page:<number>, or selected:<ids>
     *
     * @return $this
     */
    public function withScope(string $scope);
}
