<?php

namespace Langmans\ModelFromDB;

use Doctrine\DBAL\Schema\Table;

class StubModelPhpFileGenerator extends AbstractPhpFileGenerator
{
    public function generateForTable(Table $table): PhpFileWithPath
    {
        $class_name = $this->generator->inflectTableNameToClassName($table);
        $this->info("Generating stub model for table '{$table->getName()}'");
        $this->info("Class name: $class_name");
        $file = $this->generator->makePhpFile();

        $ns = $file->addNamespace($this->generator->getStubModelNamespace());

        $base_class_name = $this->generator->getBaseModelNamespace() . '\\' . $class_name;
        $ns->addUse($base_class_name, 'BaseModel');

        $ns->addClass($class_name)
            ->setFinal()
            ->setExtends($base_class_name);

        return new PhpFileWithPath(
            php_file: $file,
            path: $this->generator->getStubModelDir() . '/' . $class_name . '.php',
            stub_file: true
        );
    }
}