<?php

namespace ClinicalLab\Domain\Entity;

class ExamParameterRange
{
    public function __construct(
        private readonly int     $id,
        private readonly int     $parameterId,
        private readonly string  $reactivo,
        private readonly ?float  $valorMinRef,
        private readonly ?float  $valorMaxRef,
        private readonly string  $sexo,
        private readonly ?int    $edadMin,
        private readonly ?int    $edadMax,
        private readonly bool    $activo
    ) {
    }

    public function getId(): int            { return $this->id; }
    public function getParameterId(): int   { return $this->parameterId; }
    public function getReactivo(): string   { return $this->reactivo; }
    public function getValorMinRef(): ?float { return $this->valorMinRef; }
    public function getValorMaxRef(): ?float { return $this->valorMaxRef; }
    public function getSexo(): string       { return $this->sexo; }
    public function getEdadMin(): ?int      { return $this->edadMin; }
    public function getEdadMax(): ?int      { return $this->edadMax; }
    public function isActivo(): bool        { return $this->activo; }

    /**
     * Calcula el flag numérico usando los rangos de este reactivo.
     */
    public function calcularFlag(float $valor): string
    {
        if ($this->valorMinRef === null && $this->valorMaxRef === null) {
            return 'indeterminado';
        }

        $bajoCritico = $this->valorMinRef !== null ? $this->valorMinRef * 0.7 : null;
        $altoCritico = $this->valorMaxRef !== null ? $this->valorMaxRef * 1.3 : null;

        if ($bajoCritico !== null && $valor < $bajoCritico) return 'critico';
        if ($altoCritico !== null && $valor > $altoCritico) return 'critico';
        if ($this->valorMinRef !== null && $valor < $this->valorMinRef) return 'bajo';
        if ($this->valorMaxRef !== null && $valor > $this->valorMaxRef) return 'alto';

        return 'normal';
    }
}
