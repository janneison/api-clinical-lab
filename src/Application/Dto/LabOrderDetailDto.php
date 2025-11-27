<?php

namespace ClinicalLab\Application\Dto;

class LabOrderDetailDto
{
    public function __construct(
        public readonly string $idSolicitudKey,
        public readonly string $idAdmision,
        public readonly string $cups,
        public readonly string $nombreDelLaboratorio,
        public readonly ?string $fechaTomaMuestra,
        public readonly ?string $metodo,
        public readonly ?string $reactivo,
        public readonly ?string $invima,
        public readonly ?string $estadoDelResultado,
        public readonly ?string $fechaResultado,
        public readonly ?string $tipoIdentificacionDelBacteriologo,
        public readonly ?string $identificacionDelBacteriologo
    ) {
    }
}
