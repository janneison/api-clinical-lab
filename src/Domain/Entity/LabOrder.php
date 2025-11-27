<?php

namespace ClinicalLab\Domain\Entity;

use DateTimeImmutable;

class LabOrder
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_COMPLETED = 'completed';

    /** @var LabOrderDetail[] */
    private array $details = [];

    public function __construct(
        private string $idSolicitudKey,
        private string $idAdmision,
        private ?string $idAtencion,
        private string $tipoDeDocumento,
        private string $identificacion,
        private string $nombreDelPaciente,
        private string $sexo,
        private DateTimeImmutable $fechaDeNacimiento,
        private string $centroDeSalud,
        private DateTimeImmutable $fechaDeLaOrden,
        private string $medicoQueOrdena,
        private ?string $numeroDeAutorizacion,
        private ?string $idAliado,
        private ?DateTimeImmutable $fechaEnvio,
        private float $porcEjecucion = 0.0,
        private string $estadoDeLaOrden = self::STATUS_PENDING
    ) {
    }

    public function getIdSolicitudKey(): string
    {
        return $this->idSolicitudKey;
    }

    public function getIdAdmision(): string
    {
        return $this->idAdmision;
    }

    public function getIdAtencion(): ?string
    {
        return $this->idAtencion;
    }

    public function getTipoDeDocumento(): string
    {
        return $this->tipoDeDocumento;
    }

    public function getIdentificacion(): string
    {
        return $this->identificacion;
    }

    public function getNombreDelPaciente(): string
    {
        return $this->nombreDelPaciente;
    }

    public function getSexo(): string
    {
        return $this->sexo;
    }

    public function getFechaDeNacimiento(): DateTimeImmutable
    {
        return $this->fechaDeNacimiento;
    }

    public function getCentroDeSalud(): string
    {
        return $this->centroDeSalud;
    }

    public function getFechaDeLaOrden(): DateTimeImmutable
    {
        return $this->fechaDeLaOrden;
    }

    public function getMedicoQueOrdena(): string
    {
        return $this->medicoQueOrdena;
    }

    public function getNumeroDeAutorizacion(): ?string
    {
        return $this->numeroDeAutorizacion;
    }

    public function getIdAliado(): ?string
    {
        return $this->idAliado;
    }

    public function getFechaEnvio(): ?DateTimeImmutable
    {
        return $this->fechaEnvio;
    }

    public function getPorcEjecucion(): float
    {
        return $this->porcEjecucion;
    }

    public function getEstadoDeLaOrden(): string
    {
        return $this->estadoDeLaOrden;
    }

    /**
     * @return LabOrderDetail[]
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function addDetail(LabOrderDetail $detail): void
    {
        $this->details[] = $detail;
    }

    public function markAsSent(DateTimeImmutable $fechaEnvio): void
    {
        $this->fechaEnvio = $fechaEnvio;
        $this->estadoDeLaOrden = self::STATUS_SENT;
    }

    public function updateProgress(float $percentage): void
    {
        $this->porcEjecucion = max(0, min(100, $percentage));
        if ($this->porcEjecucion >= 100) {
            $this->estadoDeLaOrden = self::STATUS_COMPLETED;
        }
    }
}
