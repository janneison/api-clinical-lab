<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Application\Dto\LoginDto;
use ClinicalLab\Application\UseCase\LoginUseCase;
use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Entity\User;
use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use ClinicalLab\Domain\Service\TokenServiceInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LoginUseCaseTest extends TestCase
{
    private function makeUser(bool $activo = true, array $aliados = []): User
    {
        $role = new Role(1, Role::LAB_OPERATOR);
        $user = new User(
            42,
            'jdoe',
            'jdoe@lab.com',
            password_hash('secret123', PASSWORD_BCRYPT),
            $role,
            $activo
        );
        $user->setAliadoIds($aliados);
        return $user;
    }

    public function testReturnsTokenAndUserOnValidCredentials(): void
    {
        $user = $this->makeUser(aliados: ['ALLY-1']);

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->with('jdoe')->willReturn($user);

        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->expects($this->once())
            ->method('generate')
            ->with($this->callback(fn($c) =>
                $c['sub'] === 42 &&
                $c['username'] === 'jdoe' &&
                $c['role'] === Role::LAB_OPERATOR &&
                $c['aliados'] === ['ALLY-1']
            ))
            ->willReturn('jwt.token.here');

        $result = (new LoginUseCase($userRepo, $tokenService))
            ->execute(new LoginDto('jdoe', 'secret123'));

        $this->assertSame('jwt.token.here', $result['token']);
        $this->assertSame(42, $result['user']['id']);
        $this->assertSame('jdoe', $result['user']['username']);
        $this->assertSame(Role::LAB_OPERATOR, $result['user']['role']);
        $this->assertSame(['ALLY-1'], $result['user']['aliados']);
    }

    public function testThrowsOnWrongPassword(): void
    {
        $user = $this->makeUser();

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn($user);

        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->expects($this->never())->method('generate');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Credenciales inválidas');

        (new LoginUseCase($userRepo, $tokenService))
            ->execute(new LoginDto('jdoe', 'wrong'));
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn(null);

        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->expects($this->never())->method('generate');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Credenciales inválidas');

        (new LoginUseCase($userRepo, $tokenService))
            ->execute(new LoginDto('ghost', 'pass'));
    }

    public function testThrowsWhenUserIsInactive(): void
    {
        $user = $this->makeUser(activo: false);

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->method('findByUsername')->willReturn($user);

        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->expects($this->never())->method('generate');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Credenciales inválidas');

        (new LoginUseCase($userRepo, $tokenService))
            ->execute(new LoginDto('jdoe', 'secret123'));
    }
}
