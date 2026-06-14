<?php

namespace ClinicalLab\Domain\Entity;

class Role
{
    public const ADMIN            = 'admin';
    public const LAB_OPERATOR     = 'lab_operator';
    public const ALIADO_OPERATOR  = 'aliado_operator';
    public const VIEWER           = 'viewer';
    public const MEDICO           = 'medico';

    public function __construct(
        private readonly int    $id,
        private readonly string $name
    ) {
    }

    public function getId(): int   { return $this->id; }
    public function getName(): string { return $this->name; }
}
