<?php

namespace ClinicalLab\Domain\Service;

interface TokenServiceInterface
{
    public function generate(array $claims): string;

    /**
     * @return array<string, mixed>
     * @throws \RuntimeException si el token es inválido o expiró
     */
    public function validate(string $token): array;
}
