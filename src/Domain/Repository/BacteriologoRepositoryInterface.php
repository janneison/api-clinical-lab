<?php

namespace ClinicalLab\Domain\Repository;

use ClinicalLab\Domain\Entity\Bacteriologo;

interface BacteriologoRepositoryInterface
{
    public function findById(int $id): ?Bacteriologo;

    public function findByDocument(string $tipoDocumento, string $identificacion): ?Bacteriologo;

    /** @return Bacteriologo[] */
    public function findByAliadoId(string $aliadoId, bool $soloActivos = true): array;

    public function save(Bacteriologo $bacteriologo): int;

    public function update(Bacteriologo $bacteriologo): void;

    public function updateFirma(int $id, string $firmaPath): void;

    public function deactivate(int $id): void;
}
