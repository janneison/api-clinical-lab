<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderDetailRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class MySqlLabOrderDetailRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE lab_order_details (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_solicitud_key TEXT NOT NULL,
            id_admision TEXT NOT NULL,
            cups TEXT NOT NULL,
            nombre_laboratorio TEXT NOT NULL,
            fecha_toma_muestra TEXT NULL,
            metodo TEXT NULL,
            reactivo TEXT NULL,
            invima TEXT NULL,
            estado_resultado TEXT NULL,
            fecha_resultado TEXT NULL,
            tipo_id_bacteriologo TEXT NULL,
            id_bacteriologo TEXT NULL
        )');
    }

    public function testPersistsAllDetailFieldsIncludingDates(): void
    {
        $repository = new MySqlLabOrderDetailRepository($this->pdo);

        $repository->saveMany([
            new LabOrderDetail(
                'REQ-555',
                'ADM-5',
                'C900',
                'Lab Integracion',
                new DateTimeImmutable('2024-01-05 10:15:00'),
                'PCR',
                'Reactivo X',
                'INV-123',
                'finalizado',
                new DateTimeImmutable('2024-01-06 12:20:00'),
                'CC',
                '808080'
            ),
        ]);

        $stored = $this->pdo->query('SELECT * FROM lab_order_details WHERE id_solicitud_key = "REQ-555"')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('REQ-555', $stored['id_solicitud_key']);
        $this->assertSame('C900', $stored['cups']);
        $this->assertSame('2024-01-05 10:15:00', $stored['fecha_toma_muestra']);
        $this->assertSame('PCR', $stored['metodo']);
        $this->assertSame('INV-123', $stored['invima']);
        $this->assertSame('2024-01-06 12:20:00', $stored['fecha_resultado']);
        $this->assertSame('CC', $stored['tipo_id_bacteriologo']);
        $this->assertSame('808080', $stored['id_bacteriologo']);
    }
}
