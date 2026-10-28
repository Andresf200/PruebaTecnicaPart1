<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeImmutable;
use DateTimeInterface;

final class Fechas
{
    public static function desdeBd(mixed $valor): DateTimeImmutable
    {
        return $valor instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($valor)
            : new DateTimeImmutable((string) $valor);
    }
}