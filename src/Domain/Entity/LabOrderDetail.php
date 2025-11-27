<?php

namespace ClinicalLab\Domain\Entity;

use DateTimeImmutable;

class LabOrderDetail
{
    public function __construct(
        private string $idSolicitudKey,
        private string $idAdmision,
        private string $cups,
        private string $nombreDelLaboratorio,
        private ?DateTimeImmutable $fechaTomaMuestra,
        private ?string $metodo,
        private ?string $reactivo,
        private ?string $invima,
        private ?string $estadoDelResultado,
        private ?DateTimeImmutable $fechaResultado,
        private ?string $tipoIdentificacionDelBacteriologo,
        private ?string $identificacionDelBacteriologo
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

    public function getCups(): string
    {
        return $this->cups;
    }

    public function getNombreDelLaboratorio(): string
    {
        return $this->nombreDelLaboratorio;
    }

    public function getFechaTomaMuestra(): ?DateTimeImmutable
    {
        return $this->fechaTomaMuestra;
    }

    public function getMetodo(): ?string
    {
        return $this->metodo;
    }

    public function getReactivo(): ?string
    {
        return $this->reactivo;
    }

    public function getInvima(): ?string
    {
        return $this->invima;
    }

    public function getEstadoDelResultado(): ?string
    {
        return $this->estadoDelResultado;
    }

    public function getFechaResultado(): ?DateTimeImmutable
    {
        return $this->fechaResultado;
    }

    public function getTipoIdentificacionDelBacteriologo(): ?string
    {
        return $this->tipoIdentificacionDelBacteriologo;
    }

    public function getIdentificacionDelBacteriologo(): ?string
    {
        return $this->identificacionDelBacteriologo;
    }
}
