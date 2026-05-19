<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\Aliado;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use PDO;

class MySqlAliadoRepository implements AliadoRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(string $id): ?Aliado
    {
        $stmt = $this->connection->prepare('SELECT * FROM aliados WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM aliados ORDER BY nombre');
        return array_map(fn($row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function save(Aliado $aliado): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO aliados (id, nombre, nit, direccion, email, logo_path, activo)
             VALUES (:id, :nombre, :nit, :direccion, :email, :logo_path, :activo)
             ON DUPLICATE KEY UPDATE
                nombre    = VALUES(nombre),
                nit       = VALUES(nit),
                direccion = VALUES(direccion),
                email     = VALUES(email),
                activo    = VALUES(activo)'
        );
        $stmt->execute($this->toArray($aliado));
    }

    public function update(Aliado $aliado): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE aliados
             SET nombre = :nombre, nit = :nit, direccion = :direccion,
                 email = :email, activo = :activo
             WHERE id = :id'
        );
        $stmt->execute([
            'id'        => $aliado->getId(),
            'nombre'    => $aliado->getNombre(),
            'nit'       => $aliado->getNit(),
            'direccion' => $aliado->getDireccion(),
            'email'     => $aliado->getEmail(),
            'activo'    => $aliado->isActivo() ? 1 : 0,
        ]);
    }

    public function updateLogo(string $id, string $logoPath): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE aliados SET logo_path = :logo_path WHERE id = :id'
        );
        $stmt->execute(['logo_path' => $logoPath, 'id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function hydrate(array $row): Aliado
    {
        return new Aliado(
            $row['id'],
            $row['nombre'],
            (bool) $row['activo'],
            $row['nit']       ?? null,
            $row['direccion'] ?? null,
            $row['email']     ?? null,
            $row['logo_path'] ?? null,
        );
    }

    private function toArray(Aliado $a): array
    {
        return [
            'id'        => $a->getId(),
            'nombre'    => $a->getNombre(),
            'nit'       => $a->getNit(),
            'direccion' => $a->getDireccion(),
            'email'     => $a->getEmail(),
            'logo_path' => $a->getLogoPath(),
            'activo'    => $a->isActivo() ? 1 : 0,
        ];
    }
}
