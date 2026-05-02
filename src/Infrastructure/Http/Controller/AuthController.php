<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\LoginDto;
use ClinicalLab\Application\Dto\RegisterUserDto;
use ClinicalLab\Application\UseCase\LoginUseCase;
use ClinicalLab\Application\UseCase\RegisterUserUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class AuthController
{
    public function __construct(
        private readonly LoginUseCase        $loginUseCase,
        private readonly RegisterUserUseCase $registerUseCase
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (empty($body['username']) || empty($body['password'])) {
            return $this->json($response, ['error' => 'username y password son requeridos'], 422);
        }

        try {
            $result = $this->loginUseCase->execute(
                new LoginDto($body['username'], $body['password'])
            );
            return $this->json($response, $result);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        }
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['username', 'email', 'password', 'role'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: $field"], 422);
            }
        }

        try {
            $userId = $this->registerUseCase->execute(new RegisterUserDto(
                $body['username'],
                $body['email'],
                $body['password'],
                $body['role'],
                $body['aliados'] ?? []
            ));

            return $this->json($response, ['id' => $userId, 'message' => 'Usuario creado'], 201);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $auth = $request->getAttribute('auth');
        return $this->json($response, [
            'id'       => $auth['sub'],
            'username' => $auth['username'],
            'role'     => $auth['role'],
            'aliados'  => $auth['aliados'] ?? [],
        ]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
