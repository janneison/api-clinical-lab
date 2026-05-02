<?php

namespace ClinicalLab\Application\Dto;

class RegisterUserDto
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $password,
        public readonly string $roleName,
        /** @var string[] */
        public readonly array  $aliadoIds = []
    ) {
    }
}
