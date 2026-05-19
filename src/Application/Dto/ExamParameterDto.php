<?php

namespace ClinicalLab\Application\Dto;

use ClinicalLab\Domain\Entity\ExamParameter;

class ExamParameterDto
{
    public function __construct(
        public readonly string  $cups,
        public readonly string  $codigo,
        public readonly string  $nombre,
        public readonly ?string $unidad           = null,
        public readonly ?float  $valorMinRef      = null,
        public readonly ?float  $valorMaxRef      = null,
        public readonly string  $sexo             = '*',
        public readonly ?int    $edadMin          = null,
        public readonly ?int    $edadMax          = null,
        public readonly bool    $obligatorio      = false,
        public readonly int     $orden            = 0,
        public readonly string  $tipoResultado    = ExamParameter::TIPO_NUMERICO,
        public readonly ?string $etiquetaBooleano = null,
    ) {
    }
}
