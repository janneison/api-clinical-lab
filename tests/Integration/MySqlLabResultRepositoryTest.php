<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabResult;
use ClinicalLab\Domain\Entity\LabResultValue;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabResultRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabResultValueRepository;
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
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

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

        $this->pdo->exec('CREATE TABLE exam_types (
            cups TEXT PRIMARY KEY,
            nombre TEXT NOT NULL,
            descripcion TEXT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->pdo->exec('CREATE TABLE exam_parameters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cups TEXT NOT NULL,
            codigo TEXT NOT NULL,
            nombre TEXT NOT NULL,
            unidad TEXT NULL,
            valor_min_ref REAL NULL,
            valor_max_ref REAL NULL,
            sexo TEXT NOT NULL DEFAULT \'*\',
            edad_min INTEGER NULL,
            edad_max INTEGER NULL,
            obligatorio INTEGER NOT NULL DEFAULT 0,
            orden INTEGER NOT NULL DEFAULT 0,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->pdo->exec('CREATE TABLE lab_result_values (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lab_result_id INTEGER NOT NULL,
            parameter_id INTEGER NOT NULL,
            valor_numerico REAL NULL,
            valor_texto TEXT NULL,
            flag TEXT NOT NULL DEFAULT \'indeterminado\'
        )');

        // Orden base para FK
        $this->pdo->exec("INSERT INTO lab_orders VALUES (
            'REQ-321','ADM-3',NULL,'CC','321','Paciente Demo','M',
            '1985-05-05','Hospital Central','2024-01-15 10:00:00',
            'Dr. House',NULL,NULL,NULL,0,'pending'
        )");
    }

    // ── LabResultRepository ───────────────────────────────────────────────────

    public function testPersistsResultAndReturnsId(): void
    {
        $repo   = new MySqlLabResultRepository($this->pdo);
        $result = new LabResult('REQ-321', 'C500', ['resultado' => 'Positivo'], '/tmp/file.pdf', new DateTimeImmutable('2024-02-01 12:00:00'));

        $id = $repo->save($result);

        $this->assertGreaterThan(0, $id);

        $row = $this->pdo->query('SELECT * FROM lab_results WHERE id_solicitud_key = "REQ-321"')->fetch();
        $this->assertSame('C500', $row['cups']);
        $this->assertSame('Positivo', json_decode($row['values_json'], true)['resultado']);
        $this->assertSame('/tmp/file.pdf', $row['attachment_path']);
    }

    public function testFindIdByOrderAndCups(): void
    {
        $repo = new MySqlLabResultRepository($this->pdo);
        $id   = $repo->save(new LabResult('REQ-321', 'C500', ['resultado' => 'x'], null, new DateTimeImmutable()));

        $found = $repo->findIdByOrderAndCups('REQ-321', 'C500');
        $this->assertSame($id, $found);
    }

    public function testFindIdByOrderAndCupsReturnsNullWhenNotFound(): void
    {
        $repo = new MySqlLabResultRepository($this->pdo);
        $this->assertNull($repo->findIdByOrderAndCups('REQ-321', 'GHOST'));
    }

    public function testFindAllByOrderReturnsAllResults(): void
    {
        $repo = new MySqlLabResultRepository($this->pdo);
        $repo->save(new LabResult('REQ-321', 'C500', ['resultado' => 'A'], null, new DateTimeImmutable()));
        $repo->save(new LabResult('REQ-321', 'C501', ['resultado' => 'B'], null, new DateTimeImmutable()));

        $rows = $repo->findAllByOrder('REQ-321');

        $this->assertCount(2, $rows);
        $this->assertSame('C500', $rows[0]['cups']);
        $this->assertSame('C501', $rows[1]['cups']);
    }

    public function testFindAllByOrderReturnsEmptyWhenNoResults(): void
    {
        $repo = new MySqlLabResultRepository($this->pdo);
        $this->assertSame([], $repo->findAllByOrder('REQ-321'));
    }

    // ── LabResultValueRepository ──────────────────────────────────────────────

    public function testSavesAndFindsResultValues(): void
    {
        $this->pdo->exec("INSERT INTO exam_parameters
            (cups, codigo, nombre, unidad, valor_min_ref, valor_max_ref, sexo, obligatorio, orden, activo)
            VALUES ('903820','hb','Hemoglobina','g/dL',13.5,17.5,'*',1,1,1)");

        $paramId = (int) $this->pdo->lastInsertId();

        $resultRepo = new MySqlLabResultRepository($this->pdo);
        $labResultId = $resultRepo->save(
            new LabResult('REQ-321', '903820', ['hb' => '14.5'], null, new DateTimeImmutable())
        );

        $valueRepo = new MySqlLabResultValueRepository($this->pdo);
        $valueRepo->save(new LabResultValue(0, $labResultId, $paramId, 14.5, null, 'normal'));

        $values = $valueRepo->findByLabResultId($labResultId);

        $this->assertCount(1, $values);
        $this->assertSame(14.5, $values[0]->getValorNumerico());
        $this->assertSame('normal', $values[0]->getFlag());
        $this->assertNull($values[0]->getValorTexto());
    }

    public function testSavesTextualResultValue(): void
    {
        $this->pdo->exec("INSERT INTO exam_parameters
            (cups, codigo, nombre, unidad, valor_min_ref, valor_max_ref, sexo, obligatorio, orden, activo)
            VALUES ('904200','color','Color orina',NULL,NULL,NULL,'*',0,1,1)");

        $paramId     = (int) $this->pdo->lastInsertId();
        $resultRepo  = new MySqlLabResultRepository($this->pdo);
        $labResultId = $resultRepo->save(
            new LabResult('REQ-321', '904200', ['color' => 'Amarillo'], null, new DateTimeImmutable())
        );

        $valueRepo = new MySqlLabResultValueRepository($this->pdo);
        $valueRepo->save(new LabResultValue(0, $labResultId, $paramId, null, 'Amarillo', 'indeterminado'));

        $values = $valueRepo->findByLabResultId($labResultId);

        $this->assertCount(1, $values);
        $this->assertNull($values[0]->getValorNumerico());
        $this->assertSame('Amarillo', $values[0]->getValorTexto());
        $this->assertSame('indeterminado', $values[0]->getFlag());
    }

    public function testFindByLabResultIdReturnsEmptyWhenNoValues(): void
    {
        $resultRepo  = new MySqlLabResultRepository($this->pdo);
        $labResultId = $resultRepo->save(
            new LabResult('REQ-321', 'C999', ['resultado' => 'x'], null, new DateTimeImmutable())
        );

        $valueRepo = new MySqlLabResultValueRepository($this->pdo);
        $this->assertSame([], $valueRepo->findByLabResultId($labResultId));
    }
}
