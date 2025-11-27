<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\LabOrderDetailDto;
use ClinicalLab\Application\Dto\LabOrderRequestDto;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Domain\Repository\LabOrderDetailRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use DateTimeImmutable;

class CreateLabOrderUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly LabOrderDetailRepositoryInterface $detailRepository
    ) {
    }

    public function execute(LabOrderRequestDto $dto): LabOrder
    {
        $order = new LabOrder(
            $dto->idSolicitudKey,
            $dto->idAdmision,
            $dto->idAtencion,
            $dto->tipoDeDocumento,
            $dto->identificacion,
            $dto->nombreDelPaciente,
            $dto->sexo,
            new DateTimeImmutable($dto->fechaDeNacimiento),
            $dto->centroDeSalud,
            new DateTimeImmutable($dto->fechaDeLaOrden),
            $dto->medicoQueOrdena,
            $dto->numeroDeAutorizacion,
            $dto->idAliado,
            null,
            (float) ($dto->porcEjecucion ?? 0),
        );

        $details = array_map(fn (LabOrderDetailDto $detailDto) => new LabOrderDetail(
            $detailDto->idSolicitudKey,
            $detailDto->idAdmision,
            $detailDto->cups,
            $detailDto->nombreDelLaboratorio,
            $detailDto->fechaTomaMuestra ? new DateTimeImmutable($detailDto->fechaTomaMuestra) : null,
            $detailDto->metodo,
            $detailDto->reactivo,
            $detailDto->invima,
            $detailDto->estadoDelResultado,
            $detailDto->fechaResultado ? new DateTimeImmutable($detailDto->fechaResultado) : null,
            $detailDto->tipoIdentificacionDelBacteriologo,
            $detailDto->identificacionDelBacteriologo
        ), $dto->detalles);

        foreach ($details as $detail) {
            $order->addDetail($detail);
        }

        $this->orderRepository->save($order);
        $this->detailRepository->saveMany($details);

        return $order;
    }
}
