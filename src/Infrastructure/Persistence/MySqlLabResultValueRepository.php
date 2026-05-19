<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\LabResultValue;
use ClinicalLab\Domain\Repository\LabResultValueRepositoryInterface;
use PDO;

class MySqlLabResultValueRepository implements LabResultValueRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(LabResultValue $value): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lab_result_values
                (lab_result_id, parameter_id, valor_numerico, valor_texto, valor_booleano, reactivo, flag)
             VALUES
                (:lab_result_id, :parameter_id, :valor_numerico, :valor_texto, :valor_booleano, :reactivo, :flag)'
        );
        $stmt->execute([
            'lab_result_id'  => $value->getLabResultId(),
            'parameter_id'   => $value->getParameterId(),
            'valor_numerico' => $value->getValorNumerico(),
            'valor_texto'    => $value->getValorTexto(),
            'valor_booleano' => $value->getValorBooleano() !== null
                                    ? ($value->getValorBooleano() ? 1 : 0)
                                    : null,
            'reactivo'       => $value->getReactivo(),
            'flag'           => $value->getFlag(),
        ]);
    }

    public function findByLabResultId(int $labResultId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT lrv.*, ep.codigo, ep.nombre, ep.unidad,
                    ep.valor_min_ref, ep.valor_max_ref, ep.sexo, ep.orden,
                    ep.tipo_resultado, ep.etiqueta_booleano
             FROM lab_result_values lrv
             JOIN exam_parameters ep ON ep.id = lrv.parameter_id
             WHERE lrv.lab_result_id = :id
             ORDER BY ep.orden, ep.id'
        );
        $stmt->execute(['id' => $labResultId]);

        return array_map(fn(array $row) => new LabResultValue(
            (int)  $row['id'],
            (int)  $row['lab_result_id'],
            (int)  $row['parameter_id'],
            $row['valor_numerico'] !== null ? (float) $row['valor_numerico'] : null,
                   $row['valor_texto'],
            $row['valor_booleano'] !== null ? (bool) $row['valor_booleano'] : null,
                   $row['flag'],
                   $row['reactivo'] ?? null,
        ), $stmt->fetchAll());
    }
}
