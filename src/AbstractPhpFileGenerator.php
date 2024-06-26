<?php
declare(strict_types=1);

namespace Langmans\ModelFromDB;

use DateTimeInterface;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type as DBALType;
use Nette\PhpGenerator\Type;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use ReflectionMethod;
use Stringable;

abstract class AbstractPhpFileGenerator implements PhpFileGeneratorInterface
{
    use LoggerAwareTrait;
    use LoggerTrait;

    public function __construct(protected Generator $generator)
    {
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->generator->log($level, $message, $context);
    }

    protected function getGeneratorTypeForDatabaseType(DBALType $dbal_type, bool $nullable): ?string
    {
        if ($dbal_type instanceof StringType) {
            return Type::nullable('string', $nullable);
        }

        $refl = new ReflectionMethod($dbal_type, 'convertToPHPValue');
        $refl_return = $refl->getReturnType();
        if (!$refl_return) {
            return null;
        }
        $refl_return = ltrim((string)$refl_return, '?');

//        dump($refl_return, class_exists($refl_return), is_subclass_of($refl_return, DateTimeInterface::class));

        // DateTime and DateTimeImmutable always become DateTimeInterface
        if (class_exists($refl_return) && is_subclass_of($refl_return, DateTimeInterface::class)) {
            $refl_return = DateTimeInterface::class;
        }

        if ($refl_return === 'mixed') {
            return null;
        }


        if ($nullable) {
            $refl_return = '?' . $refl_return;
        }

        return $refl_return;
    }

}