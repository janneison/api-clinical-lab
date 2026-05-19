<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Domain\Entity\LabOrderDetail;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class LabOrderDetailTest extends TestCase
{
    public function testExposesAllConstructorValuesThroughGetters(): void
    {
        $sampleDate = new DateTimeImmutable('2024-01-05 10:15:00');
        $resultDate = new DateTimeImmutable('2024-01-06 12:20:00');

        $detail = new LabOrderDetail(
            'REQ-555',
            'ADM-5',
            'C900',
            'Lab Unit',
            $sampleDate,
            'ELISA',
            'Reactivo Y',
            'INV-XYZ',
            'procesado',
            $resultDate,
            'TI',
            '12345'
        );

        $this->assertSame('REQ-555', $detail->getIdSolicitudKey());
        $this->assertSame('ADM-5', $detail->getIdAdmision());
        $this->assertSame('C900', $detail->getCups());
        $this->assertSame('Lab Unit', $detail->getNombreDelLaboratorio());
        $this->assertSame($sampleDate, $detail->getFechaTomaMuestra());
        $this->assertSame('ELISA', $detail->getMetodo());
        $this->assertSame('Reactivo Y', $detail->getReactivo());
        $this->assertSame('INV-XYZ', $detail->getInvima());
        $this->assertSame('procesado', $detail->getEstadoDelResultado());
        $this->assertSame($resultDate, $detail->getFechaResultado());
        $this->assertSame('TI', $detail->getTipoIdentificacionDelBacteriologo());
        $this->assertSame('12345', $detail->getIdentificacionDelBacteriologo());
    }
}
