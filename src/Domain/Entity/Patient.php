<?php

namespace ClinicalLab\Domain\Entity;

use DateTimeImmutable;

class Patient
{
    public function __construct(
        private readonly int               $id,
        private readonly string            $tipoDocumento,
        private readonly string            $identificacion,
        private readonly string            $nombre,
        private readonly string            $sexo,
        private readonly DateTimeImmutable $fechaNacimiento,
        private readonly ?string           $email    = null,
        private readonly ?string           $telefono = null,
    ) {
    }

    public function getId(): int                           { return $this->id; }
    public function getTipoDocumento(): string             { return $this->tipoDocumento; }
    public function getIdentificacion(): string            { return $this->identificacion; }
    public function getNombre(): string                    { return $this->nombre; }
    public function getSexo(): string                      { return $this->sexo; }
    public function getFechaNacimiento(): DateTimeImmutable { return $this->fechaNacimiento; }
    public function getEmail(): ?string                    { return $this->email; }
    public function getTelefono(): ?string                 { return $this->telefono; }
}
