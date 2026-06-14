<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\LoginDto;
use ClinicalLab\Application\Dto\RegisterUserDto;
use ClinicalLab\Application\UseCase\LoginUseCase;
use ClinicalLab\Application\UseCase\PasswordResetUseCase;
use ClinicalLab\Application\UseCase\RegisterUserUseCase;
use ClinicalLab\Domain\Service\PasswordPolicyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class AuthController
{
    public function __construct(
        private readonly LoginUseCase         $loginUseCase,
        private readonly RegisterUserUseCase  $registerUseCase,
        private readonly PasswordResetUseCase $passwordResetUseCase,
        private readonly PasswordPolicyService $passwordPolicy,
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
            $result['user']['permissions'] = $this->permissionsForRole($result['user']['role']);
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
                $body['aliados']        ?? [],
                array_map('intval', $body['health_centers'] ?? [])
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
        $role = $auth['role'] ?? '';

        return $this->json($response, [
            'id'             => $auth['sub'],
            'username'       => $auth['username'],
            'role'           => $role,
            'aliados'        => $auth['aliados']        ?? [],
            'health_centers' => $auth['health_centers'] ?? [],
            'permissions'    => $this->permissionsForRole($role),
        ]);
    }

    /**
     * POST /auth/password-reset/request
     * Body: { "email": "usuario@ejemplo.com" }
     *
     * Siempre responde 200 para no revelar si el email existe.
     */
    public function requestPasswordReset(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface {
        $body  = $request->getParsedBody();
        $email = trim($body['email'] ?? '');

        if ($email === '') {
            return $this->json($response, ['error' => 'El campo email es requerido'], 422);
        }

        try {
            $this->passwordResetUseCase->requestReset($email);
        } catch (Throwable) {
            // Silenciar cualquier error interno para no revelar información
        }

        return $this->json($response, [
            'message' => 'Si el correo está registrado, recibirás un enlace de recuperación en los próximos minutos.',
        ]);
    }

    /**
     * POST /auth/password-reset/confirm
     * Body: { "token": "...", "password": "NuevaContraseña1!" }
     */
    public function confirmPasswordReset(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface {
        $body     = $request->getParsedBody();
        $token    = trim($body['token']    ?? '');
        $password = $body['password'] ?? '';

        if ($token === '' || $password === '') {
            return $this->json($response, ['error' => 'Los campos token y password son requeridos'], 422);
        }

        try {
            $this->passwordResetUseCase->confirmReset($token, $password);
            return $this->json($response, ['message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.']);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        }
    }

    /**
     * GET /auth/password-policy
     * Devuelve la descripción de la política de contraseñas (pública).
     */
    public function passwordPolicy(
        ServerRequestInterface $request,
        ResponseInterface      $response
    ): ResponseInterface {
        return $this->json($response, [
            'policy'           => $this->passwordPolicy->description(),
            'minLength'        => 8,
            'requireUppercase' => true,
            'requireLowercase' => true,
            'requireDigit'     => true,
            'requireSpecial'   => true,
            'allowedSpecial'   => PasswordPolicyService::ALLOWED_SPECIAL,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function permissionsForRole(string $role): array
    {
        return [
            // Usuarios
            'canRegisterUsers'       => $role === 'admin',

            // Aliados
            'canEditAliado'          => $role === 'admin',
            'canUploadAliadoLogo'    => $role === 'admin',

            // Bacteriólogos
            'canCreateBacteriologo'  => in_array($role, ['admin', 'lab_operator'], true),
            'canEditBacteriologo'    => in_array($role, ['admin', 'lab_operator'], true),
            'canDeleteBacteriologo'  => in_array($role, ['admin', 'lab_operator'], true),
            'canUploadFirma'         => in_array($role, ['admin', 'lab_operator'], true),

            // Centros de salud
            'canCreateHealthCenter'  => $role === 'admin',
            'canEditHealthCenter'    => $role === 'admin',

            // Pacientes
            'canViewPatients'        => in_array($role, ['admin', 'lab_operator'], true),
            'canCreatePatient'       => in_array($role, ['admin', 'lab_operator'], true),
            'canEditPatient'         => in_array($role, ['admin', 'lab_operator'], true),

            // Órdenes
            'canCreateOrder'         => in_array($role, ['admin', 'lab_operator'], true),
            'canSendOrder'           => in_array($role, ['admin', 'lab_operator'], true),
            'canMarkOrdersSent'      => in_array($role, ['admin', 'lab_operator'], true),

            // Resultados
            'canStoreResult'         => in_array($role, ['admin', 'lab_operator', 'aliado_operator'], true),
            'canAttachPdf'           => in_array($role, ['admin', 'lab_operator', 'aliado_operator'], true),
            'canSendResultEmail'     => in_array($role, ['admin', 'lab_operator', 'aliado_operator'], true),

            // Catálogo de exámenes
            'canEditExamCatalog'     => $role === 'admin',

            // Médico: solo visualización de órdenes de su centro de salud
            'canViewOrders'          => in_array($role, ['admin', 'lab_operator', 'aliado_operator', 'viewer', 'medico'], true),
        ];
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
