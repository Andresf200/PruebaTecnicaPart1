<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class ResolutorContrato
{
    /**
     * Devuelve el contrato del CUPS vigente en la fecha, o null si ninguno lo está.
     * Si hubiera solapes (dato inconsistente), gana el primero de la lista.
     *
     * @param list<Contrato> $contratosDelCups
     */
    public function resolver(array $contratosDelCups, DateTimeImmutable $fecha): ?Contrato
    {
        foreach ($contratosDelCups as $contrato) {
            if ($contrato->estaVigenteEn($fecha)) {
                return $contrato;
            }
        }

        return null;
    }
}