<?php

namespace ClinicalLab\Application\Dto;

class HealthCenterDto
{
    public function __construct(
        public readonly string  $nombre,
        public readonly ?string $ciudad    = null,
        public readonly ?string $direccion = null,
        public readonly ?string $telefono  = null,
        public readonly bool    $activo    = true,
    ) {
    }
}
