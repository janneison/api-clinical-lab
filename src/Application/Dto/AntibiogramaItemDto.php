<?php

namespace ClinicalLab\Application\Dto;

class AntibiogramaItemDto
{
    public function __construct(
        public readonly string  $antibiotico,
        public readonly ?string $cim          = null,
        public readonly ?string $sensibilidad = null,  // S | I | R
        public readonly ?string $metodo       = null,
    ) {
    }
}
