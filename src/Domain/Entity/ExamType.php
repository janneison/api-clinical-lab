<?php

namespace ClinicalLab\Domain\Entity;

class ExamType
{
    public function __construct(
        private readonly string  $cups,
        private readonly string  $nombre,
        private readonly ?string $descripcion,
        private readonly bool    $activo
    ) {
    }

    public function getCups(): string        { return $this->cups; }
    public function getNombre(): string      { return $this->nombre; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function isActivo(): bool         { return $this->activo; }
}
