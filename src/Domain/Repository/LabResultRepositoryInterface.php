<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\LabResult;

interface LabResultRepositoryInterface
{
    public function save(LabResult $result): int;

    public function findIdByOrderAndCups(string $idSolicitudKey, string $cups): ?int;

    /** @return array<int, array{id: int, cups: string, values_json: string, attachment_path: string|null, received_at: string}> */
    public function findAllByOrder(string $idSolicitudKey): array;

    public function findAttachmentByOrder(string $idSolicitudKey): ?string;

    public function updateAttachmentPath(string $idSolicitudKey, string $attachmentPath): void;
}
