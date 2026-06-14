<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Application\Dto\RegisterUserDto;
use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Entity\User;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use ClinicalLab\Domain\Service\PasswordPolicyService;
use InvalidArgumentException;
use RuntimeException;

class RegisterUserUseCase
{
    private const VALID_ROLES = [
        Role::ADMIN,
        Role::LAB_OPERATOR,
        Role::ALIADO_OPERATOR,
        Role::VIEWER,
        Role::MEDICO,
    ];

    public function __construct(
        private readonly UserRepositoryInterface   $userRepository,
        private readonly AliadoRepositoryInterface $aliadoRepository,
        private readonly PasswordPolicyService     $passwordPolicy = new PasswordPolicyService(),
    ) {
    }

    public function execute(RegisterUserDto $dto): int
    {
        if (!in_array($dto->roleName, self::VALID_ROLES, true)) {
            throw new RuntimeException("Rol inválido: {$dto->roleName}");
        }

        if ($this->userRepository->findByUsername($dto->username)) {
            throw new RuntimeException('El username ya está en uso');
        }

        if ($this->userRepository->findByEmail($dto->email)) {
            throw new RuntimeException('El email ya está en uso');
        }

        // Validar política de contraseñas
        try {
            $this->passwordPolicy->validate($dto->password);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage());
        }

        // Validar que los aliados existan
        foreach ($dto->aliadoIds as $aliadoId) {
            if (!$this->aliadoRepository->findById($aliadoId)) {
                throw new RuntimeException("Aliado no encontrado: {$aliadoId}");
            }
        }

        $passwordHash = password_hash($dto->password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Role temporal para construir el User — el repositorio resuelve el role_id real
        $role = new Role(0, $dto->roleName);

        $user = new User(0, $dto->username, $dto->email, $passwordHash, $role, true);

        $userId = $this->userRepository->save($user);

        foreach ($dto->aliadoIds as $aliadoId) {
            $this->userRepository->assignAliado($userId, $aliadoId);
        }

        foreach ($dto->healthCenterIds as $hcId) {
            $this->userRepository->assignHealthCenter($userId, (int) $hcId);
        }

        return $userId;
    }
}
