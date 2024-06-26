<?php

namespace Langmans\ModelFromDB;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Langmans\ModelFromDB\Logger\VoidLogger;
use Nette\PhpGenerator\PhpFile;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

class Generator implements LoggerAwareInterface, LoggerInterface
{
    use LoggerAwareTrait, LoggerTrait;

    /** @var array<string,string> */
    protected array $table_aliases = [];

    protected array $omit_table_names = [];
    protected Schema $current_schema;

    public function __construct(
        protected string     $base_model_namespace,
        protected string     $base_model_dir,
        protected string     $stub_model_namespace,
        protected string     $stub_model_dir,
        protected ?string    $meta_trait_namespace = null,
        protected ?string    $meta_trait_dir = null,
        protected ?string    $base_model_class = null,
        ?LoggerInterface     $logger = null,
        protected ?Inflector $inflector = null,
        protected bool       $auto_create_directories = false
    )
    {
        $this->base_model_dir = rtrim($this->base_model_dir, '/');
        $this->meta_trait_dir ??= $this->base_model_dir . '/Meta';
        $this->stub_model_dir = rtrim($this->stub_model_dir, '/');

        $this->meta_trait_namespace ??= $this->base_model_namespace . '\Meta';


        $logger ??= new VoidLogger();
        $this->setLogger($logger);

        $this->inflector ??= InflectorFactory::create()->build();
    }

    public function shouldOmitTableName($table_name): bool
    {
        return in_array($table_name, $this->omit_table_names);
    }

    public function getOmitTableNames(): array
    {
        return $this->omit_table_names;
    }

    public function setOmitTableNames(array $omit_table_names): Generator
    {
        $this->omit_table_names = $omit_table_names;
        return $this;
    }

    public function getBaseModelDir(): string
    {
        return $this->base_model_dir;
    }

    public function getMetaTraitDir(): ?string
    {
        return $this->meta_trait_dir;
    }

    public function getTableAliases(): array
    {
        return $this->table_aliases;
    }

    public function setTableAliases(array $table_aliases): Generator
    {
        $this->table_aliases = [];
        foreach ($table_aliases as $table_name => $alias) {
            $this->setTableAlias($table_name, $alias);
        }
        return $this;
    }

    public function setTableAlias(string $table_name, string $alias): static
    {
        $this->table_aliases[$table_name] = $alias;
        return $this;
    }

    public function setAutoCreateDirectories(bool $auto_create_directories = true): Generator
    {
        $this->auto_create_directories = $auto_create_directories;
        return $this;
    }

    /**
     * @param Schema $schema
     * @param PhpFileGeneratorInterface|null $base_model_generator
     * @param PhpFileGeneratorInterface|null $stub_model_generator ,
     * @param PhpFileGeneratorInterface|null $meta_trait_generator
     * @return void
     */
    #[NoReturn] public function generateFromSchema(
        Schema                    $schema,
        PhpFileGeneratorInterface $base_model_generator = null,
        PhpFileGeneratorInterface $stub_model_generator = null,
        PhpFileGeneratorInterface $meta_trait_generator = null
    ): void
    {
        $this->current_schema = $schema;

        $base_model_generator ??= new BaseModelPhpFileGenerator($this);
        $stub_model_generator ??= new StubModelPhpFileGenerator($this);
        $meta_trait_generator ??= new MetaTraitPhpFileGenerator($this);

        /** @var PhpFileWithPath[] $files */
        $files = [];
        foreach ($schema->getTables() as $i => $table) {
            if ($i) {
                $this->info(str_repeat('=', 80));
            }
            if (in_array($table->getName(), $this->omit_table_names)) {
                $this->info("Skipping table '{$table->getName()}' as it is in the omit list.");
                continue;
            }

            $files[] = $base_model_generator->generateForTable($table);
            $files[] = $stub_model_generator->generateForTable($table);
            $files[] = $meta_trait_generator->generateForTable($table);
        }
        $this_class = static::class;
        foreach ($files as $file_with_path) {
//            "This is a stub model class for table '{$table->getName()}'. You can add your own methods and properties here. It will not be overwritten."

            $file = $file_with_path->getPhpFile();

            if ($file_with_path->isStubFile()) {
                $file->addComment('This is a stub file. You can safely edit this file as it will not be overwritten.');
            } else {
                $file->addComment('You should not edit this file as it is overwritten. Instead, rerun the generator to update it and overwrite methods and properties in the stub file instead.');
            }
            $date = (new DateTimeImmutable)->format('Y-m-d H:i:s e');
            $file->addComment('@generator ' . $this_class);
            $file->addComment('@' . ($file_with_path->isStubFile() ? 'since' : 'date') . ' ' . $date);
//            $file->addComment("This file was generated by $this_class on $date");

            if ($file_with_path->shouldSaveOrOverWrite()) {
                $this->info("Saving file: {$file_with_path->getPath()}");
                try {
                    $file_with_path->save($this->isAutoCreateDirectories());
                    $this->info("File '{$file_with_path->getPath()}' saved successfully.");
                } catch (Exception $e) {
                    $this->critical($e);
                    return;
                }

            } else {
                $this->info("File '{$file_with_path->getPath()}' should not be saved.");
            }
        }
    }

    public function isAutoCreateDirectories(): bool
    {
        return $this->auto_create_directories;
    }

    public function inflectTableNameToClassName(Table $table): string
    {
        $name = $table->getName();
        if (isset($this->table_aliases[$name])) {
            $name = $this->table_aliases[$name];
        }
        $name = $this->inflector->singularize($name);
        return $this->inflector->classify($name);
    }

    public function makePhpFile(): PhpFile
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        return $file;
    }

    public function getStubModelNamespace(): string
    {
        return $this->stub_model_namespace;
    }

    public function getStubModelDir(): string
    {
        return $this->stub_model_dir;
    }

    public function getBaseModelClass(): ?string
    {
        return $this->base_model_class;
    }

    public function getInflector(): ?Inflector
    {
        return $this->inflector;
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    public function getMetaTraitNamespace(): string
    {
        return $this->meta_trait_namespace;
    }

    public function getBaseModelNamespace(): string
    {
        return $this->base_model_namespace;
    }

    public function getCurrentSchema()
    {
        return $this->current_schema;
    }
}