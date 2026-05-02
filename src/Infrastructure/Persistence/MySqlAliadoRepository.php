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
        return $row ? new Aliado($row['id'], $row['nombre'], (bool) $row['activo']) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM aliados ORDER BY nombre');
        return array_map(
            fn($row) => new Aliado($row['id'], $row['nombre'], (bool) $row['activo']),
            $stmt->fetchAll()
        );
    }

    public function save(Aliado $aliado): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO aliados (id, nombre, activo) VALUES (:id, :nombre, :activo)
             ON DUPLICATE KEY UPDATE nombre = :nombre, activo = :activo'
        );
        $stmt->execute([
            'id'     => $aliado->getId(),
            'nombre' => $aliado->getNombre(),
            'activo' => $aliado->isActivo() ? 1 : 0,
        ]);
    }
}
