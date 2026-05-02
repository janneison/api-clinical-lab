<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

class UserEntityTest extends TestCase
{
    private function makeUser(string $role = Role::ADMIN): User
    {
        return new User(
            1,
            'jdoe',
            'jdoe@lab.com',
            password_hash('secret', PASSWORD_BCRYPT),
            new Role(1, $role),
            true
        );
    }

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $user = $this->makeUser();
        $this->assertTrue($user->verifyPassword('secret'));
    }

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $user = $this->makeUser();
        $this->assertFalse($user->verifyPassword('wrong'));
    }

    public function testHasRoleReturnsTrueForMatchingRole(): void
    {
        $user = $this->makeUser(Role::LAB_OPERATOR);
        $this->assertTrue($user->hasRole(Role::LAB_OPERATOR));
    }

    public function testHasRoleReturnsFalseForDifferentRole(): void
    {
        $user = $this->makeUser(Role::VIEWER);
        $this->assertFalse($user->hasRole(Role::ADMIN));
    }

    public function testBelongsToAliadoReturnsTrueWhenAssigned(): void
    {
        $user = $this->makeUser();
        $user->setAliadoIds(['ALLY-1', 'ALLY-2']);
        $this->assertTrue($user->belongsToAliado('ALLY-1'));
    }

    public function testBelongsToAliadoReturnsFalseWhenNotAssigned(): void
    {
        $user = $this->makeUser();
        $user->setAliadoIds(['ALLY-1']);
        $this->assertFalse($user->belongsToAliado('ALLY-99'));
    }

    public function testGetAliadoIdsReturnsEmptyByDefault(): void
    {
        $user = $this->makeUser();
        $this->assertSame([], $user->getAliadoIds());
    }
}
