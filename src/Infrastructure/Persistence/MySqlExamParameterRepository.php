<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\ExamParameter;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use PDO;

class MySqlExamParameterRepository implements ExamParameterRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findByCups(string $cups, ?string $sexo = null, ?int $edad = null): array
    {
        $where  = ['cups = :cups', 'activo = 1'];
        $params = ['cups' => $cups];

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

        $sql  = 'SELECT * FROM exam_parameters WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY orden, id';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findById(int $id): ?ExamParameter
    {
        $stmt = $this->connection->prepare('SELECT * FROM exam_parameters WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function save(ExamParameter $p): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO exam_parameters
                (cups, codigo, nombre, unidad, valor_min_ref, valor_max_ref,
                 tipo_resultado, etiqueta_booleano, comentario,
                 sexo, edad_min, edad_max, obligatorio, orden, activo)
             VALUES
                (:cups, :codigo, :nombre, :unidad, :valor_min_ref, :valor_max_ref,
                 :tipo_resultado, :etiqueta_booleano, :comentario,
                 :sexo, :edad_min, :edad_max, :obligatorio, :orden, :activo)'
        );
        $stmt->execute($this->toArray($p));
        return (int) $this->connection->lastInsertId();
    }

    public function update(ExamParameter $p): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE exam_parameters
             SET cups = :cups, codigo = :codigo, nombre = :nombre, unidad = :unidad,
                 valor_min_ref = :valor_min_ref, valor_max_ref = :valor_max_ref,
                 tipo_resultado = :tipo_resultado, etiqueta_booleano = :etiqueta_booleano,
                 comentario = :comentario,
                 sexo = :sexo, edad_min = :edad_min, edad_max = :edad_max,
                 obligatorio = :obligatorio, orden = :orden, activo = :activo
             WHERE id = :id'
        );
        $stmt->execute(array_merge(['id' => $p->getId()], $this->toArray($p)));
    }

    public function deactivate(int $id): void
    {
        $this->connection->prepare('UPDATE exam_parameters SET activo = 0 WHERE id = :id')
            ->execute(['id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function hydrate(array $row): ExamParameter
    {
        return new ExamParameter(
            (int)  $row['id'],
                   $row['cups'],
                   $row['codigo'],
                   $row['nombre'],
                   $row['unidad'],
            $row['valor_min_ref'] !== null ? (float) $row['valor_min_ref'] : null,
            $row['valor_max_ref'] !== null ? (float) $row['valor_max_ref'] : null,
                   $row['sexo'],
            $row['edad_min'] !== null ? (int) $row['edad_min'] : null,
            $row['edad_max'] !== null ? (int) $row['edad_max'] : null,
            (bool) $row['obligatorio'],
            (int)  $row['orden'],
            (bool) $row['activo'],
                   $row['tipo_resultado']    ?? ExamParameter::TIPO_NUMERICO,
                   $row['etiqueta_booleano'] ?? null,
                   $row['comentario']        ?? null,
        );
    }

    private function toArray(ExamParameter $p): array
    {
        return [
            'cups'              => $p->getCups(),
            'codigo'            => $p->getCodigo(),
            'nombre'            => $p->getNombre(),
            'unidad'            => $p->getUnidad(),
            'valor_min_ref'     => $p->getValorMinRef(),
            'valor_max_ref'     => $p->getValorMaxRef(),
            'tipo_resultado'    => $p->getTipoResultado(),
            'etiqueta_booleano' => $p->getEtiquetaBooleano(),
            'comentario'        => $p->getComentario(),
            'sexo'              => $p->getSexo(),
            'edad_min'          => $p->getEdadMin(),
            'edad_max'          => $p->getEdadMax(),
            'obligatorio'       => $p->isObligatorio() ? 1 : 0,
            'orden'             => $p->getOrden(),
            'activo'            => $p->isActivo() ? 1 : 0,
        ];
    }
}
