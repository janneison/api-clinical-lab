<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\LabOrderDetailDto;
use ClinicalLab\Application\Dto\LabOrderRequestDto;
use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Domain\Entity\Patient;
use ClinicalLab\Domain\Repository\HealthCenterRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderDetailRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\MedicoRepositoryInterface;
use ClinicalLab\Domain\Repository\PatientRepositoryInterface;
use DateTimeImmutable;

class CreateLabOrderUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface       $orderRepository,
        private readonly LabOrderDetailRepositoryInterface $detailRepository,
        private readonly PatientRepositoryInterface        $patientRepository,
        private readonly HealthCenterRepositoryInterface   $healthCenterRepository,
        private readonly MedicoRepositoryInterface         $medicoRepository,
    ) {
    }

    public function execute(LabOrderRequestDto $dto): LabOrder
    {
        // ── 1. Resolver paciente (find-or-create) ─────────────────────────────
        $patient = $this->resolvePatient($dto);

        // ── 2. Resolver centro de salud ───────────────────────────────────────
        $healthCenterId = $this->resolveHealthCenter($dto);

        // ── 3. Resolver médico ────────────────────────────────────────────────
        $medicoId = $this->resolveMedico($dto);

        // ── 4. Construir orden ────────────────────────────────────────────────
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
            LabOrder::STATUS_PENDING,
            $patient->getId(),
            $healthCenterId,
            $medicoId,
        );

        $details = array_map(fn(LabOrderDetailDto $d) => new LabOrderDetail(
            $d->idSolicitudKey,
            $d->idAdmision,
            $d->cups,
            $d->nombreDelLaboratorio,
            $d->fechaTomaMuestra    ? new DateTimeImmutable($d->fechaTomaMuestra)  : null,
            $d->metodo,
            $d->reactivo,
            $d->invima,
            $d->estadoDelResultado,
            $d->fechaResultado      ? new DateTimeImmutable($d->fechaResultado)    : null,
            $d->tipoIdentificacionDelBacteriologo,
            $d->identificacionDelBacteriologo
        ), $dto->detalles);

        foreach ($details as $detail) {
            $order->addDetail($detail);
        }

        $this->orderRepository->save($order);
        $this->detailRepository->saveMany($details);

        return $order;
    }

    // ── Helpers privados ──────────────────────────────────────────────────────

    private function resolvePatient(LabOrderRequestDto $dto): Patient
    {
        $existing = $this->patientRepository->findByDocument(
            $dto->tipoDeDocumento,
            $dto->identificacion
        );

        if ($existing) {
            return $existing;
        }

        $newPatient = new Patient(
            0,
            $dto->tipoDeDocumento,
            $dto->identificacion,
            $dto->nombreDelPaciente,
            $dto->sexo,
            new DateTimeImmutable($dto->fechaDeNacimiento)
        );

        $id = $this->patientRepository->save($newPatient);

        return new Patient(
            $id,
            $dto->tipoDeDocumento,
            $dto->identificacion,
            $dto->nombreDelPaciente,
            $dto->sexo,
            new DateTimeImmutable($dto->fechaDeNacimiento)
        );
    }

    private function resolveHealthCenter(LabOrderRequestDto $dto): ?int
    {
        // Prioridad 1: ID explícito en el body
        if ($dto->healthCenterId !== null) {
            return $dto->healthCenterId;
        }

        // Prioridad 2: buscar por nombre exacto en el catálogo
        $center = $this->healthCenterRepository->findByNombre($dto->centroDeSalud);
        return $center?->getId();
    }

    private function resolveMedico(LabOrderRequestDto $dto): ?int
    {
        // Prioridad 1: ID explícito
        if ($dto->medicoId !== null) {
            $medico = $this->medicoRepository->findById($dto->medicoId);
            if (!$medico || !$medico->isActivo()) {
                throw new \RuntimeException("Médico no encontrado o inactivo: {$dto->medicoId}");
            }
            return $medico->getId();
        }

        // Prioridad 2: buscar por documento
        if ($dto->tipoDocumentoMedico !== null && $dto->identificacionMedico !== null) {
            $medico = $this->medicoRepository->findByDocument(
                $dto->tipoDocumentoMedico,
                $dto->identificacionMedico
            );
            if (!$medico || !$medico->isActivo()) {
                throw new \RuntimeException(
                    "Médico no encontrado con documento {$dto->tipoDocumentoMedico} {$dto->identificacionMedico}"
                );
            }
            return $medico->getId();
        }

        // Sin médico — campo opcional
        return null;
    }
}
