<?php

namespace Langmans\ModelFromDB;

use Nette\PhpGenerator\PhpFile;
use RuntimeException;

class PhpFileWithPath
{
    public function __construct(
        protected PhpFile $php_file,
        protected string  $path,
        protected bool    $stub_file = false
    )
    {

    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPhpFile(): PhpFile
    {
        return $this->php_file;
    }

    public function shouldSaveOrOverWrite(): bool
    {
        return !$this->isStubFile() || !file_exists($this->path);
    }

    public function isStubFile(): bool
    {
        return $this->stub_file;
    }

    public function save(bool $auto_create_directories = true): void
    {
        if ($auto_create_directories) {
            $dir = dirname($this->path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        error_get_last();
        if (!@file_put_contents($this->path, (string)$this->php_file)) {
            $err = error_get_last();
            throw new RuntimeException("Failed to save file: {$this->path}. Error: {$err['message']}");
        }
    }
}