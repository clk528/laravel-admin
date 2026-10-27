<?php

namespace Encore\Admin\Console;

use Illuminate\Database\Eloquent\Model;

class ResourceGenerator
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $formats = [
        'form_field'  => "\$form->%s('%s', __('%s'))",
        'show_field'  => "\$show->field('%s', __('%s'))",
        'grid_column' => "\$grid->column('%s', __('%s'))",
    ];

    /**
     * @var array
     */
    protected $fieldTypeMapping = [
        'ip'       => 'ip',
        'email'    => 'email|mail',
        'password' => 'password|pwd',
        'url'      => 'url|link|src|href',
        'mobile'   => 'mobile|phone',
        'color'    => 'color|rgb',
        'image'    => 'image|img|avatar|pic|picture|cover',
        'file'     => 'file|attachment',
    ];

    /**
     * ResourceGenerator constructor.
     *
     * @param mixed $model
     */
    public function __construct($model)
    {
        $this->model = $this->getModel($model);
    }

    /**
     * @param mixed $model
     *
     * @return mixed
     */
    protected function getModel($model)
    {
        if ($model instanceof Model) {
            return $model;
        }

        if (!class_exists($model) || !is_string($model) || !is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException("Invalid model [$model] !");
        }

        return new $model();
    }

    /**
     * @return string
     */
    public function generateForm()
    {
        $reservedColumns = $this->getReservedColumns();

        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];
            if (in_array($name, $reservedColumns)) {
                continue;
            }
            $type = $this->getColumnType($column);
            $defaultValue = $this->formatDefault($column);

            // set column fieldType and defaultValue
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $fieldType = 'switch';
                    break;
                case 'json':
                case 'array':
                case 'object':
                    $fieldType = 'text';
                    break;
                case 'string':
                    $fieldType = 'text';
                    foreach ($this->fieldTypeMapping as $type => $regex) {
                        if (preg_match("/^($regex)$/i", $name) !== 0) {
                            $fieldType = $type;
                            break;
                        }
                    }
                    break;
                case 'integer':
                case 'bigint':
                case 'smallint':
                    $fieldType = 'number';
                    break;
                case 'decimal':
                case 'float':
                case 'real':
                    $fieldType = 'decimal';
                    break;
                case 'datetime':
                    $fieldType = 'datetime';
                    $defaultValue = $defaultValue ?? "date('Y-m-d H:i:s')";
                    break;
                case 'date':
                    $fieldType = 'date';
                    $defaultValue = $defaultValue ?? "date('Y-m-d')";
                    break;
                case 'time':
                    $fieldType = 'time';
                    $defaultValue = $defaultValue ?? "date('H:i:s')";
                    break;
                case 'text':
                case 'blob':
                    $fieldType = 'textarea';
                    break;
                default:
                    $fieldType = 'text';
            }

            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['form_field'], $fieldType, $name, $label);

            if ($defaultValue !== null) {
                $output .= "->default({$defaultValue})";
            }

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateShow()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];

            // set column label
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['show_field'], $name, $label);

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateGrid()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['grid_column'], $name, $label);
            $output .= ";\r\n";
        }

        return $output;
    }

    protected function getReservedColumns()
    {
        return [
            $this->model->getKeyName(),
            $this->model->getCreatedAtColumn(),
            $this->model->getUpdatedAtColumn(),
            'deleted_at',
        ];
    }

    /**
     * Get columns of a giving model.
     *
     * @return array
     */
    protected function getTableColumns()
    {
        return $this->model->getConnection()->getSchemaBuilder()->getColumns($this->model->getTable());
    }

    protected function getColumnType(array $column): string
    {
        $type = strtolower($column['type_name']);

        if ($type === 'tinyint' && preg_match('/^tinyint\(1\)(?:\s|$)/i', $column['type'])) {
            return 'boolean';
        }

        return match ($type) {
            'varchar', 'nvarchar', 'char', 'nchar', 'character varying', 'bpchar', 'enum', 'set', 'uuid' => 'string',
            'int', 'int2', 'int4', 'int8', 'mediumint', 'tinyint', 'serial', 'bigserial' => 'integer',
            'numeric', 'double', 'double precision', 'float4', 'float8', 'money' => 'decimal',
            'timestamp', 'timestamptz', 'datetime2', 'smalldatetime' => 'datetime',
            'timetz' => 'time',
            'tinytext', 'mediumtext', 'longtext', 'ntext' => 'text',
            'jsonb' => 'json',
            default => $type,
        };
    }

    /**
     * Convert literal database defaults to valid PHP, leaving SQL expressions to the database.
     */
    protected function formatDefault(array $column): ?string
    {
        $value = $column['default'];

        if ($value === null || $column['auto_increment']) {
            return null;
        }

        $driver = $this->model->getConnection()->getDriverName();
        $type = $this->getColumnType($column);
        $quoted = false;

        // MySQL exposes expression defaults in parentheses, without a retained DEFAULT_GENERATED flag.
        if ($driver === 'mysql' && is_string($value)
            && str_starts_with($value, '(') && str_ends_with($value, ')')) {
            return null;
        }

        if (is_string($value) && $driver !== 'mysql') {
            $value = trim($value);
            // PostgreSQL includes type casts; SQLite doubles embedded quotes.
            if (preg_match("/^'(.*)'(?:::[a-zA-Z_][a-zA-Z_0-9 .\\[\\]]*)?$/s", $value, $matches)) {
                $value = str_replace("''", "'", $matches[1]);
                $quoted = true;
            } else {
                $value = preg_replace('/::[a-zA-Z_][a-zA-Z_0-9 .]*$/', '', $value);
                $value = trim($value, '()');
            }
        }

        if (in_array($type, ['boolean', 'bool'], true)) {
            return match (strtolower((string) $value)) {
                '1', 'true' => 'true',
                '0', 'false' => 'false',
                default => null,
            };
        }

        if (in_array($type, ['integer', 'bigint', 'smallint', 'decimal', 'float', 'real'], true)) {
            return is_numeric($value) ? (string) $value : null;
        }

        if ($quoted || ($driver === 'mysql'
            && !($column['generation'] ?? null)
            && !in_array($type, ['datetime', 'date', 'time'], true))) {
            return var_export($value, true);
        }

        return null;
    }

    /**
     * Format label.
     *
     * @param string $value
     *
     * @return string
     */
    protected function formatLabel($value)
    {
        return ucfirst(str_replace(['-', '_'], ' ', $value));
    }
}
