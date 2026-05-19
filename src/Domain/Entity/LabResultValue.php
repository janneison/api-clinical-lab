<?php

namespace ClinicalLab\Domain\Entity;

class LabResultValue
{
    public function __construct(
        private readonly int     $id,
        private readonly int     $labResultId,
        private readonly int     $parameterId,
        private readonly ?float  $valorNumerico,
        private readonly ?string $valorTexto,
        private readonly ?bool   $valorBooleano,
        private readonly string  $flag,
        private readonly ?string $reactivo = null,
    ) {
    }

    public function getId(): int               { return $this->id; }
    public function getLabResultId(): int      { return $this->labResultId; }
    public function getParameterId(): int      { return $this->parameterId; }
    public function getValorNumerico(): ?float { return $this->valorNumerico; }
    public function getValorTexto(): ?string   { return $this->valorTexto; }
    public function getValorBooleano(): ?bool  { return $this->valorBooleano; }
    public function getFlag(): string          { return $this->flag; }
    public function getReactivo(): ?string     { return $this->reactivo; }
}
