<?php

declare(strict_types=1);

use ClinicalLab\Application\UseCase\BulkMarkOrdersSentUseCase;
use ClinicalLab\Application\UseCase\CreateLabOrderUseCase;
use ClinicalLab\Application\UseCase\ExamCatalogUseCase;
use ClinicalLab\Application\UseCase\ExamParameterRangeUseCase;
use ClinicalLab\Application\UseCase\GenerateResultPdfUseCase;
use ClinicalLab\Application\UseCase\GetPendingOrdersByAliadoUseCase;
use ClinicalLab\Application\UseCase\HealthCenterUseCase;
use ClinicalLab\Application\UseCase\LoginUseCase;
use ClinicalLab\Application\UseCase\PasswordResetUseCase;
use ClinicalLab\Application\UseCase\PatientPortalUseCase;
use ClinicalLab\Application\UseCase\PatientUseCase;
use ClinicalLab\Application\UseCase\RegisterUserUseCase;
use ClinicalLab\Application\UseCase\SendLabOrderUseCase;
use ClinicalLab\Application\UseCase\SendResultEmailUseCase;
use ClinicalLab\Application\UseCase\ValidateAndStoreResultUseCase;
use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Service\PasswordPolicyService;
use ClinicalLab\Infrastructure\Auth\JwtTokenService;
use ClinicalLab\Infrastructure\Http\Controller\AliadoController;
use ClinicalLab\Infrastructure\Http\Controller\AliadoOrderController;
use ClinicalLab\Infrastructure\Http\Controller\AuthController;
use ClinicalLab\Infrastructure\Http\Controller\BacteriologoController;
use ClinicalLab\Infrastructure\Http\Controller\ExamCatalogController;
use ClinicalLab\Infrastructure\Http\Controller\ExamParameterRangeController;
use ClinicalLab\Infrastructure\Http\Controller\HealthCenterController;
use ClinicalLab\Infrastructure\Http\Controller\OrderController;
use ClinicalLab\Infrastructure\Http\Controller\PatientController;
use ClinicalLab\Infrastructure\Http\Controller\PatientPortalController;
use ClinicalLab\Infrastructure\Http\Controller\MedicoController;
use ClinicalLab\Infrastructure\Http\Controller\ResultController;
use ClinicalLab\Infrastructure\Http\Controller\ResultReportController;
use ClinicalLab\Infrastructure\Mail\ResultMailer;
use ClinicalLab\Infrastructure\Pdf\ResultPdfGenerator;
use ClinicalLab\Infrastructure\Http\ExternalLabApiClient;
use ClinicalLab\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use ClinicalLab\Infrastructure\Http\Middleware\PatientJwtMiddleware;
use ClinicalLab\Infrastructure\Http\Middleware\RequireRoleMiddleware;
use ClinicalLab\Infrastructure\Persistence\MySqlAliadoRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlBacteriologoRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlExamParameterRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlExamParameterRangeRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlExamTypeRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlHealthCenterRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderDetailRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabOrderRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabResultRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlLabResultValueRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlPatientAccessTokenRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlPatientRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlUserRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlMedicoRepository;
use ClinicalLab\Infrastructure\Persistence\MySqlAntibiogramaRepository;
use ClinicalLab\Infrastructure\Persistence\PdoConnectionFactory;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// ── Variables de entorno ──────────────────────────────────────────────────────
$envFile = __DIR__ . '/../config/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Quitar comillas envolventes si las hay
        if (strlen($value) >= 2 &&
            (($value[0] === '"' && $value[-1] === '"') ||
             ($value[0] === "'" && $value[-1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        // Solo setear si NO está ya definida en el entorno del sistema (Docker la inyecta)
        if (getenv($key) === false) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// ── Dependencias ──────────────────────────────────────────────────────────────
$pdo = PdoConnectionFactory::fromEnv();

$userRepository        = new MySqlUserRepository($pdo);
$aliadoRepository      = new MySqlAliadoRepository($pdo);
$orderRepository       = new MySqlLabOrderRepository($pdo);
$detailRepository      = new MySqlLabOrderDetailRepository($pdo);
$resultRepository      = new MySqlLabResultRepository($pdo);
$examTypeRepository    = new MySqlExamTypeRepository($pdo);
$parameterRepository   = new MySqlExamParameterRepository($pdo);
$paramRangeRepository  = new MySqlExamParameterRangeRepository($pdo);
$resultValueRepository = new MySqlLabResultValueRepository($pdo);
$healthCenterRepository = new MySqlHealthCenterRepository($pdo);
$patientRepository      = new MySqlPatientRepository($pdo);
$bacteriologoRepository = new MySqlBacteriologoRepository($pdo);
$medicoRepository       = new MySqlMedicoRepository($pdo);
$antibiogramaRepository = new MySqlAntibiogramaRepository($pdo);

$tokenService = new JwtTokenService(getenv('JWT_SECRET') ?: getenv('EXTERNAL_LAB_JWT_SECRET'));
$externalClient = new ExternalLabApiClient(
    getenv('EXTERNAL_LAB_BASE_URL'),
    getenv('EXTERNAL_LAB_API_KEY'),
    getenv('EXTERNAL_LAB_JWT_SECRET')
);

// ── Controladores ─────────────────────────────────────────────────────────────
$passwordPolicy = new PasswordPolicyService();

// ── PDF y Email ───────────────────────────────────────────────────────────────
$pdfGenerator = new ResultPdfGenerator();

$generatePdfUseCase = new GenerateResultPdfUseCase(
    $orderRepository,
    $resultRepository,
    $resultValueRepository,
    $parameterRepository,
    $patientRepository,
    $aliadoRepository,
    $bacteriologoRepository,
    $pdfGenerator,
);

$mailer = new ResultMailer(
    getenv('MAIL_HOST')      ?: 'smtp.gmail.com',
    (int) (getenv('MAIL_PORT') ?: 587),
    getenv('MAIL_USERNAME')  ?: '',
    getenv('MAIL_PASSWORD')  ?: '',
    getenv('MAIL_FROM_NAME') ?: 'Laboratorio Clínico',
);

$authController = new AuthController(
    new LoginUseCase($userRepository, $tokenService),
    new RegisterUserUseCase($userRepository, $aliadoRepository, $passwordPolicy),
    new PasswordResetUseCase(
        $userRepository,
        $mailer,
        $passwordPolicy,
        getenv('MAIL_FROM_NAME') ?: 'Laboratorio Clínico',
    ),
    $passwordPolicy,
);

$orderController = new OrderController(
    new CreateLabOrderUseCase(
        $orderRepository,
        $detailRepository,
        $patientRepository,
        $healthCenterRepository,
        $medicoRepository,
    ),
    new SendLabOrderUseCase($orderRepository, $externalClient),
    $orderRepository
);

$medicoController = new MedicoController(
    $medicoRepository,
    $userRepository,
);

$healthCenterController = new HealthCenterController(
    new HealthCenterUseCase($healthCenterRepository, $aliadoRepository)
);

$patientController = new PatientController(
    new PatientUseCase($patientRepository),
    $orderRepository,
    $patientRepository,
);

$resultController = new ResultController(
    new ValidateAndStoreResultUseCase(
        $orderRepository,
        $resultRepository,
        $parameterRepository,
        $resultValueRepository,
        $paramRangeRepository,
        $antibiogramaRepository,
    ),
    $resultRepository,
    $resultValueRepository,
    $parameterRepository,
    $bacteriologoRepository,
    $antibiogramaRepository,
);

$bacteriologoController = new BacteriologoController(
    $bacteriologoRepository,
    $aliadoRepository,
);

$examCatalogController = new ExamCatalogController(
    new ExamCatalogUseCase($examTypeRepository, $parameterRepository)
);

$examParamRangeController = new ExamParameterRangeController(
    new ExamParameterRangeUseCase($paramRangeRepository, $parameterRepository)
);

$aliadoOrderController = new AliadoOrderController(
    new GetPendingOrdersByAliadoUseCase($orderRepository, $aliadoRepository),
    new BulkMarkOrdersSentUseCase($orderRepository, $aliadoRepository)
);

$aliadoController = new AliadoController($aliadoRepository);

$resultReportController = new ResultReportController(
    $generatePdfUseCase,
    new SendResultEmailUseCase(
        $orderRepository,
        $patientRepository,
        $generatePdfUseCase,
        $mailer,
        $pdfGenerator,
        $pdo,
    ),
    $pdfGenerator,
    $resultRepository,
);

// ── Portal de pacientes ───────────────────────────────────────────────────────
$patientAccessTokenRepository = new MySqlPatientAccessTokenRepository($pdo);

$patientPortalUseCase = new PatientPortalUseCase(
    $patientRepository,
    $patientAccessTokenRepository,
    $mailer,
    getenv('JWT_SECRET') ?: getenv('EXTERNAL_LAB_JWT_SECRET'),
);

$patientPortalController = new PatientPortalController(
    $patientPortalUseCase,
    $orderRepository,
    $generatePdfUseCase,
    $pdfGenerator,
);

// ── Middlewares ───────────────────────────────────────────────────────────────
$jwtMiddleware        = new JwtAuthMiddleware($tokenService);
$patientJwtMiddleware = new PatientJwtMiddleware($patientPortalUseCase);
$adminOnly     = new RequireRoleMiddleware([Role::ADMIN]);
$labOrAdmin    = new RequireRoleMiddleware([Role::ADMIN, Role::LAB_OPERATOR]);
$aliadoOrAbove = new RequireRoleMiddleware([Role::ADMIN, Role::LAB_OPERATOR, Role::ALIADO_OPERATOR]);
$allRoles      = new RequireRoleMiddleware([Role::ADMIN, Role::LAB_OPERATOR, Role::ALIADO_OPERATOR, Role::VIEWER]);

// ── App ───────────────────────────────────────────────────────────────────────
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: (bool) getenv('APP_DEBUG'),
    logErrors: true,
    logErrorDetails: true
);

// ── Rutas públicas ────────────────────────────────────────────────────────────
$app->post('/auth/login',                        [$authController, 'login']);
$app->get('/auth/password-policy',               [$authController, 'passwordPolicy']);
$app->post('/auth/password-reset/request',       [$authController, 'requestPasswordReset']);
$app->post('/auth/password-reset/confirm',       [$authController, 'confirmPasswordReset']);

// ── Storage — servir archivos estáticos (firmas, logos, PDFs) ────────────────
$app->get('/storage/{type}/{filename}', function (
    \Psr\Http\Message\ServerRequestInterface $request,
    \Psr\Http\Message\ResponseInterface $response,
    array $args
) {
    $allowedTypes = ['firmas', 'logos', 'pdfs'];
    $type         = $args['type'];
    $filename     = basename($args['filename']); // evita path traversal

    if (!in_array($type, $allowedTypes, true)) {
        $response->getBody()->write(json_encode(['error' => 'Tipo no permitido']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $fullPath = __DIR__ . '/../storage/' . $type . '/' . $filename;

    if (!file_exists($fullPath)) {
        $response->getBody()->write(json_encode(['error' => 'Archivo no encontrado']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'png'  => 'image/png',
        'jpg', 'jpeg' => 'image/jpeg',
        'pdf'  => 'application/pdf',
        default => 'application/octet-stream',
    };

    $response->getBody()->write(file_get_contents($fullPath));
    return $response
        ->withStatus(200)
        ->withHeader('Content-Type', $mime)
        ->withHeader('Content-Length', (string) filesize($fullPath))
        ->withHeader('Cache-Control', 'private, max-age=3600');
});

// ── Portal de pacientes — rutas públicas (OTP) ────────────────────────────────
$app->post('/patient-portal/request-access', [$patientPortalController, 'requestAccess']);
$app->post('/patient-portal/verify',         [$patientPortalController, 'verify']);

// ── Portal de pacientes — rutas protegidas con JWT de paciente ────────────────
$app->group('/patient-portal', function ($group) use ($patientPortalController) {
    $group->get('/results',                              [$patientPortalController, 'results']);
    $group->get('/results/{idSolicitudKey}/pdf',         [$patientPortalController, 'downloadPdf']);
})->add($patientJwtMiddleware);

// ── Rutas protegidas con JWT ──────────────────────────────────────────────────
$app->group('', function ($group) use (
    $authController,
    $orderController,
    $resultController,
    $resultReportController,
    $examCatalogController,
    $examParamRangeController,
    $aliadoOrderController,
    $aliadoController,
    $bacteriologoController,
    $medicoController,
    $healthCenterController,
    $patientController,
    $adminOnly,
    $labOrAdmin,
    $aliadoOrAbove,
    $allRoles
) {
    // Auth
    $group->get('/auth/me', [$authController, 'me']);
    $group->post('/auth/register', [$authController, 'register'])->add($adminOnly);

    // Aliados
    $group->get('/aliados',       [$aliadoController, 'list'])  ->add($allRoles);
    $group->post('/aliados',      [$aliadoController, 'create'])->add($adminOnly);
    $group->get('/aliados/{id}',  [$aliadoController, 'show'])  ->add($allRoles);
    $group->put('/aliados/{id}',  [$aliadoController, 'update'])->add($adminOnly);
    $group->post('/aliados/{id}/logo', [$aliadoController, 'uploadLogo'])->add($adminOnly);

    // Bacteriólogos
    $group->get('/aliados/{aliadoId}/bacteriologos', [$bacteriologoController, 'listByAliado'])->add($allRoles);
    $group->post('/aliados/{aliadoId}/bacteriologos', [$bacteriologoController, 'create'])->add($labOrAdmin);
    $group->get('/bacteriologos/{id}', [$bacteriologoController, 'show'])->add($allRoles);
    $group->put('/bacteriologos/{id}', [$bacteriologoController, 'update'])->add($labOrAdmin);

    // Médicos
    $group->get('/medicos',       [$medicoController, 'list'])      ->add($allRoles);
    $group->post('/medicos',      [$medicoController, 'create'])    ->add($labOrAdmin);
    $group->get('/medicos/{id}',  [$medicoController, 'show'])      ->add($allRoles);
    $group->put('/medicos/{id}',  [$medicoController, 'update'])    ->add($labOrAdmin);
    $group->delete('/medicos/{id}', [$medicoController, 'deactivate'])->add($labOrAdmin);
    $group->delete('/bacteriologos/{id}', [$bacteriologoController, 'deactivate'])->add($labOrAdmin);
    $group->post('/bacteriologos/{id}/firma', [$bacteriologoController, 'uploadFirma'])->add($labOrAdmin);

    // Órdenes
    $group->get('/orders', [$orderController, 'list'])->add($allRoles);
    $group->post('/orders', [$orderController, 'create'])->add($labOrAdmin);
    $group->get('/orders/{id}', [$orderController, 'show'])->add($allRoles);
    $group->post('/orders/{id}/send', [$orderController, 'send'])->add($labOrAdmin);

    // Resultados
    $group->post('/results', [$resultController, 'store'])->add($aliadoOrAbove);
    $group->get('/orders/{id}/results', [$resultController, 'getStructured'])->add($allRoles);
    $group->get('/orders/{id}/results/pdf', [$resultReportController, 'downloadPdf'])->add($allRoles);
    $group->post('/orders/{id}/results/attach-pdf', [$resultReportController, 'attachPdf'])->add($aliadoOrAbove);
    $group->post('/orders/{id}/results/send-email', [$resultReportController, 'sendEmail'])->add($aliadoOrAbove);

    // Centros de salud
    $group->get('/health-centers', [$healthCenterController, 'list'])->add($allRoles);
    $group->post('/health-centers', [$healthCenterController, 'create'])->add($adminOnly);
    $group->put('/health-centers/{id}', [$healthCenterController, 'update'])->add($adminOnly);
    $group->post('/health-centers/{id}/aliados/{aliadoId}', [$healthCenterController, 'associateAliado'])->add($adminOnly);
    $group->delete('/health-centers/{id}/aliados/{aliadoId}', [$healthCenterController, 'dissociateAliado'])->add($adminOnly);

    // Pacientes
    $group->get('/patients',      [$patientController, 'list'])  ->add($labOrAdmin);
    $group->post('/patients',     [$patientController, 'create'])->add($labOrAdmin);
    $group->get('/patients/{id}', [$patientController, 'show'])  ->add($labOrAdmin);
    $group->put('/patients/{id}', [$patientController, 'update'])->add($labOrAdmin);

    // Servicios por aliado
    $group->get('/aliados/{aliadoId}/orders/pending', [$aliadoOrderController, 'listPending'])
          ->add($aliadoOrAbove);
    $group->post('/aliados/{aliadoId}/orders/mark-sent', [$aliadoOrderController, 'markSent'])
          ->add($labOrAdmin);

    // Catálogo de exámenes
    $group->get('/exam-types', [$examCatalogController, 'listTypes'])->add($allRoles);
    $group->post('/exam-types', [$examCatalogController, 'createType'])->add($adminOnly);
    $group->put('/exam-types/{cups}', [$examCatalogController, 'updateType'])->add($adminOnly);
    $group->get('/exam-types/{cups}/parameters', [$examCatalogController, 'listParameters'])->add($allRoles);
    $group->post('/exam-types/{cups}/parameters', [$examCatalogController, 'addParameter'])->add($adminOnly);
    $group->put('/exam-types/{cups}/parameters/{id}', [$examCatalogController, 'updateParameter'])->add($adminOnly);
    $group->delete('/exam-types/{cups}/parameters/{id}', [$examCatalogController, 'deactivateParameter'])->add($adminOnly);

    // Rangos por reactivo
    $group->get('/exam-types/{cups}/parameters/{parameterId}/ranges', [$examParamRangeController, 'list'])->add($allRoles);
    $group->post('/exam-types/{cups}/parameters/{parameterId}/ranges', [$examParamRangeController, 'add'])->add($adminOnly);
    $group->put('/exam-types/{cups}/parameters/{parameterId}/ranges/{rangeId}', [$examParamRangeController, 'update'])->add($adminOnly);
    $group->delete('/exam-types/{cups}/parameters/{parameterId}/ranges/{rangeId}', [$examParamRangeController, 'deactivate'])->add($adminOnly);

})->add($jwtMiddleware);

$app->run();
