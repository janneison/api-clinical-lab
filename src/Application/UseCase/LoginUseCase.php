<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\LoginDto;
use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use ClinicalLab\Domain\Service\TokenServiceInterface;
use RuntimeException;

class LoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenServiceInterface   $tokenService
    ) {
    }

    /**
     * @return array{token: string, user: array}
     */
    public function execute(LoginDto $dto): array
    {
        $user = $this->userRepository->findByUsername($dto->username);

        if (!$user || !$user->isActivo()) {
            throw new RuntimeException('Credenciales inválidas');
        }

        if (!$user->verifyPassword($dto->password)) {
            throw new RuntimeException('Credenciales inválidas');
        }

        $token = $this->tokenService->generate([
            'sub'      => $user->getId(),
            'username' => $user->getUsername(),
            'role'     => $user->getRole()->getName(),
            'aliados'  => $user->getAliadoIds(),
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'       => $user->getId(),
                'username' => $user->getUsername(),
                'email'    => $user->getEmail(),
                'role'     => $user->getRole()->getName(),
                'aliados'  => $user->getAliadoIds(),
            ],
        ];
    }
}
