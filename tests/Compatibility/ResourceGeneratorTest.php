<?php

namespace Encore\Admin\Tests\Compatibility;

use Encore\Admin\Console\ResourceGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResourceGeneratorTest extends TestCase
{
    public function test_mysql_expression_defaults_are_not_copied_as_strings(): void
    {
        config(['database.connections.metadata' => ['driver' => 'mysql', 'host' => 'unused', 'database' => 'unused']]);
        $model = new class extends Model {};
        $model->setConnection('metadata');
        $columns = [
            ['name' => 'token', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'default' => '(uuid())', 'auto_increment' => false, 'generation' => null],
            ['name' => 'status', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'default' => 'pending', 'auto_increment' => false, 'generation' => null],
        ];
        $generator = new class($model, $columns) extends ResourceGenerator {
            public function __construct(Model $model, private array $columns)
            {
                parent::__construct($model);
            }
            protected function getTableColumns()
            {
                return $this->columns;
            }
        };
        $form = $generator->generateForm();
        $this->assertStringContainsString("\$form->text('token', __('Token'));", $form);
        $this->assertStringContainsString("\$form->text('status', __('Status'))->default('pending');", $form);
    }

    public function test_mariadb_string_defaults_are_unquoted_and_sql_expressions_are_not_copied(): void
    {
        config(['database.connections.metadata' => ['driver' => 'mariadb', 'host' => 'unused', 'database' => 'unused']]);
        $model = new class extends Model {};
        $model->setConnection('metadata');
        $columns = [
            ['name' => 'status', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'default' => "'pending'", 'auto_increment' => false, 'generation' => null],
            ['name' => 'token', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'default' => 'uuid()', 'auto_increment' => false, 'generation' => null],
        ];
        $generator = new class($model, $columns) extends ResourceGenerator {
            public function __construct(Model $model, private array $columns)
            {
                parent::__construct($model);
            }
            protected function getTableColumns()
            {
                return $this->columns;
            }
        };
        $form = $generator->generateForm();
        $this->assertStringContainsString("\$form->text('status', __('Status'))->default('pending');", $form);
        $this->assertStringContainsString("\$form->text('token', __('Token'));", $form);
    }

    public function test_generates_resources_using_native_schema_inspection_with_table_prefix(): void
    {
        DB::connection()->setTablePrefix('test_');
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default("O'Reilly\\Books");
            $table->string('email');
            $table->boolean('enabled')->default(false);
            $table->integer('visits')->default(0);
            $table->decimal('price', 8, 2)->default(1.5);
            $table->dateTime('published_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        $model = new class extends Model {
            protected $table = 'articles';
        };
        $generator = new ResourceGenerator($model);
        $form = $generator->generateForm();
        $this->assertStringContainsString("\$form->email('email'", $form);
        $this->assertStringContainsString("\$form->switch('enabled'", $form);
        $this->assertStringContainsString("\$form->number('visits', __('Visits'))->default(0)", $form);
        $this->assertStringContainsString("\$form->decimal('price'", $form);
        $this->assertStringContainsString("\$form->datetime('published_at'", $form);
        $this->assertStringContainsString("\$form->textarea('description'", $form);
        $this->assertStringNotContainsString("('id'", $form);
        $this->assertStringNotContainsString("('created_at'", $form);
        $this->assertStringContainsString('->default('.var_export("O'Reilly\\Books", true).')', $form);
        $this->assertStringContainsString("\$grid->column('id'", $generator->generateGrid());
        $this->assertStringContainsString("\$show->field('created_at'", $generator->generateShow());
        // The generated PHP must remain valid when defaults contain quotes/backslashes.
        $this->assertInstanceOf(\Closure::class, eval('return function ($form) {'.$form.'};'));
    }
}
