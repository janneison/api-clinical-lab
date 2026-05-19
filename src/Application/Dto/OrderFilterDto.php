<?php

namespace ClinicalLab\Application\Dto;

class OrderFilterDto
{
    /**
     * @param string[]|null $aliadoIds  null = sin restricción (admin/lab_operator)
     *                                  array vacío = sin acceso
     *                                  array con ids = filtrar por esos aliados
     */
    public function __construct(
        public readonly ?array  $aliadoIds  = null,
        public readonly ?string $estado     = null,
        public readonly ?string $fechaDesde = null,
        public readonly ?string $fechaHasta = null,
        public readonly ?string $cups       = null,
        public readonly int     $page       = 1,
        public readonly int     $limit      = 20,
        public readonly ?int    $patientId  = null,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
