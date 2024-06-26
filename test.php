<?php

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Schema\Schema;
use Langmans\ModelFromDB\Generator;
use Langmans\ModelFromDB\Logger\EchoLogger;

/** @var ClassLoader $loader */
$loader = require_once __DIR__ . '/vendor/autoload.php';

$schema = new Schema();

$user_table = $schema->createTable('dmg_users');
$user_table->addColumn('id', 'bigint')->setAutoincrement(true)->setUnsigned(true);
$user_table->addColumn('email', 'string')->setDefault('default@test.nu')->setNotnull(false);
$user_table->addColumn('password', 'string');
$user_table->addColumn('created_at', 'datetime');
$user_table->addColumn('updated_at', 'datetime');
$user_table->setPrimaryKey(['id']);

$post_table = $schema->createTable('posts');
$post_table->addColumn('id', 'integer')->setAutoincrement(true)->setUnsigned(true);
$post_table->addColumn('title', 'string')->setNotnull(false);
$post_table->addColumn('content', 'text');
$post_table->addColumn('created_by', 'integer');
$post_table->addColumn('created_at', 'datetime')->setDefault('CURRENT_TIMESTAMP');
$post_table->addColumn('updated_at', 'datetime')->setDefault('CURRENT_TIMESTAMP');
$post_table->setPrimaryKey(['id']);

$post_table->addForeignKeyConstraint('user', array('created_by'), array('id'))->columnsAreIndexed(['created_by']);


$dummy_table = $schema->createTable('dummy')->setComment('This is a dummy table');
$dummy_table->addColumn('id', 'integer')->setAutoincrement(true)->setUnsigned(true);
$dummy_table->setPrimaryKey(['id']);

$model_generator = new Generator(
    base_model_namespace: "Dummy\\Db\\Generated\\Model",
    base_model_dir: __DIR__ . "/out/Db/Generated/Model",
    stub_model_namespace: "Dummy\\Db\\Stub\\Model",
    stub_model_dir: __DIR__ . "/out/Db/Stub/Model",
    meta_trait_namespace: "Dummy\\Db\\Generated\\Meta",
    meta_trait_dir: __DIR__ . "/out/Db/Generated/Meta",
    logger: new EchoLogger(),
    auto_create_directories: true
);

$post_table->addColumn('dummy_id', 'integer')->setNotnull(false);
$post_table->addForeignKeyConstraint(foreignTableName: 'dummy', localColumnNames: ['dummy_id'], foreignColumnNames: ['id']);

$model_generator->setTableAlias('dmg_users', 'users');
$model_generator->setOmitTableNames(['dummy']);

$model_generator->generateFromSchema($schema);