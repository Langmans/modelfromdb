<?php

namespace Langmans\ModelFromDB;

interface ModelMetaInterface
{
    public static function getTableName(): string;

    public static function getColumns(): array;

    public static function getForeignKeys(): array;

    public static function getIndexes(): array;
}