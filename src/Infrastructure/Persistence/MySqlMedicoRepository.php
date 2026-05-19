<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\Medico;
use ClinicalLab\Domain\Repository\MedicoRepositoryInterface;
use PDO;

class MySqlMedicoRepository implements MedicoRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Medico
    {
        $stmt = $this->connection->prepare('SELECT * FROM medicos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByDocument(string $tipoDocumento, string $identificacion): ?Medico
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM medicos WHERE tipo_documento = :tipo AND identificacion = :id LIMIT 1'
        );
        $stmt->execute(['tipo' => $tipoDocumento, 'id' => $identificacion]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByUserId(int $userId): ?Medico
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM medicos WHERE user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, bool $soloActivos = true): array
    {
        $where  = [];
        $params = [];

        if ($soloActivos) {
            $where[] = 'activo = 1';
        }

        if ($search !== null && $search !== '') {
            $where[]           = '(identificacion LIKE :search OR nombre LIKE :search2)';
            $params['search']  = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }

        $sql = 'SELECT * FROM medicos';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY nombre';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function save(Medico $medico): int
    {
        $this->connection->prepare(
            'INSERT INTO medicos (tipo_documento, identificacion, nombre, especialidad, registro_medico, user_id, activo)
             VALUES (:tipo_documento, :identificacion, :nombre, :especialidad, :registro_medico, :user_id, :activo)'
        )->execute([
            'tipo_documento'  => $medico->getTipoDocumento(),
            'identificacion'  => $medico->getIdentificacion(),
            'nombre'          => $medico->getNombre(),
            'especialidad'    => $medico->getEspecialidad(),
            'registro_medico' => $medico->getRegistroMedico(),
            'user_id'         => $medico->getUserId(),
            'activo'          => $medico->isActivo() ? 1 : 0,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    public function update(Medico $medico): void
    {
        $this->connection->prepare(
            'UPDATE medicos
             SET tipo_documento  = :tipo_documento,
                 identificacion  = :identificacion,
                 nombre          = :nombre,
                 especialidad    = :especialidad,
                 registro_medico = :registro_medico,
                 user_id         = :user_id,
                 activo          = :activo
             WHERE id = :id'
        )->execute([
            'id'              => $medico->getId(),
            'tipo_documento'  => $medico->getTipoDocumento(),
            'identificacion'  => $medico->getIdentificacion(),
            'nombre'          => $medico->getNombre(),
            'especialidad'    => $medico->getEspecialidad(),
            'registro_medico' => $medico->getRegistroMedico(),
            'user_id'         => $medico->getUserId(),
            'activo'          => $medico->isActivo() ? 1 : 0,
        ]);
    }

    public function deactivate(int $id): void
    {
        $this->connection->prepare('UPDATE medicos SET activo = 0 WHERE id = :id')
            ->execute(['id' => $id]);
    }

    private function hydrate(array $row): Medico
    {
        return new Medico(
            (int) $row['id'],
            $row['tipo_documento'],
            $row['identificacion'],
            $row['nombre'],
            $row['especialidad']    ?? null,
            $row['registro_medico'] ?? null,
            isset($row['user_id'])  ? (int) $row['user_id'] : null,
            (bool) $row['activo'],
        );
    }
}
