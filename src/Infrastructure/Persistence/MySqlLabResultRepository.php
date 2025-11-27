<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\LabResult;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use PDO;

class MySqlLabResultRepository implements LabResultRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(LabResult $result): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lab_results (
                id_solicitud_key, cups, values_json, attachment_path, received_at
            ) VALUES (:id_solicitud_key, :cups, :values_json, :attachment_path, :received_at)'
        );

        $stmt->execute([
            'id_solicitud_key' => $result->getIdSolicitudKey(),
            'cups' => $result->getCups(),
            'values_json' => json_encode($result->getValues(), JSON_THROW_ON_ERROR),
            'attachment_path' => $result->getAttachmentPath(),
            'received_at' => $result->getReceivedAt()->format('Y-m-d H:i:s'),
        ]);
    }
}
