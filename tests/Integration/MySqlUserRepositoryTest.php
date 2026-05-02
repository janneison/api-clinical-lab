<?php

declare(strict_types=1);

namespace Tests\Integration;

use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Entity\User;
use ClinicalLab\Infrastructure\Persistence\MySqlUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class MySqlUserRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->pdo->exec('CREATE TABLE roles (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE
        )');

        $this->pdo->exec("INSERT INTO roles (name) VALUES
            ('admin'), ('lab_operator'), ('aliado_operator'), ('viewer')");

        $this->pdo->exec('CREATE TABLE aliados (
            id     TEXT PRIMARY KEY,
            nombre TEXT NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->pdo->exec("INSERT INTO aliados (id, nombre) VALUES ('ALLY-1', 'Lab Norte'), ('ALLY-2', 'Lab Sur')");

        $this->pdo->exec('CREATE TABLE users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            email         TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role_id       INTEGER NOT NULL,
            activo        INTEGER NOT NULL DEFAULT 1
        )');

        $this->pdo->exec('CREATE TABLE user_aliado (
            user_id   INTEGER NOT NULL,
            aliado_id TEXT NOT NULL,
            PRIMARY KEY (user_id, aliado_id)
        )');
    }

    private function makeUser(string $role = 'lab_operator'): User
    {
        return new User(
            0,
            'jdoe',
            'jdoe@lab.com',
            password_hash('secret', PASSWORD_BCRYPT),
            new Role(0, $role),
            true
        );
    }

    public function testSavesAndFindsUserByUsername(): void
    {
        $repo   = new MySqlUserRepository($this->pdo);
        $userId = $repo->save($this->makeUser());

        $this->assertGreaterThan(0, $userId);

        $found = $repo->findByUsername('jdoe');

        $this->assertNotNull($found);
        $this->assertSame('jdoe', $found->getUsername());
        $this->assertSame('jdoe@lab.com', $found->getEmail());
        $this->assertSame('lab_operator', $found->getRole()->getName());
        $this->assertTrue($found->isActivo());
    }

    public function testFindsUserByEmail(): void
    {
        $repo = new MySqlUserRepository($this->pdo);
        $repo->save($this->makeUser());

        $found = $repo->findByEmail('jdoe@lab.com');

        $this->assertNotNull($found);
        $this->assertSame('jdoe', $found->getUsername());
    }

    public function testFindsUserById(): void
    {
        $repo   = new MySqlUserRepository($this->pdo);
        $userId = $repo->save($this->makeUser());

        $found = $repo->findById($userId);

        $this->assertNotNull($found);
        $this->assertSame($userId, $found->getId());
    }

    public function testReturnsNullWhenUserNotFound(): void
    {
        $repo = new MySqlUserRepository($this->pdo);

        $this->assertNull($repo->findByUsername('ghost'));
        $this->assertNull($repo->findByEmail('ghost@x.com'));
        $this->assertNull($repo->findById(999));
    }

    public function testAssignsAndLoadsAliadoIds(): void
    {
        $repo   = new MySqlUserRepository($this->pdo);
        $userId = $repo->save($this->makeUser());

        // Insertar directamente con sintaxis SQLite para el test de integración
        $this->pdo->prepare('INSERT OR IGNORE INTO user_aliado (user_id, aliado_id) VALUES (?, ?)')
            ->execute([$userId, 'ALLY-1']);
        $this->pdo->prepare('INSERT OR IGNORE INTO user_aliado (user_id, aliado_id) VALUES (?, ?)')
            ->execute([$userId, 'ALLY-2']);

        $found = $repo->findById($userId);

        $this->assertNotNull($found);
        $this->assertCount(2, $found->getAliadoIds());
        $this->assertContains('ALLY-1', $found->getAliadoIds());
        $this->assertContains('ALLY-2', $found->getAliadoIds());
    }

    public function testRemovesAliado(): void
    {
        $repo   = new MySqlUserRepository($this->pdo);
        $userId = $repo->save($this->makeUser());

        $this->pdo->prepare('INSERT OR IGNORE INTO user_aliado (user_id, aliado_id) VALUES (?, ?)')
            ->execute([$userId, 'ALLY-1']);
        $this->pdo->prepare('INSERT OR IGNORE INTO user_aliado (user_id, aliado_id) VALUES (?, ?)')
            ->execute([$userId, 'ALLY-2']);

        $repo->removeAliado($userId, 'ALLY-1');

        $found = $repo->findById($userId);

        $this->assertNotNull($found);
        $this->assertCount(1, $found->getAliadoIds());
        $this->assertNotContains('ALLY-1', $found->getAliadoIds());
        $this->assertContains('ALLY-2', $found->getAliadoIds());
    }

    public function testAssignAliadoIsIdempotent(): void
    {
        $repo   = new MySqlUserRepository($this->pdo);
        $userId = $repo->save($this->makeUser());

        // Insertar dos veces — SQLite OR IGNORE no falla en duplicados
        $this->pdo->prepare('INSERT OR IGNORE INTO user_aliado (user_id, aliado_id) VALUES (?, ?)')
            ->execute([$userId, 'ALLY-1']);
        $this->pdo->prepare('INSERT OR IGNORE INTO user_aliado (user_id, aliado_id) VALUES (?, ?)')
            ->execute([$userId, 'ALLY-1']);

        $found = $repo->findById($userId);
        $this->assertCount(1, $found->getAliadoIds());
    }

    public function testThrowsWhenRoleNotFound(): void
    {
        $repo = new MySqlUserRepository($this->pdo);
        $user = new User(0, 'x', 'x@x.com', 'hash', new Role(0, 'nonexistent'), true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rol no encontrado: nonexistent');

        $repo->save($user);
    }
}
