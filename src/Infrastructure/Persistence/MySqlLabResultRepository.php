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

    public function save(LabResult $result): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lab_results (
                id_solicitud_key, cups, values_json, attachment_path, received_at, bacteriologo_id
            ) VALUES (:id_solicitud_key, :cups, :values_json, :attachment_path, :received_at, :bacteriologo_id)'
        );

        $stmt->execute([
            'id_solicitud_key' => $result->getIdSolicitudKey(),
            'cups'             => $result->getCups(),
            'values_json'      => json_encode($result->getValues(), JSON_THROW_ON_ERROR),
            'attachment_path'  => $result->getAttachmentPath(),
            'received_at'      => $result->getReceivedAt()->format('Y-m-d H:i:s'),
            'bacteriologo_id'  => $result->getBacteriologoId(),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findIdByOrderAndCups(string $idSolicitudKey, string $cups): ?int
    {
        $stmt = $this->connection->prepare(
            'SELECT id FROM lab_results
             WHERE id_solicitud_key = :key AND cups = :cups
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['key' => $idSolicitudKey, 'cups' => $cups]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public function findAllByOrder(string $idSolicitudKey): array
    {
        $stmt = $this->connection->prepare(
            'SELECT id, cups, values_json, attachment_path, received_at, bacteriologo_id
             FROM lab_results
             WHERE id_solicitud_key = :key
             ORDER BY id ASC'
        );
        $stmt->execute(['key' => $idSolicitudKey]);
        return $stmt->fetchAll();
    }

    /**
     * Retorna el primer attachment_path no nulo registrado para la orden.
     * Se usa para reutilizar un PDF adjunto en lugar de regenerarlo.
     */
    public function findAttachmentByOrder(string $idSolicitudKey): ?string
    {
        $stmt = $this->connection->prepare(
            'SELECT attachment_path FROM lab_results
             WHERE id_solicitud_key = :key
               AND attachment_path IS NOT NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['key' => $idSolicitudKey]);
        $path = $stmt->fetchColumn();
        return $path !== false ? $path : null;
    }

    public function updateAttachmentPath(string $idSolicitudKey, string $attachmentPath): void
    {
        // Actualiza el attachment_path del resultado más reciente de la orden
        $stmt = $this->connection->prepare(
            'UPDATE lab_results SET attachment_path = :path
             WHERE id_solicitud_key = :key
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['path' => $attachmentPath, 'key' => $idSolicitudKey]);
    }
}
