<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\Bacteriologo;
use ClinicalLab\Domain\Repository\BacteriologoRepositoryInterface;
use PDO;

class MySqlBacteriologoRepository implements BacteriologoRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Bacteriologo
    {
        $stmt = $this->connection->prepare('SELECT * FROM bacteriologos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByDocument(string $tipoDocumento, string $identificacion): ?Bacteriologo
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM bacteriologos WHERE tipo_documento = :tipo AND identificacion = :id LIMIT 1'
        );
        $stmt->execute(['tipo' => $tipoDocumento, 'id' => $identificacion]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByAliadoId(string $aliadoId, bool $soloActivos = true): array
    {
        $sql  = 'SELECT * FROM bacteriologos WHERE aliado_id = :aliado_id';
        $sql .= $soloActivos ? ' AND activo = 1' : '';
        $sql .= ' ORDER BY nombre';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['aliado_id' => $aliadoId]);
        return array_map(fn($row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function save(Bacteriologo $b): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO bacteriologos
                (aliado_id, tipo_documento, identificacion, nombre, tarjeta_profesional, universidad, firma_path, activo)
             VALUES
                (:aliado_id, :tipo_documento, :identificacion, :nombre, :tarjeta_profesional, :universidad, :firma_path, :activo)'
        );
        $stmt->execute($this->toArray($b));
        return (int) $this->connection->lastInsertId();
    }

    public function update(Bacteriologo $b): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE bacteriologos
             SET aliado_id = :aliado_id, tipo_documento = :tipo_documento, identificacion = :identificacion,
                 nombre = :nombre, tarjeta_profesional = :tarjeta_profesional,
                 universidad = :universidad, activo = :activo
             WHERE id = :id'
        );
        $stmt->execute(array_merge(['id' => $b->getId()], $this->toArray($b)));
    }

    public function updateFirma(int $id, string $firmaPath): void
    {
        $this->connection->prepare(
            'UPDATE bacteriologos SET firma_path = :firma_path WHERE id = :id'
        )->execute(['firma_path' => $firmaPath, 'id' => $id]);
    }

    public function deactivate(int $id): void
    {
        $this->connection->prepare(
            'UPDATE bacteriologos SET activo = 0 WHERE id = :id'
        )->execute(['id' => $id]);
    }

    private function hydrate(array $row): Bacteriologo
    {
        return new Bacteriologo(
            (int)  $row['id'],
                   $row['aliado_id'],
                   $row['tipo_documento'],
                   $row['identificacion'],
                   $row['nombre'],
                   $row['tarjeta_profesional'] ?? null,
                   $row['universidad']         ?? null,
                   $row['firma_path']          ?? null,
            (bool) $row['activo'],
        );
    }

    private function toArray(Bacteriologo $b): array
    {
        return [
            'aliado_id'           => $b->getAliadoId(),
            'tipo_documento'      => $b->getTipoDocumento(),
            'identificacion'      => $b->getIdentificacion(),
            'nombre'              => $b->getNombre(),
            'tarjeta_profesional' => $b->getTarjetaProfesional(),
            'universidad'         => $b->getUniversidad(),
            'firma_path'          => $b->getFirmaPath(),
            'activo'              => $b->isActivo() ? 1 : 0,
        ];
    }
}
