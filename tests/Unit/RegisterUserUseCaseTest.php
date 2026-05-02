<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Application\Dto\RegisterUserDto;
use ClinicalLab\Application\UseCase\RegisterUserUseCase;
use ClinicalLab\Domain\Entity\Aliado;
use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RegisterUserUseCaseTest extends TestCase
{
    public function testRegistersUserAndAssignsAliados(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn(null);
        $userRepo->method('findByEmail')->willReturn(null);
        $userRepo->expects($this->once())->method('save')->willReturn(10);
        $userRepo->expects($this->exactly(2))
            ->method('assignAliado')
            ->with(10, $this->logicalOr('ALLY-1', 'ALLY-2'));

        $aliadoRepo = $this->createMock(AliadoRepositoryInterface::class);
        $aliadoRepo->method('findById')->willReturn(
            new Aliado('ALLY-1', 'Lab Norte', true)
        );

        $dto = new RegisterUserDto('jdoe', 'jdoe@lab.com', 'pass123', Role::LAB_OPERATOR, ['ALLY-1', 'ALLY-2']);

        $userId = (new RegisterUserUseCase($userRepo, $aliadoRepo))->execute($dto);

        $this->assertSame(10, $userId);
    }

    public function testRegistersUserWithoutAliados(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn(null);
        $userRepo->method('findByEmail')->willReturn(null);
        $userRepo->expects($this->once())->method('save')->willReturn(5);
        $userRepo->expects($this->never())->method('assignAliado');

        $aliadoRepo = $this->createMock(AliadoRepositoryInterface::class);

        $dto = new RegisterUserDto('admin', 'admin@lab.com', 'admin123', Role::ADMIN);

        $userId = (new RegisterUserUseCase($userRepo, $aliadoRepo))->execute($dto);

        $this->assertSame(5, $userId);
    }

    public function testThrowsOnInvalidRole(): void
    {
        $userRepo   = $this->createMock(UserRepositoryInterface::class);
        $aliadoRepo = $this->createMock(AliadoRepositoryInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rol inválido: superuser');

        (new RegisterUserUseCase($userRepo, $aliadoRepo))
            ->execute(new RegisterUserDto('u', 'u@x.com', 'p', 'superuser'));
    }

    public function testThrowsWhenUsernameAlreadyExists(): void
    {
        $existingUser = $this->createMock(\ClinicalLab\Domain\Entity\User::class);

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn($existingUser);

        $aliadoRepo = $this->createMock(AliadoRepositoryInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El username ya está en uso');

        (new RegisterUserUseCase($userRepo, $aliadoRepo))
            ->execute(new RegisterUserDto('jdoe', 'new@lab.com', 'pass', Role::VIEWER));
    }

    public function testThrowsWhenEmailAlreadyExists(): void
    {
        $existingUser = $this->createMock(\ClinicalLab\Domain\Entity\User::class);

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn(null);
        $userRepo->method('findByEmail')->willReturn($existingUser);

        $aliadoRepo = $this->createMock(AliadoRepositoryInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El email ya está en uso');

        (new RegisterUserUseCase($userRepo, $aliadoRepo))
            ->execute(new RegisterUserDto('newuser', 'taken@lab.com', 'pass', Role::VIEWER));
    }

    public function testThrowsWhenAliadoNotFound(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn(null);
        $userRepo->method('findByEmail')->willReturn(null);

        $aliadoRepo = $this->createMock(AliadoRepositoryInterface::class);
        $aliadoRepo->method('findById')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aliado no encontrado: GHOST-99');

        (new RegisterUserUseCase($userRepo, $aliadoRepo))
            ->execute(new RegisterUserDto('u', 'u@x.com', 'p', Role::ALIADO_OPERATOR, ['GHOST-99']));
    }
}
