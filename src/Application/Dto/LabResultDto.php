<?php

namespace ClinicalLab\Application\Dto;

class LabResultDto
{
    /**
     * @param AntibiogramaDto[] $antibiogramas
     */
    public function __construct(
        public readonly string  $idSolicitudKey,
        public readonly string  $cups,
        public readonly array   $values,
        public readonly ?string $attachmentPath,
        public readonly ?int    $bacteriologoId  = null,
        public readonly array   $antibiogramas   = [],  // solo para exámenes de cultivo
    ) {
    }
}
