<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Aviso;
use App\Domain\MotivoRechazo;
use App\Domain\ReglaMonotonia;
use PHPUnit\Framework\TestCase;

final class ReglaMonotoniaTest extends TestCase
{
    private ReglaMonotonia $regla;

    protected function setUp(): void
    {
        $this->regla = new ReglaMonotonia();
    }

    public function testPrimeraLecturaSinVecinasSeAcepta(): void
    {
        $e = $this->regla->evaluar(500, null, null, 5);

        self::assertTrue($e->esAceptada());
        self::assertSame([], $e->avisos);
    }

    public function testIgualOSuperiorALaAnteriorSeAcepta(): void
    {
        self::assertTrue($this->regla->evaluar(1000, 1000, null, 5)->esAceptada());
        self::assertTrue($this->regla->evaluar(1200, 1000, null, 5)->esAceptada());
    }

    public function testEntreLaAnteriorYLaSiguienteSeAcepta(): void
    {
        self::assertTrue($this->regla->evaluar(1100, 1000, 1200, 5)->esAceptada());
    }

    public function testMenorQueLaAnteriorSeRechaza(): void
    {
        $e = $this->regla->evaluar(49999, 50000, null, 5);

        self::assertSame(MotivoRechazo::LecturaDecreciente, $e->rechazo);
    }

    public function testVueltaDeContadorSeAceptaConAviso(): void
    {
        // Contrato 8: 99.812 con 5 dígitos
        $e = $this->regla->evaluar(150, 99812, null, 5);

        self::assertTrue($e->esAceptada());
        self::assertSame([Aviso::VueltaDeContador], $e->avisos);
    }

    public function testVueltaDeContadorConSeisDigitos(): void
    {
        $e = $this->regla->evaluar(4000, 995000, null, 6);

        self::assertSame([Aviso::VueltaDeContador], $e->avisos);
    }

    public function testBajadaDesdeCercaDelTopeSinLlegarAlPrincipioSeRechaza(): void
    {
        // Antes estaba cerca del tope, pero 50000 no es «principio» del contador
        $e = $this->regla->evaluar(50000, 99812, null, 5);

        self::assertSame(MotivoRechazo::LecturaDecreciente, $e->rechazo);
    }

    public function testSuperiorALaSiguienteSeRechaza(): void
    {
        $e = $this->regla->evaluar(300, 100, 200, 5);

        self::assertSame(MotivoRechazo::LecturaSuperiorAPosterior, $e->rechazo);
    }

    public function testLlegadaDesordenadaAntesDeUnaVueltaSeAceptaConAviso(): void
    {
        // Ya existe una lectura posterior tras la vuelta; esta cae justo antes
        $e = $this->regla->evaluar(99900, 99000, 120, 5);

        self::assertTrue($e->esAceptada());
        self::assertSame([Aviso::VueltaDeContador], $e->avisos);
    }
}