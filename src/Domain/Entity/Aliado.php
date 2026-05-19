<?php

namespace ClinicalLab\Domain\Entity;

class Aliado
{
    public function __construct(
        private readonly string  $id,
        private readonly string  $nombre,
        private readonly bool    $activo,
        private readonly ?string $nit       = null,
        private readonly ?string $direccion = null,
        private readonly ?string $email     = null,
        private readonly ?string $logoPath  = null,
    ) {
    }

    public function getId(): string       { return $this->id; }
    public function getNombre(): string   { return $this->nombre; }
    public function isActivo(): bool      { return $this->activo; }
    public function getNit(): ?string     { return $this->nit; }
    public function getDireccion(): ?string { return $this->direccion; }
    public function getEmail(): ?string   { return $this->email; }
    public function getLogoPath(): ?string { return $this->logoPath; }
}
