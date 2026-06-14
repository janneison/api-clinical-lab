<?php

namespace ClinicalLab\Domain\Entity;

class ExamParameter
{
    // Tipos de resultado
    public const TIPO_NUMERICO  = 'numerico';
    public const TIPO_TEXTO     = 'texto';
    public const TIPO_BOOLEANO  = 'booleano';

    // Etiquetas para booleano
    public const ETIQUETA_NORMAL_ALTO            = 'normal_alto';
    public const ETIQUETA_POSITIVO_NEGATIVO      = 'positivo_negativo';
    public const ETIQUETA_REACTIVO_NO_REACTIVO   = 'reactivo_no_reactivo';

    public function __construct(
        private readonly int     $id,
        private readonly string  $cups,
        private readonly string  $codigo,
        private readonly string  $nombre,
        private readonly ?string $unidad,
        private readonly ?float  $valorMinRef,
        private readonly ?float  $valorMaxRef,
        private readonly string  $sexo,
        private readonly ?int    $edadMin,
        private readonly ?int    $edadMax,
        private readonly bool    $obligatorio,
        private readonly int     $orden,
        private readonly bool    $activo,
        private readonly string  $tipoResultado    = self::TIPO_NUMERICO,
        private readonly ?string $etiquetaBooleano = null,
        private readonly ?string $comentario       = null,  // nota interpretativa (ej. "Valor deseable < 200 mg/dL")
    ) {
    }

    public function getId(): int              { return $this->id; }
    public function getCups(): string         { return $this->cups; }
    public function getCodigo(): string       { return $this->codigo; }
    public function getNombre(): string       { return $this->nombre; }
    public function getUnidad(): ?string      { return $this->unidad; }
    public function getValorMinRef(): ?float  { return $this->valorMinRef; }
    public function getValorMaxRef(): ?float  { return $this->valorMaxRef; }
    public function getSexo(): string         { return $this->sexo; }
    public function getEdadMin(): ?int        { return $this->edadMin; }
    public function getEdadMax(): ?int        { return $this->edadMax; }
    public function isObligatorio(): bool     { return $this->obligatorio; }
    public function getOrden(): int           { return $this->orden; }
    public function isActivo(): bool          { return $this->activo; }
    public function getTipoResultado(): string { return $this->tipoResultado; }
    public function getEtiquetaBooleano(): ?string { return $this->etiquetaBooleano; }
    public function getComentario(): ?string  { return $this->comentario; }

    // ── Cálculo de flags ──────────────────────────────────────────────────────

    /**
     * Flag para valor numérico usando los rangos del parámetro (fallback).
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

    /**
     * Flag para valor booleano según la etiqueta configurada.
     *
     * @param bool $valor  true = resultado positivo/reactivo/alto
     */
    public function calcularFlagBooleano(bool $valor): string
    {
        return match ($this->etiquetaBooleano) {
            self::ETIQUETA_POSITIVO_NEGATIVO    => $valor ? 'positivo'   : 'negativo',
            self::ETIQUETA_REACTIVO_NO_REACTIVO => $valor ? 'reactivo'   : 'no_reactivo',
            default                             => $valor ? 'alto'       : 'normal',
        };
    }
}
