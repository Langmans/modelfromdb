<?php

namespace Langmans\ModelFromDB\Logger;

use DateTimeImmutable;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;


class EchoLogger extends AbstractLogger implements LoggerInterface
{
    use LoggerTrait;

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $microtime = microtime(true);
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $microtime));
        $time = $dt->format('H:i:s.u');
        printf("[%s %s] %s %s\n", $level, $time, $message, $context ? json_encode($context) : '');
    }
}