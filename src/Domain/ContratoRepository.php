<?php

declare(strict_types=1);

namespace App\Domain;

interface ContratoRepository
{
    /**
     * Todos los contratos que ha tenido un CUPS, ordenados por fecha de alta.
     *
     * @return list<Contrato>
     */
    public function contratosPorCups(string $cups): array;
}