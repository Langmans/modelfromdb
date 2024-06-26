<?php
declare(strict_types=1);

namespace Langmans\ModelFromDB;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Nette\PhpGenerator\ClassType;

class BaseModelPhpFileGenerator extends AbstractPhpFileGenerator
{
    public function generateForTable(Table $table): PhpFileWithPath
    {
        $class_name = $this->generator->inflectTableNameToClassName($table);
        $this->info("Generating base model for table '{$table->getName()}'");
        $this->info("Class name: $class_name");

        $file = $this->generator->makePhpFile();

        $ns = $file->addNamespace($this->generator->getBaseModelNamespace());

        $class = $ns->addClass($class_name)->setAbstract();

        $trait = $this->generator->getMetaTraitNamespace() . '\\' . $class_name;
        $ns->addUse($trait, 'MetaTrait');
        $class->addTrait($trait);

        if ($base_model_class = $this->generator->getBaseModelClass()) {
            $alias = 'BaseModel';
            $ns->addUse($base_model_class, $alias);
            $class->setExtends($base_model_class);
            $this->debug("Extending $base_model_class as $alias");
        }

        $ns->addUse(ModelMetaInterface::class);

        $class->addImplement(ModelMetaInterface::class);

        $auto_increment_columns = array_map(fn(Column $column) => $column->getName(),
            array_filter($table->getColumns(), fn(Column $column) => $column->getAutoincrement()));

        foreach ($table->getColumns() as $column) {
            $this->generateGetterMethod(class: $class, column: $column, method_type: 'current');

            if (in_array($column->getName(), $auto_increment_columns)) {
                $this->debug("Skipping autoincrement column '{$column->getName()}' from setter generation");
                continue;
            }

            $this->generateSetterMethod(class: $class, column: $column);
            $this->generateGetterMethod(class: $class, column: $column, method_type: 'stored');
            $this->generateGetterMethod(class: $class, column: $column, method_type: 'modified');
            $this->generateGetterMethod(class: $class, column: $column, method_type: 'is_modified');
        }

        // belongs to
        foreach ($table->getForeignKeys() as $fk) {
            $this->generateBelongsTo($class, $fk);
        }

        return new PhpFileWithPath(
            php_file: $file,
            path: $this->generator->getBaseModelDir() . '/' . $class_name . '.php'
        );
    }

    protected function generateGetterMethod(ClassType $class, Column $column, string $method_type): void
    {
        $inflector = $this->generator->getInflector();

        $column_name = $column->getName();

        $method_name = $inflector->camelize(sprintf(match ($method_type) {
            'stored' => 'get_stored_%s',
            'modified' => 'get_modified_%s',
            'current' => 'get_%s',
            'is_modified' => 'is_%s_modified',
        }, $column_name));
        $internal_method_name = $inflector->camelize(match ($method_type) {
            'stored' => 'get_stored',
            'modified' => 'get_modified',
            'current' => 'get_current',
            'is_modified' => 'is_modified',
        });

        $method = $class->addMethod($method_name);
        if ($method_type === 'is_modified') {
            $return_type = 'bool';
        } else {
            $return_type = $this->getGeneratorTypeForDatabaseType($column->getType(), !$column->getNotnull());
        }

        $method->setReturnType($return_type);

        $method->setBody('return $this->?(?);', [$internal_method_name, $column_name]);

        $this->debug("Generated $method_name method", ['$column_name' => $column_name, '$type' => $method_type, '$return_type' => $return_type]);
    }

    protected function generateSetterMethod(ClassType $class, Column $column): void
    {
        $column_name = $column->getName();
        $method_name = $this->generator->getInflector()->camelize('set_' . $column_name);

        $method = $class->addMethod($method_name);
        $method->setReturnType('static');

        $method->setBody('return $this->set(?, $?);', [$column_name, $column_name]);

        $nullable = !$column->getNotnull();

        $param = $method->addParameter($column_name);
        $param->setType($this->getGeneratorTypeForDatabaseType($column->getType(), $nullable));
        $param->setNullable($nullable);

        $default = $column->getDefault();
        if ($default === 'CURRENT_TIMESTAMP') {
            $default = null;
        }
        if ($default !== null || $nullable) {
            $param->setDefaultValue($column->getDefault());
        }

        $this->debug("Generated $method_name method", ['$column_name' => $column_name, '$nullable' => $nullable]);
    }

    protected function generateBelongsTo(ClassType $class, ForeignKeyConstraint $fk)
    {
        $foreignTableName = $fk->getForeignTableName();
        if ($this->generator->shouldOmitTableName($foreignTableName)) {
            $this->warning("Skipping belongs to relation for table '$foreignTableName'");
            return;
        }
        $this->debug('Generating belongs to relation for table ' . $foreignTableName);

//        $foreignTable = $this->generator->getCurrentSchema()->getTable($foreignTableName);
//
//        dd($foreignTable);
//        $class->addMethod('get' . $this->generator->inflectTableNameToClassName($foreignTable))
//            ->setReturnType('Relation')
//            ->setBody('return $this->belongsTo(?, ?);', [$foreignTableName, $fk->getLocalColumns()[0]]);
    }
}