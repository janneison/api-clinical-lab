<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\Antibiograma;
use ClinicalLab\Domain\Entity\AntibiogramaItem;
use ClinicalLab\Domain\Repository\AntibiogramaRepositoryInterface;
use PDO;

class MySqlAntibiogramaRepository implements AntibiogramaRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(Antibiograma $antibiograma): int
    {
        $this->connection->prepare(
            'INSERT INTO antibiogramas
                (lab_result_id, bacteria_aislada, gram, tiempo_incubacion, gram_orina, observaciones)
             VALUES
                (:lab_result_id, :bacteria_aislada, :gram, :tiempo_incubacion, :gram_orina, :observaciones)'
        )->execute([
            'lab_result_id'    => $antibiograma->getLabResultId(),
            'bacteria_aislada' => $antibiograma->getBacteriaAislada(),
            'gram'             => $antibiograma->getGram(),
            'tiempo_incubacion'=> $antibiograma->getTiempoIncubacion(),
            'gram_orina'       => $antibiograma->getGramOrina(),
            'observaciones'    => $antibiograma->getObservaciones(),
        ]);

        $antibiogramaId = (int) $this->connection->lastInsertId();

        foreach ($antibiograma->getItems() as $item) {
            $this->connection->prepare(
                'INSERT INTO antibiograma_items
                    (antibiograma_id, antibiotico, cim, sensibilidad, metodo)
                 VALUES
                    (:antibiograma_id, :antibiotico, :cim, :sensibilidad, :metodo)'
            )->execute([
                'antibiograma_id' => $antibiogramaId,
                'antibiotico'     => $item->getAntibiotico(),
                'cim'             => $item->getCim(),
                'sensibilidad'    => $item->getSensibilidad(),
                'metodo'          => $item->getMetodo(),
            ]);
        }

        return $antibiogramaId;
    }

    public function findByLabResultId(int $labResultId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM antibiogramas WHERE lab_result_id = :id ORDER BY id'
        );
        $stmt->execute(['id' => $labResultId]);
        $rows = $stmt->fetchAll();

        $antibiogramas = [];
        foreach ($rows as $row) {
            $ab = new Antibiograma(
                (int) $row['id'],
                (int) $row['lab_result_id'],
                $row['bacteria_aislada'],
                $row['gram']              ?? null,
                $row['tiempo_incubacion'] ?? null,
                $row['gram_orina']        ?? null,
                $row['observaciones']     ?? null,
            );

            // Cargar items
            $itemStmt = $this->connection->prepare(
                'SELECT * FROM antibiograma_items WHERE antibiograma_id = :id ORDER BY id'
            );
            $itemStmt->execute(['id' => $row['id']]);
            foreach ($itemStmt->fetchAll() as $itemRow) {
                $ab->addItem(new AntibiogramaItem(
                    (int) $itemRow['id'],
                    (int) $itemRow['antibiograma_id'],
                    $itemRow['antibiotico'],
                    $itemRow['cim']          ?? null,
                    $itemRow['sensibilidad'] ?? null,
                    $itemRow['metodo']       ?? null,
                ));
            }

            $antibiogramas[] = $ab;
        }

        return $antibiogramas;
    }

    public function deleteByLabResultId(int $labResultId): void
    {
        // Los items se eliminan en cascada por FK
        $this->connection->prepare(
            'DELETE FROM antibiogramas WHERE lab_result_id = :id'
        )->execute(['id' => $labResultId]);
    }
}
