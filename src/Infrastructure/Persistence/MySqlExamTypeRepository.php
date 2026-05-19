<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\ExamType;
use ClinicalLab\Domain\Repository\ExamTypeRepositoryInterface;
use PDO;

class MySqlExamTypeRepository implements ExamTypeRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findAll(bool $soloActivos = true): array
    {
        $sql  = 'SELECT * FROM exam_types';
        $sql .= $soloActivos ? ' WHERE activo = 1' : '';
        $sql .= ' ORDER BY nombre';

        return array_map(
            fn(array $row) => $this->hydrate($row),
            $this->connection->query($sql)->fetchAll()
        );
    }

    public function findByCups(string $cups): ?ExamType
    {
        $stmt = $this->connection->prepare('SELECT * FROM exam_types WHERE cups = :cups');
        $stmt->execute(['cups' => $cups]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function save(ExamType $examType): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO exam_types (cups, nombre, descripcion, activo)
             VALUES (:cups, :nombre, :descripcion, :activo)'
        );
        $stmt->execute([
            'cups'        => $examType->getCups(),
            'nombre'      => $examType->getNombre(),
            'descripcion' => $examType->getDescripcion(),
            'activo'      => $examType->isActivo() ? 1 : 0,
        ]);
    }

    public function update(ExamType $examType): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE exam_types
             SET nombre = :nombre, descripcion = :descripcion, activo = :activo
             WHERE cups = :cups'
        );
        $stmt->execute([
            'cups'        => $examType->getCups(),
            'nombre'      => $examType->getNombre(),
            'descripcion' => $examType->getDescripcion(),
            'activo'      => $examType->isActivo() ? 1 : 0,
        ]);
    }

    private function hydrate(array $row): ExamType
    {
        return new ExamType(
            $row['cups'],
            $row['nombre'],
            $row['descripcion'],
            (bool) $row['activo']
        );
    }
}
