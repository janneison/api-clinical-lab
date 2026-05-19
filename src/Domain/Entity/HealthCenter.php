<?php

namespace ClinicalLab\Domain\Entity;

class HealthCenter
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $nombre,
        private readonly ?string $ciudad,
        private readonly ?string $direccion,
        private readonly ?string $telefono,
        private readonly bool    $activo
    ) {
    }

    public function getId(): int          { return $this->id; }
    public function getNombre(): string   { return $this->nombre; }
    public function getCiudad(): ?string  { return $this->ciudad; }
    public function getDireccion(): ?string { return $this->direccion; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function isActivo(): bool      { return $this->activo; }
}
