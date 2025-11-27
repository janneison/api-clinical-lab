<?php

namespace ClinicalLab\Application\Dto;

class LabResultDto
{
    public function __construct(
        public readonly string $idSolicitudKey,
        public readonly string $cups,
        public readonly array $values,
        public readonly ?string $attachmentPath
    ) {
    }
}
