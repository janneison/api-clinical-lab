<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabResult;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabResultRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class MySqlLabResultRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE lab_orders (
            id_solicitud_key TEXT PRIMARY KEY,
            id_admision TEXT NOT NULL,
            id_atencion TEXT NULL,
            tipo_documento TEXT NOT NULL,
            identificacion TEXT NOT NULL,
            nombre_paciente TEXT NOT NULL,
            sexo TEXT NOT NULL,
            fecha_nacimiento TEXT NOT NULL,
            centro_salud TEXT NOT NULL,
            fecha_orden TEXT NOT NULL,
            medico_ordena TEXT NOT NULL,
            numero_autorizacion TEXT NULL,
            id_aliado TEXT NULL,
            fecha_envio TEXT NULL,
            porc_ejecucion REAL DEFAULT 0,
            estado_orden TEXT NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE lab_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_solicitud_key TEXT NOT NULL,
            cups TEXT NOT NULL,
            values_json TEXT NOT NULL,
            attachment_path TEXT NULL,
            received_at TEXT NOT NULL
        )');
    }

    public function testPersistsResultPayload(): void
    {
        $orderRepository = new MySqlLabOrderRepository($this->pdo);
        $resultRepository = new MySqlLabResultRepository($this->pdo);

        $orderRepository->save(new LabOrder(
            'REQ-321',
            'ADM-3',
            null,
            'CC',
            '321',
            'Paciente Demo',
            'M',
            new DateTimeImmutable('1985-05-05'),
            'Hospital Central',
            new DateTimeImmutable('2024-01-15 10:00:00'),
            'Dr. House',
            null,
            null,
            null,
            0.0
        ));

        $result = new LabResult('REQ-321', 'C500', ['resultado' => 'Positivo', 'valor' => '10'], '/tmp/file.pdf', new DateTimeImmutable('2024-02-01 12:00:00'));
        $resultRepository->save($result);

        $stored = $this->pdo->query('SELECT * FROM lab_results WHERE id_solicitud_key = "REQ-321"')->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('REQ-321', $stored['id_solicitud_key']);
        $this->assertSame('C500', $stored['cups']);
        $decoded = json_decode($stored['values_json'], true);
        $this->assertSame('Positivo', $decoded['resultado']);
        $this->assertSame('/tmp/file.pdf', $stored['attachment_path']);
    }
}
