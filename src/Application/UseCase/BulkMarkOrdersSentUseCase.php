<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;

class BulkMarkOrdersSentUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly AliadoRepositoryInterface   $aliadoRepository,
    ) {
    }

    /**
     * Marca como 'sent' las órdenes de la lista que:
     *   - existen
     *   - pertenecen al aliado indicado
     *   - están en estado 'pending'
     *
     * Las que no cumplan alguna condición se omiten (no lanzan excepción).
     *
     * @param  string   $aliadoId
     * @param  string[] $idsSolicitudKey
     * @return array{
     *   updated:  string[],
     *   skipped:  array<string, string>
     * }
     * @throws RuntimeException si el aliado no existe
     */
    public function execute(string $aliadoId, array $idsSolicitudKey): array
    {
        if (!$this->aliadoRepository->findById($aliadoId)) {
            throw new RuntimeException("Aliado no encontrado: {$aliadoId}");
        }

        $updated = [];
        $skipped = [];
        $now     = new DateTimeImmutable();

        foreach ($idsSolicitudKey as $id) {
            $order = $this->orderRepository->findByIdSolicitudKey($id);

            if ($order === null) {
                $skipped[$id] = 'Orden no encontrada';
                continue;
            }

            if ($order->getIdAliado() !== $aliadoId) {
                $skipped[$id] = 'La orden no pertenece al aliado';
                continue;
            }

            if ($order->getEstadoDeLaOrden() !== LabOrder::STATUS_PENDING) {
                $skipped[$id] = "Estado inválido: {$order->getEstadoDeLaOrden()} (se requiere pending)";
                continue;
            }

            $order->markAsSent($now);
            $this->orderRepository->update($order);
            $updated[] = $id;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}
