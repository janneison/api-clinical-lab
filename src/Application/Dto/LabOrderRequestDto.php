<?php

namespace ClinicalLab\Application\Dto;

class LabOrderRequestDto
{
    public function __construct(
        public readonly string $idSolicitudKey,
        public readonly string $idAdmision,
        public readonly ?string $idAtencion,
        public readonly string $tipoDeDocumento,
        public readonly string $identificacion,
        public readonly string $nombreDelPaciente,
        public readonly string $sexo,
        public readonly string $fechaDeNacimiento,
        public readonly string $centroDeSalud,
        public readonly string $fechaDeLaOrden,
        public readonly string $medicoQueOrdena,
        public readonly ?string $numeroDeAutorizacion,
        public readonly ?string $idAliado,
        public readonly ?string $porcEjecucion,
        /** @var LabOrderDetailDto[] */
        public readonly array $detalles
    ) {
    }
}
