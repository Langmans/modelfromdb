<?php

namespace Langmans\ModelFromDB\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;


class VoidLogger extends AbstractLogger implements LoggerInterface
{
    use LoggerTrait;

    public function log($level, \Stringable|string $message, array $context = []): void
    {

    }
}