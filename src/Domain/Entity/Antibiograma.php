<?php

namespace ClinicalLab\Domain\Entity;

/**
 * Resultado microbiológico de un cultivo.
 * Un lab_result puede tener uno o más antibiogramas (una por bacteria aislada).
 */
class Antibiograma
{
    public const GRAM_POSITIVO = 'positivo';
    public const GRAM_NEGATIVO = 'negativo';
    public const GRAM_NA       = 'n/a';

    /** @var AntibiogramaItem[] */
    private array $items = [];

    public function __construct(
        private readonly int     $id,
        private readonly int     $labResultId,
        private readonly string  $bacteriaAislada,       // ej. "E. coli" | "Negativo en 48h"
        private readonly ?string $gram,                  // positivo | negativo | n/a
        private readonly ?string $tiempoIncubacion,      // ej. "48 horas"
        private readonly ?string $gramOrina,             // texto del Gram directo de orina
        private readonly ?string $observaciones,
    ) {
    }

    public function getId(): int                  { return $this->id; }
    public function getLabResultId(): int         { return $this->labResultId; }
    public function getBacteriaAislada(): string  { return $this->bacteriaAislada; }
    public function getGram(): ?string            { return $this->gram; }
    public function getTiempoIncubacion(): ?string { return $this->tiempoIncubacion; }
    public function getGramOrina(): ?string       { return $this->gramOrina; }
    public function getObservaciones(): ?string   { return $this->observaciones; }

    /** @return AntibiogramaItem[] */
    public function getItems(): array             { return $this->items; }

    public function addItem(AntibiogramaItem $item): void
    {
        $this->items[] = $item;
    }

    public function isNegativo(): bool
    {
        return stripos($this->bacteriaAislada, 'negativo') !== false
            || stripos($this->bacteriaAislada, 'no growth') !== false;
    }
}
