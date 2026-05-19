<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Domain\Entity\ExamParameter;
use PHPUnit\Framework\TestCase;

class ExamParameterEntityTest extends TestCase
{
    private function makeParam(
        ?float $min,
        ?float $max,
        string $sexo = '*',
        bool   $obligatorio = false
    ): ExamParameter {
        return new ExamParameter(1, '903820', 'hb', 'Hemoglobina', 'g/dL', $min, $max, $sexo, null, null, $obligatorio, 1, true);
    }

    public function testFlagNormalWhenValueInRange(): void
    {
        $param = $this->makeParam(13.5, 17.5);
        $this->assertSame('normal', $param->calcularFlag(15.0));
    }

    public function testFlagAltoWhenValueAboveMax(): void
    {
        $param = $this->makeParam(13.5, 17.5);
        $this->assertSame('alto', $param->calcularFlag(18.0));
    }

    public function testFlagBajoWhenValueBelowMin(): void
    {
        $param = $this->makeParam(13.5, 17.5);
        $this->assertSame('bajo', $param->calcularFlag(12.0));
    }

    public function testFlagCriticoWhenValueFarAboveMax(): void
    {
        // Crítico: > max * 1.3  →  17.5 * 1.3 = 22.75
        $param = $this->makeParam(13.5, 17.5);
        $this->assertSame('critico', $param->calcularFlag(23.0));
    }

    public function testFlagCriticoWhenValueFarBelowMin(): void
    {
        // Crítico: < min * 0.7  →  13.5 * 0.7 = 9.45
        $param = $this->makeParam(13.5, 17.5);
        $this->assertSame('critico', $param->calcularFlag(9.0));
    }

    public function testFlagIndeterminadoWhenNoRanges(): void
    {
        $param = $this->makeParam(null, null);
        $this->assertSame('indeterminado', $param->calcularFlag(99.0));
    }

    public function testFlagNormalWhenOnlyMaxDefined(): void
    {
        // Solo max definido (ej: colesterol total < 200)
        $param = $this->makeParam(null, 200.0);
        $this->assertSame('normal', $param->calcularFlag(185.0));
    }

    public function testFlagAltoWhenOnlyMaxDefinedAndExceeded(): void
    {
        $param = $this->makeParam(null, 200.0);
        $this->assertSame('alto', $param->calcularFlag(210.0));
    }

    public function testFlagNormalWhenOnlyMinDefined(): void
    {
        // Solo min definido (ej: HDL > 40)
        $param = $this->makeParam(40.0, null);
        $this->assertSame('normal', $param->calcularFlag(55.0));
    }

    public function testFlagBajoWhenOnlyMinDefinedAndBelowIt(): void
    {
        $param = $this->makeParam(40.0, null);
        $this->assertSame('bajo', $param->calcularFlag(35.0));
    }

    public function testGettersReturnCorrectValues(): void
    {
        $param = new ExamParameter(
            42, '903820', 'wbc', 'Leucocitos', '10³/µL',
            4.5, 11.0, 'M', 18, 65, true, 1, true
        );

        $this->assertSame(42,       $param->getId());
        $this->assertSame('903820', $param->getCups());
        $this->assertSame('wbc',    $param->getCodigo());
        $this->assertSame('Leucocitos', $param->getNombre());
        $this->assertSame('10³/µL', $param->getUnidad());
        $this->assertSame(4.5,      $param->getValorMinRef());
        $this->assertSame(11.0,     $param->getValorMaxRef());
        $this->assertSame('M',      $param->getSexo());
        $this->assertSame(18,       $param->getEdadMin());
        $this->assertSame(65,       $param->getEdadMax());
        $this->assertTrue($param->isObligatorio());
        $this->assertSame(1,        $param->getOrden());
        $this->assertTrue($param->isActivo());
    }
}
