<?php

namespace ClinicalLab\Domain\Entity;

use DateTimeImmutable;

class LabResult
{
    public function __construct(
        private string            $idSolicitudKey,
        private string            $cups,
        private array             $values,
        private ?string           $attachmentPath,
        private DateTimeImmutable $receivedAt,
        private ?int              $bacteriologoId = null,
    ) {
    }

    public function getIdSolicitudKey(): string      { return $this->idSolicitudKey; }
    public function getCups(): string                { return $this->cups; }
    public function getValues(): array               { return $this->values; }
    public function getAttachmentPath(): ?string     { return $this->attachmentPath; }
    public function getReceivedAt(): DateTimeImmutable { return $this->receivedAt; }
    public function getBacteriologoId(): ?int        { return $this->bacteriologoId; }
}
