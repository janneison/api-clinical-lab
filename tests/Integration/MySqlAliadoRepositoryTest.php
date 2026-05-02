<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClinicalLab\Domain\Entity\Aliado;
use ClinicalLab\Infrastructure\Persistence\MySqlAliadoRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class MySqlAliadoRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('CREATE TABLE aliados (
            id     TEXT PRIMARY KEY,
            nombre TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');
    }

    public function testSavesAndFindsAliado(): void
    {
        $repo   = new MySqlAliadoRepository($this->pdo);
        $aliado = new Aliado('ALLY-1', 'Lab Norte', true);

        // SQLite usa INSERT OR REPLACE en lugar de ON DUPLICATE KEY
        $this->pdo->prepare('INSERT OR REPLACE INTO aliados (id, nombre, activo) VALUES (?, ?, ?)')
            ->execute(['ALLY-1', 'Lab Norte', 1]);

        $found = $repo->findById('ALLY-1');

        $this->assertNotNull($found);
        $this->assertSame('ALLY-1', $found->getId());
        $this->assertSame('Lab Norte', $found->getNombre());
        $this->assertTrue($found->isActivo());
    }

    public function testReturnsNullWhenAliadoNotFound(): void
    {
        $repo = new MySqlAliadoRepository($this->pdo);
        $this->assertNull($repo->findById('GHOST'));
    }

    public function testFindAllReturnsAllAliados(): void
    {
        $repo = new MySqlAliadoRepository($this->pdo);
        $this->pdo->exec("INSERT INTO aliados (id, nombre, activo) VALUES ('ALLY-2','Lab Sur',1),('ALLY-1','Lab Norte',1)");

        $all = $repo->findAll();

        $this->assertCount(2, $all);
        $this->assertSame('Lab Norte', $all[0]->getNombre());
        $this->assertSame('Lab Sur', $all[1]->getNombre());
    }

    public function testFindAllReturnsEmptyWhenNoAliados(): void
    {
        $repo = new MySqlAliadoRepository($this->pdo);
        $this->assertSame([], $repo->findAll());
    }

    public function testSaveUpdatesExistingAliado(): void
    {
        $repo = new MySqlAliadoRepository($this->pdo);
        $this->pdo->exec("INSERT INTO aliados (id, nombre, activo) VALUES ('ALLY-1','Lab Norte',1)");
        $this->pdo->prepare('INSERT OR REPLACE INTO aliados (id, nombre, activo) VALUES (?, ?, ?)')
            ->execute(['ALLY-1', 'Lab Norte Actualizado', 0]);

        $found = $repo->findById('ALLY-1');

        $this->assertSame('Lab Norte Actualizado', $found->getNombre());
        $this->assertFalse($found->isActivo());
    }

    public function testSavesInactiveAliado(): void
    {
        $repo = new MySqlAliadoRepository($this->pdo);
        $this->pdo->exec("INSERT INTO aliados (id, nombre, activo) VALUES ('ALLY-X','Lab Inactivo',0)");

        $found = $repo->findById('ALLY-X');

        $this->assertNotNull($found);
        $this->assertFalse($found->isActivo());
    }
}
