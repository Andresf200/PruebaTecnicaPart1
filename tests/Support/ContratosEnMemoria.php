<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Contrato;
use App\Domain\ContratoRepository;

final class ContratosEnMemoria implements ContratoRepository
{
    /** @param list<Contrato> $contratos */
    public function __construct(private readonly array $contratos)
    {
    }

    public function contratosPorCups(string $cups): array
    {
        $delCups = array_values(array_filter(
            $this->contratos,
            static fn (Contrato $c): bool => $c->cups === $cups,
        ));
        usort($delCups, static fn (Contrato $a, Contrato $b): int => $a->fechaAlta <=> $b->fechaAlta);

        return $delCups;
    }
}