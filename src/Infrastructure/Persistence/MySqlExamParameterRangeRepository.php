<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\ExamParameterRange;
use ClinicalLab\Domain\Repository\ExamParameterRangeRepositoryInterface;
use PDO;

class MySqlExamParameterRangeRepository implements ExamParameterRangeRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByParameter(
        int     $parameterId,
        ?string $reactivo = null,
        ?string $sexo     = null,
        ?int    $edad     = null
    ): array {
        $where  = ['parameter_id = :pid', 'activo = 1'];
        $params = ['pid' => $parameterId];

        if ($reactivo !== null) {
            $where[]           = 'reactivo = :reactivo';
            $params['reactivo'] = $reactivo;
        }

        if ($sexo !== null) {
            $where[]        = "(sexo = :sexo OR sexo = '*')";
            $params['sexo'] = $sexo;
        }

        if ($edad !== null) {
            $where[]         = '(edad_min IS NULL OR edad_min <= :edad)';
            $where[]         = '(edad_max IS NULL OR edad_max >= :edad2)';
            $params['edad']  = $edad;
            $params['edad2'] = $edad;
        }

        $sql = 'SELECT * FROM exam_parameter_ranges WHERE '
             . implode(' AND ', $where)
             . ' ORDER BY id';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findById(int $id): ?ExamParameterRange
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM exam_parameter_ranges WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function save(ExamParameterRange $range): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO exam_parameter_ranges
                (parameter_id, reactivo, valor_min_ref, valor_max_ref, sexo, edad_min, edad_max, activo)
             VALUES
                (:parameter_id, :reactivo, :valor_min_ref, :valor_max_ref, :sexo, :edad_min, :edad_max, :activo)'
        );
        $stmt->execute($this->toArray($range));
        return (int) $this->connection->lastInsertId();
    }

    public function update(ExamParameterRange $range): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE exam_parameter_ranges
             SET reactivo = :reactivo, valor_min_ref = :valor_min_ref, valor_max_ref = :valor_max_ref,
                 sexo = :sexo, edad_min = :edad_min, edad_max = :edad_max, activo = :activo
             WHERE id = :id'
        );
        $stmt->execute(array_merge(['id' => $range->getId()], $this->toArray($range)));
    }

    public function deactivate(int $id): void
    {
        $this->connection->prepare(
            'UPDATE exam_parameter_ranges SET activo = 0 WHERE id = :id'
        )->execute(['id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function hydrate(array $row): ExamParameterRange
    {
        return new ExamParameterRange(
            (int)  $row['id'],
            (int)  $row['parameter_id'],
                   $row['reactivo'],
            $row['valor_min_ref'] !== null ? (float) $row['valor_min_ref'] : null,
            $row['valor_max_ref'] !== null ? (float) $row['valor_max_ref'] : null,
                   $row['sexo'],
            $row['edad_min'] !== null ? (int) $row['edad_min'] : null,
            $row['edad_max'] !== null ? (int) $row['edad_max'] : null,
            (bool) $row['activo']
        );
    }

    private function toArray(ExamParameterRange $r): array
    {
        return [
            'parameter_id'  => $r->getParameterId(),
            'reactivo'      => $r->getReactivo(),
            'valor_min_ref' => $r->getValorMinRef(),
            'valor_max_ref' => $r->getValorMaxRef(),
            'sexo'          => $r->getSexo(),
            'edad_min'      => $r->getEdadMin(),
            'edad_max'      => $r->getEdadMax(),
            'activo'        => $r->isActivo() ? 1 : 0,
        ];
    }
}
