<?php

declare(strict_types=1);

namespace Tests\Unit;

use ClinicalLab\Domain\Service\TokenServiceInterface;
use ClinicalLab\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Response;

class JwtAuthMiddlewareTest extends TestCase
{
    private function makeRequest(string $authHeader = ''): ServerRequestInterface
    {
        $request = (new RequestFactory())->createRequest('GET', '/test');
        if ($authHeader) {
            $request = $request->withHeader('Authorization', $authHeader);
        }
        return $request;
    }

    private function makeHandler(array &$capturedClaims = null): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')
            ->willReturnCallback(function (ServerRequestInterface $req) use (&$capturedClaims) {
                if ($capturedClaims !== null) {
                    $capturedClaims = $req->getAttribute('auth');
                }
                return new Response();
            });
        return $handler;
    }

    public function testAllowsRequestWithValidToken(): void
    {
        $claims = ['sub' => 1, 'role' => 'admin', 'username' => 'jdoe'];

        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->method('validate')->willReturn($claims);

        $capturedClaims = [];
        $handler        = $this->makeHandler($capturedClaims);

        $response = (new JwtAuthMiddleware($tokenService))
            ->process($this->makeRequest('Bearer valid.token'), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($claims, $capturedClaims);
    }

    public function testReturns401WhenAuthHeaderMissing(): void
    {
        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->expects($this->never())->method('validate');

        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = (new JwtAuthMiddleware($tokenService))
            ->process($this->makeRequest(), $handler);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    public function testReturns401WhenTokenIsInvalid(): void
    {
        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->method('validate')
            ->willThrowException(new RuntimeException('Token inválido o expirado'));

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = (new JwtAuthMiddleware($tokenService))
            ->process($this->makeRequest('Bearer bad.token'), $handler);

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('Token inválido', $body['error']);
    }

    public function testReturns401WhenHeaderHasNoBearerPrefix(): void
    {
        $tokenService = $this->createMock(TokenServiceInterface::class);
        $tokenService->expects($this->never())->method('validate');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $response = (new JwtAuthMiddleware($tokenService))
            ->process($this->makeRequest('Basic dXNlcjpwYXNz'), $handler);

        $this->assertSame(401, $response->getStatusCode());
    }
}
