<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\OrderFilterDto;
use ClinicalLab\Application\UseCase\GenerateResultPdfUseCase;
use ClinicalLab\Application\UseCase\PatientPortalUseCase;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Infrastructure\Pdf\ResultPdfGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class PatientPortalController
{
    public function __construct(
        private readonly PatientPortalUseCase        $portalUseCase,
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly GenerateResultPdfUseCase    $generatePdfUseCase,
        private readonly ResultPdfGenerator          $pdfGenerator,
    ) {
    }

    // ── Paso 1: Solicitar código OTP ──────────────────────────────────────────

    /**
     * POST /patient-portal/request-access
     * Body: { "tipoDocumento": "CC", "identificacion": "1020304050" }
     */
    public function requestAccess(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        $tipoDocumento  = trim((string) ($body['tipoDocumento']  ?? ''));
        $identificacion = trim((string) ($body['identificacion'] ?? ''));

        if ($tipoDocumento === '' || $identificacion === '') {
            return $this->json($response, ['error' => 'Los campos tipoDocumento e identificacion son requeridos'], 422);
        }

        try {
            $this->portalUseCase->requestAccess($tipoDocumento, $identificacion);
        } catch (RuntimeException $e) {
            // Respuesta genérica para no revelar si el paciente existe
            return $this->json($response, ['message' => $e->getMessage()]);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => 'Error interno al procesar la solicitud'], 500);
        }

        return $this->json($response, [
            'message' => 'Si el documento está registrado, recibirás un código en tu correo.',
        ]);
    }

    // ── Paso 2: Verificar OTP y obtener JWT ───────────────────────────────────

    /**
     * POST /patient-portal/verify
     * Body: { "tipoDocumento": "CC", "identificacion": "1020304050", "codigo": "847291" }
     */
    public function verify(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        $tipoDocumento  = trim((string) ($body['tipoDocumento']  ?? ''));
        $identificacion = trim((string) ($body['identificacion'] ?? ''));
        $codigo         = trim((string) ($body['codigo']         ?? ''));

        if ($tipoDocumento === '' || $identificacion === '' || $codigo === '') {
            return $this->json($response, ['error' => 'Los campos tipoDocumento, identificacion y codigo son requeridos'], 422);
        }

        try {
            $result = $this->portalUseCase->verify($tipoDocumento, $identificacion, $codigo);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 401);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => 'Error interno al verificar el código'], 500);
        }

        return $this->json($response, $result);
    }

    // ── Paso 3: Ver resultados del paciente autenticado ───────────────────────

    /**
     * GET /patient-portal/results
     * Requiere: Authorization: Bearer <patient-jwt>
     */
    public function results(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $patient   = $request->getAttribute('patient');
        $patientId = (int) ($patient['sub'] ?? 0);

        if ($patientId === 0) {
            return $this->json($response, ['error' => 'Token de paciente inválido'], 401);
        }

        // Buscar por patient_id (órdenes nuevas) y también por identificacion (órdenes legacy sin patient_id)
        $byPatientId = $this->orderRepository->findByFilter(new OrderFilterDto(
            aliadoIds: null,
            estado:    'completed',
            patientId: $patientId,
            limit:     200,
        ));

        $byDoc = $this->orderRepository->findByIdentificacion(
            $patient['identificacion'] ?? '',
            'completed'
        );

        // Unir y deduplicar por idSolicitudKey
        $merged = [];
        foreach (array_merge($byPatientId, $byDoc) as $order) {
            $merged[$order->getIdSolicitudKey()] = $order;
        }

        $data = array_map(fn($o) => [
            'idSolicitudKey'  => $o->getIdSolicitudKey(),
            'fechaDeLaOrden'  => $o->getFechaDeLaOrden()->format('Y-m-d H:i:s'),
            'estadoDeLaOrden' => $o->getEstadoDeLaOrden(),
            'centroDeSalud'   => $o->getCentroDeSalud(),
            'medicoQueOrdena' => $o->getMedicoQueOrdena(),
        ], array_values($merged));

        return $this->json($response, [
            'patient' => [
                'nombre'         => $patient['nombre']         ?? '',
                'tipoDocumento'  => $patient['tipoDocumento']  ?? '',
                'identificacion' => $patient['identificacion'] ?? '',
            ],
            'ordenes' => $data,
            'total'   => count($data),
        ]);
    }

    // ── Paso 4: Descargar PDF de una orden ────────────────────────────────────

    /**
     * GET /patient-portal/results/{idSolicitudKey}/pdf
     * Requiere: Authorization: Bearer <patient-jwt>
     */
    public function downloadPdf(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $patient        = $request->getAttribute('patient');
        $patientId      = (int) ($patient['sub'] ?? 0);
        $idSolicitudKey = $args['idSolicitudKey'] ?? '';

        if ($patientId === 0) {
            return $this->json($response, ['error' => 'Token de paciente inválido'], 401);
        }

        // Verificar que la orden pertenece al paciente autenticado
        $order = $this->orderRepository->findByIdSolicitudKey($idSolicitudKey);

        if (!$order) {
            return $this->json($response, ['error' => 'Orden no encontrada'], 404);
        }

        // Verificar pertenencia: por patient_id (nuevo) o por identificacion (legacy)
        $perteneceAlPaciente = $order->getPatientId() === $patientId
            || $order->getIdentificacion() === ($patient['identificacion'] ?? '');

        if (!$perteneceAlPaciente) {
            return $this->json($response, ['error' => 'No tienes acceso a esta orden'], 403);
        }

        if ($order->getEstadoDeLaOrden() !== 'completed') {
            return $this->json($response, ['error' => 'Los resultados de esta orden aún no están disponibles'], 422);
        }

        try {
            $relativePath = $this->generatePdfUseCase->execute($idSolicitudKey);
            $fullPath     = $this->pdfGenerator->getFullPath($relativePath);

            if (!file_exists($fullPath)) {
                return $this->json($response, ['error' => 'No se pudo generar el PDF'], 500);
            }

            $pdfContent = file_get_contents($fullPath);
            $filename   = 'resultado_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $idSolicitudKey) . '.pdf';

            $response->getBody()->write($pdfContent);

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->withHeader('Content-Length', (string) strlen($pdfContent));

        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrada') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
