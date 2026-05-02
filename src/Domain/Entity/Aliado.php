<?php

namespace ClinicalLab\Domain\Entity;

class Aliado
{
    public function __construct(
        private readonly string $id,
        private readonly string $nombre,
        private readonly bool   $activo
    ) {
    }

    public function getId(): string    { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function isActivo(): bool   { return $this->activo; }
}
