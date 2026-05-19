<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\HealthCenterDto;
use ClinicalLab\Domain\Entity\HealthCenter;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use ClinicalLab\Domain\Repository\HealthCenterRepositoryInterface;
use RuntimeException;

class HealthCenterUseCase
{
    public function __construct(
        private readonly HealthCenterRepositoryInterface $healthCenterRepo,
        private readonly AliadoRepositoryInterface       $aliadoRepo,
    ) {
    }

    // ── Centros de salud ──────────────────────────────────────────────────────

    /** @return HealthCenter[] */
    public function list(bool $soloActivos = true, ?string $aliadoId = null): array
    {
        if ($aliadoId !== null) {
            return $this->healthCenterRepo->findByAliadoId($aliadoId);
        }
        return $this->healthCenterRepo->findAll($soloActivos);
    }

    public function create(HealthCenterDto $dto): int
    {
        return $this->healthCenterRepo->save(
            new HealthCenter(0, $dto->nombre, $dto->ciudad, $dto->direccion, $dto->telefono, $dto->activo)
        );
    }

    public function update(int $id, HealthCenterDto $dto): void
    {
        if (!$this->healthCenterRepo->findById($id)) {
            throw new RuntimeException("Centro de salud no encontrado: {$id}");
        }
        $this->healthCenterRepo->update(
            new HealthCenter($id, $dto->nombre, $dto->ciudad, $dto->direccion, $dto->telefono, $dto->activo)
        );
    }

    // ── Relación aliado ↔ centro ──────────────────────────────────────────────

    public function associateAliado(int $healthCenterId, string $aliadoId): void
    {
        if (!$this->healthCenterRepo->findById($healthCenterId)) {
            throw new RuntimeException("Centro de salud no encontrado: {$healthCenterId}");
        }
        if (!$this->aliadoRepo->findById($aliadoId)) {
            throw new RuntimeException("Aliado no encontrado: {$aliadoId}");
        }
        $this->healthCenterRepo->associateAliado($healthCenterId, $aliadoId);
    }

    public function dissociateAliado(int $healthCenterId, string $aliadoId): void
    {
        if (!$this->healthCenterRepo->findById($healthCenterId)) {
            throw new RuntimeException("Centro de salud no encontrado: {$healthCenterId}");
        }
        $this->healthCenterRepo->dissociateAliado($healthCenterId, $aliadoId);
    }
}
