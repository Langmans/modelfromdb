<?php

namespace Langmans\ModelFromDB;

use Doctrine\DBAL\Schema\Table;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

interface PhpFileGeneratorInterface extends LoggerAwareInterface, LoggerInterface
{
    public function __construct(Generator $generator);

    public function generateForTable(Table $table): PhpFileWithPath;
}