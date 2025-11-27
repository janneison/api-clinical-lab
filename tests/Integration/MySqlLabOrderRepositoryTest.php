<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderDetailRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

class MySqlLabOrderRepositoryTest extends TestCase
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

    public function testSavesAndLoadsOrderWithDetails(): void
    {
        $orderRepository = new MySqlLabOrderRepository($this->pdo);
        $detailRepository = new MySqlLabOrderDetailRepository($this->pdo);

        $order = new LabOrder(
            'REQ-123',
            'ADM-1',
            'ATT-1',
            'CC',
            '100200300',
            'Paciente Demo',
            'M',
            new DateTimeImmutable('1980-01-02'),
            'Hospital Central',
            new DateTimeImmutable('2024-01-10 08:00:00'),
            'Dr. House',
            'AUTH-55',
            'ALLY-9',
            null,
            0.0
        );
        $order->addDetail(new LabOrderDetail('REQ-123', 'ADM-1', 'C123', 'Lab A', null, null, null, null, null, null, null, null));
        $order->addDetail(new LabOrderDetail('REQ-123', 'ADM-1', 'C124', 'Lab B', null, null, null, null, null, null, null, null));

        $orderRepository->save($order);
        $detailRepository->saveMany($order->getDetails());

        $fetched = $orderRepository->findByIdSolicitudKey('REQ-123');

        $this->assertNotNull($fetched);
        $this->assertSame('REQ-123', $fetched->getIdSolicitudKey());
        $this->assertCount(2, $fetched->getDetails());
    }

    public function testUpdatesOrderStatus(): void
    {
        $orderRepository = new MySqlLabOrderRepository($this->pdo);

        $order = new LabOrder(
            'REQ-999',
            'ADM-9',
            null,
            'CC',
            '999',
            'Paciente Demo',
            'F',
            new DateTimeImmutable('1990-01-01'),
            'Hospital Central',
            new DateTimeImmutable('2024-02-01 08:00:00'),
            'Dr. Who',
            null,
            null,
            null,
            0.0
        );

        $orderRepository->save($order);

        $order->markAsSent(new DateTimeImmutable('2024-02-02 09:00:00'));
        $orderRepository->update($order);

        $fetched = $orderRepository->findByIdSolicitudKey('REQ-999');
        $this->assertSame(LabOrder::STATUS_SENT, $fetched?->getEstadoDeLaOrden());
        $this->assertSame('2024-02-02 09:00:00', $fetched?->getFechaEnvio()?->format('Y-m-d H:i:s'));
    }
}
