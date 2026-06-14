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
     * @throws RuntimeException si las credenciales son inválidas o la cuenta está bloqueada.
     */
    public function execute(LoginDto $dto): array
    {
        $user = $this->userRepository->findByUsername($dto->username);

        // No revelar si el usuario existe o no (evita enumeración)
        if (!$user || !$user->isActivo()) {
            throw new RuntimeException('Credenciales inválidas');
        }

        // Verificar bloqueo por intentos fallidos
        if ($user->isLocked()) {
            $lockedUntil = $user->getLockedUntil()->format('d/m/Y H:i');
            throw new RuntimeException(
                "Cuenta bloqueada por múltiples intentos fallidos. Intente de nuevo después de las {$lockedUntil} o use la opción de recuperar contraseña."
            );
        }

        if (!$user->verifyPassword($dto->password)) {
            // Registrar intento fallido
            $this->userRepository->incrementFailedAttempts($user->getId());

            // Recargar para saber cuántos intentos quedan
            $updated  = $this->userRepository->findById($user->getId());
            $attempts = $updated?->getFailedLoginAttempts() ?? 0;
            $max      = 4;
            $left     = max(0, $max - $attempts);

            if ($left === 0) {
                throw new RuntimeException(
                    'Cuenta bloqueada por múltiples intentos fallidos. Use la opción de recuperar contraseña.'
                );
            }

            throw new RuntimeException(
                "Credenciales inválidas. Intentos restantes antes del bloqueo: {$left}."
            );
        }

        // Login exitoso: resetear contador
        $this->userRepository->resetFailedAttempts($user->getId());

        $token = $this->tokenService->generate([
            'sub'              => $user->getId(),
            'username'         => $user->getUsername(),
            'role'             => $user->getRole()->getName(),
            'aliados'          => $user->getAliadoIds(),
            'health_centers'   => $user->getHealthCenterIds(),
        ]);

        return [
            'token' => $token,
            'user'  => [
                'id'             => $user->getId(),
                'username'       => $user->getUsername(),
                'email'          => $user->getEmail(),
                'role'           => $user->getRole()->getName(),
                'aliados'        => $user->getAliadoIds(),
                'health_centers' => $user->getHealthCenterIds(),
            ],
        ];
    }
}
