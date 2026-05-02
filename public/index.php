<?php

declare(strict_types=1);

use ClinicalLab\Application\UseCase\CreateLabOrderUseCase;
use ClinicalLab\Application\UseCase\LoginUseCase;
use ClinicalLab\Application\UseCase\RegisterUserUseCase;
use ClinicalLab\Application\UseCase\SendLabOrderUseCase;
use ClinicalLab\Application\UseCase\ValidateAndStoreResultUseCase;
use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Infrastructure\Auth\JwtTokenService;
use ClinicalLab\Infrastructure\Http\Controller\AuthController;
use ClinicalLab\Infrastructure\Http\Controller\OrderController;
use ClinicalLab\Infrastructure\Http\Controller\ResultController;
use ClinicalLab\Infrastructure\Http\ExternalLabApiClient;
use ClinicalLab\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use ClinicalLab\Infrastructure\Http\Middleware\RequireRoleMiddleware;
use ClinicalLab\Infrastructure\Persistence\MySqlAliadoRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderDetailRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabResultRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlUserRepository;
use ClinicalLab\Infrastructure\Persistence\PdoConnectionFactory;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

// ── Variables de entorno ──────────────────────────────────────────────────────
$envFile = __DIR__ . '/../config/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!str_starts_with(trim($line), '#') && str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// ── Dependencias ──────────────────────────────────────────────────────────────
$pdo = PdoConnectionFactory::fromEnv();

$userRepository   = new MySqlUserRepository($pdo);
$aliadoRepository = new MySqlAliadoRepository($pdo);
$orderRepository  = new MySqlLabOrderRepository($pdo);
$detailRepository = new MySqlLabOrderDetailRepository($pdo);
$resultRepository = new MySqlLabResultRepository($pdo);

$tokenService = new JwtTokenService(getenv('JWT_SECRET') ?: getenv('EXTERNAL_LAB_JWT_SECRET'));

$externalClient = new ExternalLabApiClient(
    getenv('EXTERNAL_LAB_BASE_URL'),
    getenv('EXTERNAL_LAB_API_KEY'),
    getenv('EXTERNAL_LAB_JWT_SECRET')
);

// ── Controladores ─────────────────────────────────────────────────────────────
$authController = new AuthController(
    new LoginUseCase($userRepository, $tokenService),
    new RegisterUserUseCase($userRepository, $aliadoRepository)
);

$orderController = new OrderController(
    new CreateLabOrderUseCase($orderRepository, $detailRepository),
    new SendLabOrderUseCase($orderRepository, $externalClient),
    $orderRepository
);

$resultController = new ResultController(
    new ValidateAndStoreResultUseCase($orderRepository, $resultRepository)
);

// ── Middlewares ───────────────────────────────────────────────────────────────
$jwtMiddleware   = new JwtAuthMiddleware($tokenService);
$adminOnly       = new RequireRoleMiddleware([Role::ADMIN]);
$labOrAdmin      = new RequireRoleMiddleware([Role::ADMIN, Role::LAB_OPERATOR]);
$aliadoOrAbove   = new RequireRoleMiddleware([Role::ADMIN, Role::LAB_OPERATOR, Role::ALIADO_OPERATOR]);

// ── App ───────────────────────────────────────────────────────────────────────
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: (bool) getenv('APP_DEBUG'),
    logErrors: true,
    logErrorDetails: true
);

// ── Rutas públicas (sin JWT) ──────────────────────────────────────────────────
$app->post('/auth/login', [$authController, 'login']);

// ── Rutas protegidas con JWT ──────────────────────────────────────────────────
$app->group('', function ($group) use ($authController, $orderController, $resultController, $adminOnly, $labOrAdmin, $aliadoOrAbove) {

    // Perfil del usuario autenticado
    $group->get('/auth/me', [$authController, 'me']);

    // Registro de usuarios — solo admin
    $group->post('/auth/register', [$authController, 'register'])
          ->add($adminOnly);

    // Órdenes — lab_operator y admin pueden crear/enviar; aliado_operator puede consultar
    $group->get('/orders', [$orderController, 'list'])
          ->add($aliadoOrAbove);

    $group->post('/orders', [$orderController, 'create'])
          ->add($labOrAdmin);

    $group->get('/orders/{id}', [$orderController, 'show'])
          ->add($aliadoOrAbove);

    $group->post('/orders/{id}/send', [$orderController, 'send'])
          ->add($labOrAdmin);

    // Resultados — aliado_operator puede registrar resultados
    $group->post('/results', [$resultController, 'store'])
          ->add($aliadoOrAbove);

})->add($jwtMiddleware);

$app->run();
