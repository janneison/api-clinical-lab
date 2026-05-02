<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Infrastructure\Auth\JwtTokenService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JwtTokenServiceTest extends TestCase
{
    private const SECRET = 'test-secret-key-at-least-32-chars!!';

    private function service(): JwtTokenService
    {
        return new JwtTokenService(self::SECRET);
    }

    public function testGeneratesAndValidatesToken(): void
    {
        $service = $this->service();

        $token = $service->generate([
            'sub'      => 1,
            'username' => 'jdoe',
            'role'     => 'admin',
            'aliados'  => ['ALLY-1'],
        ]);

        $this->assertNotEmpty($token);

        $claims = $service->validate($token);

        $this->assertSame(1, $claims['sub']);
        $this->assertSame('jdoe', $claims['username']);
        $this->assertSame('admin', $claims['role']);
        $this->assertSame('api-clinical-lab', $claims['iss']);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('iat', $claims);
    }

    public function testTokenContainsAliados(): void
    {
        $service = $this->service();
        $token   = $service->generate(['sub' => 2, 'aliados' => ['ALLY-A', 'ALLY-B']]);
        $claims  = $service->validate($token);

        // JWT decode convierte arrays a stdClass o array según versión — normalizamos
        $aliados = is_array($claims['aliados'])
            ? $claims['aliados']
            : (array) $claims['aliados'];

        $this->assertContains('ALLY-A', $aliados);
        $this->assertContains('ALLY-B', $aliados);
    }

    public function testThrowsOnInvalidToken(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Token inválido/');

        $this->service()->validate('not.a.valid.token');
    }

    public function testThrowsOnTokenSignedWithDifferentSecret(): void
    {
        $other = new JwtTokenService('completely-different-secret-key!!');
        $token = $other->generate(['sub' => 1]);

        $this->expectException(RuntimeException::class);

        $this->service()->validate($token);
    }
}
