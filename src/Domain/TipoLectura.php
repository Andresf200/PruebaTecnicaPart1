<?php

declare(strict_types=1);

namespace App\Domain;

enum TipoLectura: string
{
    case Real = 'real';
    case Estimada = 'estimada';
}