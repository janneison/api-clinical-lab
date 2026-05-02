<?php

namespace ClinicalLab\Application\Dto;

class LoginDto
{
    public function __construct(
        public readonly string $username,
        public readonly string $password
    ) {
    }
}
