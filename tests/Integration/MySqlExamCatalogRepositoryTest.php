<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClinicalLab\Domain\Entity\ExamParameter;
use ClinicalLab\Domain\Entity\ExamType;
use ClinicalLab\Infrastructure\Persistence\MySqlExamParameterRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlExamTypeRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class MySqlExamCatalogRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('CREATE TABLE exam_types (
            cups        TEXT PRIMARY KEY,
            nombre      TEXT NOT NULL,
            descripcion TEXT NULL,
            activo      INTEGER NOT NULL DEFAULT 1
        )');

        $this->pdo->exec('CREATE TABLE exam_parameters (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            cups          TEXT NOT NULL,
            codigo        TEXT NOT NULL,
            nombre        TEXT NOT NULL,
            unidad        TEXT NULL,
            valor_min_ref REAL NULL,
            valor_max_ref REAL NULL,
            sexo          TEXT NOT NULL DEFAULT \'*\',
            edad_min      INTEGER NULL,
            edad_max      INTEGER NULL,
            obligatorio   INTEGER NOT NULL DEFAULT 0,
            orden         INTEGER NOT NULL DEFAULT 0,
            activo        INTEGER NOT NULL DEFAULT 1,
            UNIQUE (cups, codigo, sexo, edad_min, edad_max)
        )');
    }

    // ── ExamTypeRepository ────────────────────────────────────────────────────

    public function testSavesAndFindsExamType(): void
    {
        $repo = new MySqlExamTypeRepository($this->pdo);
        $repo->save(new ExamType('903820', 'Hemograma Completo', 'CBC', true));

        $found = $repo->findByCups('903820');

        $this->assertNotNull($found);
        $this->assertSame('903820', $found->getCups());
        $this->assertSame('Hemograma Completo', $found->getNombre());
        $this->assertSame('CBC', $found->getDescripcion());
        $this->assertTrue($found->isActivo());
    }

    public function testFindAllReturnsOnlyActiveByDefault(): void
    {
        $repo = new MySqlExamTypeRepository($this->pdo);
        $repo->save(new ExamType('903820', 'Hemograma', null, true));
        $repo->save(new ExamType('904010', 'Glucosa', null, false));

        $active = $repo->findAll(true);
        $this->assertCount(1, $active);
        $this->assertSame('903820', $active[0]->getCups());

        $all = $repo->findAll(false);
        $this->assertCount(2, $all);
    }

    public function testUpdatesExamType(): void
    {
        $repo = new MySqlExamTypeRepository($this->pdo);
        $repo->save(new ExamType('903820', 'Hemograma', null, true));
        $repo->update(new ExamType('903820', 'Hemograma Completo Actualizado', 'CBC v2', false));

        $found = $repo->findByCups('903820');

        $this->assertSame('Hemograma Completo Actualizado', $found->getNombre());
        $this->assertSame('CBC v2', $found->getDescripcion());
        $this->assertFalse($found->isActivo());
    }

    public function testReturnsNullWhenExamTypeNotFound(): void
    {
        $repo = new MySqlExamTypeRepository($this->pdo);
        $this->assertNull($repo->findByCups('GHOST'));
    }

    // ── ExamParameterRepository ───────────────────────────────────────────────

    public function testSavesAndFindsParameter(): void
    {
        $this->pdo->exec("INSERT INTO exam_types (cups, nombre, activo) VALUES ('903820','Hemograma',1)");

        $repo = new MySqlExamParameterRepository($this->pdo);
        $id   = $repo->save(new ExamParameter(
            0, '903820', 'wbc', 'Leucocitos', '10³/µL', 4.5, 11.0, '*', null, null, true, 1, true
        ));

        $this->assertGreaterThan(0, $id);

        $found = $repo->findById($id);

        $this->assertNotNull($found);
        $this->assertSame('wbc', $found->getCodigo());
        $this->assertSame('Leucocitos', $found->getNombre());
        $this->assertSame(4.5, $found->getValorMinRef());
        $this->assertSame(11.0, $found->getValorMaxRef());
        $this->assertTrue($found->isObligatorio());
    }

    public function testFindByCupsReturnsAllActiveParams(): void
    {
        $this->pdo->exec("INSERT INTO exam_types (cups, nombre, activo) VALUES ('903820','Hemograma',1)");

        $repo = new MySqlExamParameterRepository($this->pdo);
        $repo->save(new ExamParameter(0, '903820', 'wbc', 'Leucocitos',  '10³/µL', 4.5, 11.0, '*', null, null, true,  1, true));
        $repo->save(new ExamParameter(0, '903820', 'hb',  'Hemoglobina', 'g/dL',   13.5, 17.5, 'M', null, null, true,  2, true));
        $repo->save(new ExamParameter(0, '903820', 'hb',  'Hemoglobina', 'g/dL',   12.0, 16.0, 'F', null, null, true,  2, true));

        $all = $repo->findByCups('903820');
        $this->assertCount(3, $all);
    }

    public function testFindByCupsFiltersBySexo(): void
    {
        $this->pdo->exec("INSERT INTO exam_types (cups, nombre, activo) VALUES ('903820','Hemograma',1)");

        $repo = new MySqlExamParameterRepository($this->pdo);
        $repo->save(new ExamParameter(0, '903820', 'wbc', 'Leucocitos',  '10³/µL', 4.5, 11.0, '*', null, null, true, 1, true));
        $repo->save(new ExamParameter(0, '903820', 'hb',  'Hemoglobina', 'g/dL',   13.5, 17.5, 'M', null, null, true, 2, true));
        $repo->save(new ExamParameter(0, '903820', 'hb',  'Hemoglobina', 'g/dL',   12.0, 16.0, 'F', null, null, true, 2, true));

        // Para sexo M: wbc (*) + hb (M) = 2
        $male = $repo->findByCups('903820', 'M');
        $this->assertCount(2, $male);

        // Para sexo F: wbc (*) + hb (F) = 2
        $female = $repo->findByCups('903820', 'F');
        $this->assertCount(2, $female);
    }

    public function testDeactivatesParameter(): void
    {
        $this->pdo->exec("INSERT INTO exam_types (cups, nombre, activo) VALUES ('903820','Hemograma',1)");

        $repo = new MySqlExamParameterRepository($this->pdo);
        $id   = $repo->save(new ExamParameter(0, '903820', 'wbc', 'Leucocitos', null, null, null, '*', null, null, false, 1, true));

        $repo->deactivate($id);

        $params = $repo->findByCups('903820');
        $this->assertCount(0, $params);   // findByCups solo retorna activos
    }

    public function testUpdatesParameter(): void
    {
        $this->pdo->exec("INSERT INTO exam_types (cups, nombre, activo) VALUES ('903820','Hemograma',1)");

        $repo = new MySqlExamParameterRepository($this->pdo);
        $id   = $repo->save(new ExamParameter(0, '903820', 'wbc', 'Leucocitos', '10³/µL', 4.5, 11.0, '*', null, null, true, 1, true));

        $repo->update(new ExamParameter($id, '903820', 'wbc', 'Leucocitos Actualizado', '10³/µL', 4.0, 12.0, '*', null, null, true, 1, true));

        $found = $repo->findById($id);
        $this->assertSame('Leucocitos Actualizado', $found->getNombre());
        $this->assertSame(4.0, $found->getValorMinRef());
        $this->assertSame(12.0, $found->getValorMaxRef());
    }

    public function testReturnsNullWhenParameterNotFound(): void
    {
        $repo = new MySqlExamParameterRepository($this->pdo);
        $this->assertNull($repo->findById(999));
    }
}
