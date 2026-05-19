<?php

namespace ClinicalLab\Application\Dto;

class ExamTypeDto
{
    public function __construct(
        public readonly string  $cups,
        public readonly string  $nombre,
        public readonly ?string $descripcion = null,
        public readonly bool    $activo      = true,
    ) {
    }
}
