<?php

namespace ClinicalLab\Application\Dto;

class ExamParameterRangeDto
{
    public function __construct(
        public readonly int     $parameterId,
        public readonly string  $reactivo,
        public readonly ?float  $valorMinRef = null,
        public readonly ?float  $valorMaxRef = null,
        public readonly string  $sexo        = '*',
        public readonly ?int    $edadMin     = null,
        public readonly ?int    $edadMax     = null,
    ) {
    }
}
