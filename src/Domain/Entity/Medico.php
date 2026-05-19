<?php

namespace ClinicalLab\Domain\Entity;

class Medico
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $tipoDocumento,
        private readonly string  $identificacion,
        private readonly string  $nombre,
        private readonly ?string $especialidad,
        private readonly ?string $registroMedico,   // número de registro profesional
        private readonly ?int    $userId,            // FK → users (opcional)
        private readonly bool    $activo,
    ) {
    }

    public function getId(): int               { return $this->id; }
    public function getTipoDocumento(): string  { return $this->tipoDocumento; }
    public function getIdentificacion(): string { return $this->identificacion; }
    public function getNombre(): string         { return $this->nombre; }
    public function getEspecialidad(): ?string  { return $this->especialidad; }
    public function getRegistroMedico(): ?string { return $this->registroMedico; }
    public function getUserId(): ?int           { return $this->userId; }
    public function isActivo(): bool            { return $this->activo; }
}
