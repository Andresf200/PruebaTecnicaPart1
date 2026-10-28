<?php

declare(strict_types=1);

namespace App\Domain;

enum Aviso: string
{
    case VueltaDeContador = 'VUELTA_DE_CONTADOR';

    public function mensaje(): string
    {
        return match ($this) {
            self::VueltaDeContador => 'La lectura es inferior a la anterior y se ha interpretado como vuelta del contador.',
        };
    }
}
