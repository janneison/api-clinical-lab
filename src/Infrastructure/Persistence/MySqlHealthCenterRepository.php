<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\HealthCenter;
use ClinicalLab\Domain\Repository\HealthCenterRepositoryInterface;
use PDO;

class MySqlHealthCenterRepository implements HealthCenterRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findAll(bool $soloActivos = true): array
    {
        $sql  = 'SELECT * FROM health_centers';
        $sql .= $soloActivos ? ' WHERE activo = 1' : '';
        $sql .= ' ORDER BY nombre';

        return array_map(
            fn(array $row) => $this->hydrate($row),
            $this->connection->query($sql)->fetchAll()
        );
    }

    public function findById(int $id): ?HealthCenter
    {
        $stmt = $this->connection->prepare('SELECT * FROM health_centers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByNombre(string $nombre): ?HealthCenter
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM health_centers WHERE nombre = :nombre AND activo = 1 LIMIT 1'
        );
        $stmt->execute(['nombre' => $nombre]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function save(HealthCenter $center): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO health_centers (nombre, ciudad, direccion, telefono, activo)
             VALUES (:nombre, :ciudad, :direccion, :telefono, :activo)'
        );
        $stmt->execute([
            'nombre'    => $center->getNombre(),
            'ciudad'    => $center->getCiudad(),
            'direccion' => $center->getDireccion(),
            'telefono'  => $center->getTelefono(),
            'activo'    => $center->isActivo() ? 1 : 0,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    public function update(HealthCenter $center): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE health_centers
             SET nombre = :nombre, ciudad = :ciudad, direccion = :direccion,
                 telefono = :telefono, activo = :activo
             WHERE id = :id'
        );
        $stmt->execute([
            'id'        => $center->getId(),
            'nombre'    => $center->getNombre(),
            'ciudad'    => $center->getCiudad(),
            'direccion' => $center->getDireccion(),
            'telefono'  => $center->getTelefono(),
            'activo'    => $center->isActivo() ? 1 : 0,
        ]);
    }

    public function findByAliadoId(string $aliadoId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT hc.* FROM health_centers hc
             JOIN aliado_health_center ahc ON ahc.health_center_id = hc.id
             WHERE ahc.aliado_id = :aliado_id AND hc.activo = 1
             ORDER BY hc.nombre'
        );
        $stmt->execute(['aliado_id' => $aliadoId]);
        return array_map(fn(array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function associateAliado(int $healthCenterId, string $aliadoId): void
    {
        $stmt = $this->connection->prepare(
            'INSERT IGNORE INTO aliado_health_center (aliado_id, health_center_id)
             VALUES (:aliado_id, :health_center_id)'
        );
        $stmt->execute(['aliado_id' => $aliadoId, 'health_center_id' => $healthCenterId]);
    }

    public function dissociateAliado(int $healthCenterId, string $aliadoId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM aliado_health_center
             WHERE aliado_id = :aliado_id AND health_center_id = :health_center_id'
        );
        $stmt->execute(['aliado_id' => $aliadoId, 'health_center_id' => $healthCenterId]);
    }

    private function hydrate(array $row): HealthCenter
    {
        return new HealthCenter(
            (int)    $row['id'],
                     $row['nombre'],
                     $row['ciudad'],
                     $row['direccion'],
                     $row['telefono'],
            (bool)   $row['activo']
        );
    }
}
