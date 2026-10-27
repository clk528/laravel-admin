<?php

namespace Encore\Admin\Grid\Exporters;

use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Exceptions\NoFilenameGivenException;
use Maatwebsite\Excel\Exceptions\NoFilePathGivenException;
use Maatwebsite\Excel\Exporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class ExcelExporter extends AbstractExporter implements FromQuery, WithHeadings
{
    // Keep the legacy untyped filename property usable by existing admin exporters.
    protected $fileName;

    /**
     * @var array
     */
    protected $headings = [];

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @return array
     */
    public function headings(): array
    {
        if (!empty($this->columns)) {
            return array_values($this->columns);
        }

        return $this->headings;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function query(): QueryBuilder|EloquentBuilder|Relation
    {
        $query = $this->getQuery();
        if ($query instanceof Model) {
            $query = $query->newQuery();
        }

        if (!empty($this->columns)) {
            $columns = array_keys($this->columns);

            $eagerLoads = $query instanceof EloquentBuilder ? array_keys($query->getEagerLoads()) : [];

            $columns = collect($columns)->reject(function ($column) use ($eagerLoads) {
                return Str::contains($column, '.') || in_array($column, $eagerLoads);
            });

            return $query->select($columns->toArray());
        }

        return $query;
    }

    public function download(?string $fileName = null, ?string $writerType = null, ?array $headers = null): BinaryFileResponse
    {
        $fileName = $fileName ?? $this->fileName;

        if ($fileName === null) {
            throw new NoFilenameGivenException();
        }

        return app(Exporter::class)->download($this, $fileName, $writerType ?? $this->writerType ?? null, $headers ?? $this->headers ?? []);
    }

    public function store(?string $filePath = null, ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): bool|PendingDispatch|PendingBatch
    {
        $filePath = $filePath ?? $this->filePath ?? null;

        if ($filePath === null) {
            throw NoFilePathGivenException::export();
        }

        return app(Exporter::class)->store($this, $filePath, $disk ?? $this->disk ?? null, $writerType ?? $this->writerType ?? null, $diskOptions ?: ($this->diskOptions ?? []));
    }

    public function queue(?string $filePath = null, ?string $disk = null, ?string $writerType = null, mixed $diskOptions = []): PendingDispatch|PendingBatch
    {
        $filePath = $filePath ?? $this->filePath ?? null;

        if ($filePath === null) {
            throw NoFilePathGivenException::export();
        }

        return app(Exporter::class)->queue($this, $filePath, $disk ?? $this->disk ?? null, $writerType ?? $this->writerType ?? null, $diskOptions ?: ($this->diskOptions ?? []));
    }

    public function raw(?string $writerType = null): string
    {
        return app(Exporter::class)->raw($this, $writerType ?? $this->writerType ?? null);
    }

    public function toResponse($request): BinaryFileResponse
    {
        return $this->download();
    }

    /**
     * {@inheritdoc}
     */
    public function export()
    {
        $this->download($this->fileName)->prepare(request())->send();

        exit;
    }
}
