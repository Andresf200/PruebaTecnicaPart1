<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Reloj;
use DateTimeImmutable;

final class RelojFijo implements Reloj
{
    public function __construct(private readonly DateTimeImmutable $instante)
    {
    }

    public function ahora(): DateTimeImmutable
    {
        return $this->instante;
    }
}