<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\Patient;
use ClinicalLab\Domain\Repository\PatientRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MySqlPatientRepository implements PatientRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Patient
    {
        $stmt = $this->connection->prepare('SELECT * FROM patients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByDocument(string $tipoDocumento, string $identificacion): ?Patient
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM patients
             WHERE tipo_documento = :tipo AND identificacion = :id
             LIMIT 1'
        );
        $stmt->execute(['tipo' => $tipoDocumento, 'id' => $identificacion]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, int $page = 1, int $limit = 20): array
    {
        [$sql, $params] = $this->buildSearchQuery('SELECT *', $search);
        $sql .= ' ORDER BY nombre LIMIT :limit OFFSET :offset';

        $stmt = $this->connection->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':limit',  $limit,             PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn(array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function countAll(?string $search = null): int
    {
        [$sql, $params] = $this->buildSearchQuery('SELECT COUNT(*)', $search);
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function save(Patient $patient): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO patients (tipo_documento, identificacion, nombre, sexo, fecha_nacimiento, email, telefono)
             VALUES (:tipo_documento, :identificacion, :nombre, :sexo, :fecha_nacimiento, :email, :telefono)'
        );
        $stmt->execute([
            'tipo_documento'   => $patient->getTipoDocumento(),
            'identificacion'   => $patient->getIdentificacion(),
            'nombre'           => $patient->getNombre(),
            'sexo'             => $patient->getSexo(),
            'fecha_nacimiento' => $patient->getFechaNacimiento()->format('Y-m-d'),
            'email'            => $patient->getEmail(),
            'telefono'         => $patient->getTelefono(),
        ]);
        return (int) $this->connection->lastInsertId();
    }

    public function update(Patient $patient): void
    {
        $this->connection->prepare(
            'UPDATE patients
             SET tipo_documento = :tipo_documento,
                 identificacion = :identificacion,
                 nombre         = :nombre,
                 sexo           = :sexo,
                 fecha_nacimiento = :fecha_nacimiento,
                 email          = :email,
                 telefono       = :telefono
             WHERE id = :id'
        )->execute([
            'id'               => $patient->getId(),
            'tipo_documento'   => $patient->getTipoDocumento(),
            'identificacion'   => $patient->getIdentificacion(),
            'nombre'           => $patient->getNombre(),
            'sexo'             => $patient->getSexo(),
            'fecha_nacimiento' => $patient->getFechaNacimiento()->format('Y-m-d'),
            'email'            => $patient->getEmail(),
            'telefono'         => $patient->getTelefono(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: array<string, mixed>} */
    private function buildSearchQuery(string $select, ?string $search): array
    {
        $sql    = "{$select} FROM patients";
        $params = [];

        if ($search !== null && $search !== '') {
            $sql           .= ' WHERE (identificacion LIKE :search OR nombre LIKE :search2)';
            $params['search']  = "%{$search}%";
            $params['search2'] = "%{$search}%";
        }

        return [$sql, $params];
    }

    private function hydrate(array $row): Patient
    {
        return new Patient(
            (int) $row['id'],
                  $row['tipo_documento'],
                  $row['identificacion'],
                  $row['nombre'],
                  $row['sexo'],
            new DateTimeImmutable($row['fecha_nacimiento']),
                  $row['email']    ?? null,
                  $row['telefono'] ?? null,
        );
    }
}
