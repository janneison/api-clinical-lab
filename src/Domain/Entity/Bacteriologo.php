<?php

namespace ClinicalLab\Domain\Entity;

class Bacteriologo
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $aliadoId,
        private readonly string  $tipoDocumento,
        private readonly string  $identificacion,
        private readonly string  $nombre,
        private readonly ?string $tarjetaProfesional,
        private readonly ?string $universidad,
        private readonly ?string $firmaPath,
        private readonly bool    $activo,
    ) {
    }

    public function getId(): int                      { return $this->id; }
    public function getAliadoId(): string             { return $this->aliadoId; }
    public function getTipoDocumento(): string        { return $this->tipoDocumento; }
    public function getIdentificacion(): string       { return $this->identificacion; }
    public function getNombre(): string               { return $this->nombre; }
    public function getTarjetaProfesional(): ?string  { return $this->tarjetaProfesional; }
    public function getUniversidad(): ?string         { return $this->universidad; }
    public function getFirmaPath(): ?string           { return $this->firmaPath; }
    public function isActivo(): bool                  { return $this->activo; }
}
