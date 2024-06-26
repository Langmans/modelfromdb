<?php

namespace Langmans\ModelFromDB;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type as DBALType;
use Nette\PhpGenerator\TraitType;

class  MetaTraitPhpFileGenerator extends AbstractPhpFileGenerator
{
    public function generateForTable(Table $table): PhpFileWithPath
    {
        $trait_name = $this->generator->inflectTableNameToClassName($table);
        $file = $this->generator->makePhpFile();
        $ns = $file->addNamespace($this->generator->getMetaTraitNamespace());

        $trait = $ns->addTrait($trait_name);

        $this->generateMetaMethods($trait, $table);

        return new PhpFileWithPath(
            php_file: $file,
            path: $this->generator->getMetaTraitDir() . '/' . $trait_name . '.php'
        );
    }

    protected function generateMetaMethods(TraitType $class, Table $table): void
    {
        // GetComment
        $class->addMethod('getComment')
            ->setStatic()
            ->setReturnType('string')
            ->setBody('return ?;', [(string)$table->getComment()]);

        // GetTableName
        $table_name = $table->getName();
        $class
            ->addMethod('getTableName')
            ->setStatic()
            ->setReturnType('string')
            ->setBody('return ?;', [$table_name]);
        $this->debug('Generated getTableName method', ['$table_name' => $table_name]);

        // getColumns
        $exported_columns = array_map([$this, 'exportColumn'], $table->getColumns());
        $class
            ->addMethod('getColumns')
            ->setStatic()
            ->setReturnType('array')
            ->setBody('return ?;', [
                array_combine(array_column($exported_columns, 'name'), $exported_columns)
            ]);
        $this->debug('Generated getColumns method', ['$exported_columns' => $exported_columns]);

        // getForeignKeys
        $exported_foreign_keys = array_map([$this, 'exportForeignKey'], $table->getForeignKeys());
        $class
            ->addMethod('getForeignKeys')
            ->setStatic()
            ->setReturnType('array')
            ->setBody('return ?;', [$exported_foreign_keys]);
        $this->debug('Generated getForeignKeys method', ['$exported_foreign_keys' => $exported_foreign_keys]);

        // getIndexes
        $exported_indexes = array_map([$this, 'exportIndex'], $table->getIndexes());
        $class
            ->addMethod('getIndexes')
            ->setStatic()
            ->setReturnType('array')
            ->setBody('return ?;', [$exported_indexes]);
        $this->debug('Generated getIndexes method', ['$exported_indexes' => $exported_indexes]);
    }

    protected function exportColumn(Column $column): array
    {
        $type = $column->getType();
        $column = $column->toArray();
        $column['type'] = DBALType::lookupName($type);
        return $column;
    }

    protected function exportForeignKey(ForeignKeyConstraint $fk): array
    {
        $name = $fk->getName();
        $columns = $fk->getLocalColumns();
        $foreign = ['table' => $fk->getForeignTableName(), 'columns' => $fk->getForeignColumns()];
        $options = $fk->getOptions();
        $events = ['update' => $fk->onUpdate(), 'delete' => $fk->onDelete()];

        return compact('name', 'columns', 'foreign', 'options', 'events');
    }

    protected function exportIndex(Index $index): array
    {
        $name = $index->getName();
        $columns = $index->getUnquotedColumns();
        $options = $index->getOptions();
        $primary = $index->isPrimary();
        $unique = $index->isUnique();
        $simple = $index->isSimpleIndex();

        $flags = $index->getFlags();

        return compact('name', 'columns', 'options', 'flags', 'primary', 'unique', 'simple');
    }
}