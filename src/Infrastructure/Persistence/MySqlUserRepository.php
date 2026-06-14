<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Entity\User;
use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MySqlUserRepository implements UserRepositoryInterface
{
    /** Número máximo de intentos antes de bloquear la cuenta. */
    private const MAX_ATTEMPTS = 4;

    /** Minutos que dura el bloqueo automático. */
    private const LOCK_MINUTES = 30;

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

    // ── Centros de salud ──────────────────────────────────────────────────────

    public function assignHealthCenter(int $userId, int $healthCenterId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT IGNORE INTO user_health_center (user_id, health_center_id) VALUES (:user_id, :hc_id)'
        );
        $stmt->execute(['user_id' => $userId, 'hc_id' => $healthCenterId]);
    }

    public function removeHealthCenter(int $userId, int $healthCenterId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM user_health_center WHERE user_id = :user_id AND health_center_id = :hc_id'
        );
        $stmt->execute(['user_id' => $userId, 'hc_id' => $healthCenterId]);
    }

    public function syncHealthCenters(int $userId, array $healthCenterIds): void
    {
        $this->connection->prepare(
            'DELETE FROM user_health_center WHERE user_id = :user_id'
        )->execute(['user_id' => $userId]);

        foreach ($healthCenterIds as $hcId) {
            $this->assignHealthCenter($userId, (int) $hcId);
        }
    }

    // ── Seguridad: bloqueo por intentos fallidos ──────────────────────────────

    public function incrementFailedAttempts(int $userId): void
    {
        // Incrementar y bloquear si se alcanza el límite
        $stmt = $this->connection->prepare(
            'UPDATE users
             SET failed_login_attempts = failed_login_attempts + 1,
                 locked_until = IF(
                     failed_login_attempts + 1 >= :max_attempts,
                     DATE_ADD(NOW(), INTERVAL :lock_minutes MINUTE),
                     locked_until
                 )
             WHERE id = :id'
        );
        $stmt->execute([
            'max_attempts'  => self::MAX_ATTEMPTS,
            'lock_minutes'  => self::LOCK_MINUTES,
            'id'            => $userId,
        ]);
    }

    public function resetFailedAttempts(int $userId): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE users
             SET failed_login_attempts = 0, locked_until = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    // ── Seguridad: recuperación de contraseña ─────────────────────────────────

    public function savePasswordResetToken(int $userId, string $tokenHash, DateTimeImmutable $expires): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE users
             SET password_reset_token   = :token_hash,
                 password_reset_expires = :expires
             WHERE id = :id'
        );
        $stmt->execute([
            'token_hash' => $tokenHash,
            'expires'    => $expires->format('Y-m-d H:i:s'),
            'id'         => $userId,
        ]);
    }

    public function findByResetToken(string $tokenHash): ?User
    {
        $stmt = $this->connection->prepare(
            'SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.password_reset_token   = :token_hash
               AND u.password_reset_expires > NOW()
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        return $this->hydrate($stmt->fetch());
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE users
             SET password_hash           = :password_hash,
                 password_reset_token   = NULL,
                 password_reset_expires = NULL,
                 failed_login_attempts  = 0,
                 locked_until           = NULL
             WHERE id = :id'
        );
        $stmt->execute(['password_hash' => $passwordHash, 'id' => $userId]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function hydrate(mixed $row): ?User
    {
        if (!$row) {
            return null;
        }

        $lockedUntil = null;
        if (!empty($row['locked_until'])) {
            $lockedUntil = new DateTimeImmutable($row['locked_until']);
        }

        $role = new Role((int) $row['role_id'], $row['role_name']);
        $user = new User(
            (int) $row['id'],
            $row['username'],
            $row['email'],
            $row['password_hash'],
            $role,
            (bool) $row['activo'],
            (int) ($row['failed_login_attempts'] ?? 0),
            $lockedUntil,
        );

        $user->setAliadoIds($this->loadAliadoIds((int) $row['id']));
        $user->setHealthCenterIds($this->loadHealthCenterIds((int) $row['id']));

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

    private function loadHealthCenterIds(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT health_center_id FROM user_health_center WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
