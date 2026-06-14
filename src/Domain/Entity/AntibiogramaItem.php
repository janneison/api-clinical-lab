<?php

namespace ClinicalLab\Domain\Entity;

/**
 * Una fila del antibiograma: antibiótico + CIM + sensibilidad.
 */
class AntibiogramaItem
{
    public const SENSIBILIDAD_SENSIBLE     = 'S';
    public const SENSIBILIDAD_INTERMEDIO   = 'I';
    public const SENSIBILIDAD_RESISTENTE   = 'R';

    public function __construct(
        private readonly int     $id,
        private readonly int     $antibiogramaId,
        private readonly string  $antibiotico,       // ej. "Ampicilina"
        private readonly ?string $cim,               // ej. "≤2" | "8" | ">16"
        private readonly ?string $sensibilidad,      // S | I | R
        private readonly ?string $metodo,            // ej. "MIC automatico" | "Kirby-Bauer"
    ) {
    }

    public function getId(): int                { return $this->id; }
    public function getAntibiogramaId(): int    { return $this->antibiogramaId; }
    public function getAntibiotico(): string    { return $this->antibiotico; }
    public function getCim(): ?string           { return $this->cim; }
    public function getSensibilidad(): ?string  { return $this->sensibilidad; }
    public function getMetodo(): ?string        { return $this->metodo; }
}
