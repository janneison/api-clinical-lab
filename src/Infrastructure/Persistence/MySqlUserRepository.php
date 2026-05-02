<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Entity\User;
use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use PDO;

class MySqlUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        return $this->hydrate($stmt->fetch());
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        return $this->hydrate($stmt->fetch());
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $this->hydrate($stmt->fetch());
    }

    public function save(User $user): int
    {
        // Resolver role_id desde el nombre del rol
        $roleStmt = $this->connection->prepare('SELECT id FROM roles WHERE name = :name');
        $roleStmt->execute(['name' => $user->getRole()->getName()]);
        $roleId = $roleStmt->fetchColumn();

        if (!$roleId) {
            throw new \RuntimeException("Rol no encontrado: {$user->getRole()->getName()}");
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO users (username, email, password_hash, role_id, activo)
             VALUES (:username, :email, :password_hash, :role_id, :activo)'
        );

        $stmt->execute([
            'username'      => $user->getUsername(),
            'email'         => $user->getEmail(),
            'password_hash' => $user->getPasswordHash(),
            'role_id'       => $roleId,
            'activo'        => $user->isActivo() ? 1 : 0,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function assignAliado(int $userId, string $aliadoId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT IGNORE INTO user_aliado (user_id, aliado_id) VALUES (:user_id, :aliado_id)'
        );
        $stmt->execute(['user_id' => $userId, 'aliado_id' => $aliadoId]);
    }

    public function removeAliado(int $userId, string $aliadoId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM user_aliado WHERE user_id = :user_id AND aliado_id = :aliado_id'
        );
        $stmt->execute(['user_id' => $userId, 'aliado_id' => $aliadoId]);
    }

    // -------------------------------------------------------------------------

    private function hydrate(mixed $row): ?User
    {
        if (!$row) {
            return null;
        }

        $role = new Role((int) $row['role_id'], $row['role_name']);
        $user = new User(
            (int) $row['id'],
            $row['username'],
            $row['email'],
            $row['password_hash'],
            $role,
            (bool) $row['activo']
        );

        $user->setAliadoIds($this->loadAliadoIds((int) $row['id']));

        return $user;
    }

    private function loadAliadoIds(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT aliado_id FROM user_aliado WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
