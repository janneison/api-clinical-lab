<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\UseCase\GenerateResultPdfUseCase;
use ClinicalLab\Application\UseCase\SendResultEmailUseCase;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use ClinicalLab\Infrastructure\Pdf\ResultPdfGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class ResultReportController
{
    private const PDF_DIR        = __DIR__ . '/../../../../storage/pdfs/';
    private const PDF_URL_PREFIX = '/storage/pdfs/';
    private const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

    public function __construct(
        private readonly GenerateResultPdfUseCase  $generatePdfUseCase,
        private readonly SendResultEmailUseCase    $sendEmailUseCase,
        private readonly ResultPdfGenerator        $pdfGenerator,
        private readonly LabResultRepositoryInterface $resultRepository,
    ) {
    }

    /**
     * GET /orders/{id}/results/pdf
     *
     * Devuelve el PDF del informe. Prioridad:
     *   1. PDF adjunto subido manualmente
     *   2. PDF generado automáticamente
     *
     * Query param: ?regenerate=1  → fuerza regeneración aunque exista adjunto
     */
    public function downloadPdf(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $forceRegenerate = ($request->getQueryParams()['regenerate'] ?? '0') === '1';

        try {
            $relativePath = $this->generatePdfUseCase->execute($args['id'], $forceRegenerate);
            $fullPath     = $this->pdfGenerator->getFullPath($relativePath);

            if (!file_exists($fullPath)) {
                return $this->json($response, ['error' => 'No se pudo obtener el PDF'], 500);
            }

            $pdfContent = file_get_contents($fullPath);
            $filename   = basename($fullPath);

            $response->getBody()->write($pdfContent);

            return $response
                ->withStatus(200)
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->withHeader('Content-Length', (string) strlen($pdfContent));

        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrada') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            // Captura cualquier error incluyendo fatales de dompdf
            return $this->json($response, [
                'error'   => $e->getMessage(),
                'type'    => get_class($e),
                'file'    => basename($e->getFile()),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * POST /orders/{id}/results/attach-pdf
     *
     * Adjunta un PDF externo como el informe oficial de la orden.
     * Una vez adjuntado, se usa en lugar del PDF generado automáticamente.
     *
     * Content-Type: multipart/form-data
     * Campo: pdf (archivo PDF, máx 20 MB)
     */
    public function attachPdf(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $idSolicitudKey = $args['id'];

        // Verificar que la orden tiene resultados
        $results = $this->resultRepository->findAllByOrder($idSolicitudKey);
        if (empty($results)) {
            return $this->json($response, ['error' => 'La orden no tiene resultados registrados'], 422);
        }

        $files = $request->getUploadedFiles();

        if (empty($files['pdf'])) {
            return $this->json($response, ['error' => 'Se requiere el campo "pdf" como archivo multipart'], 422);
        }

        $file = $files['pdf'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, ['error' => 'Error al subir el archivo: código ' . $file->getError()], 422);
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return $this->json($response, ['error' => 'El archivo supera el tamaño máximo de 20 MB'], 422);
        }

        $mime = $file->getClientMediaType();
        if ($mime !== 'application/pdf') {
            return $this->json($response, ['error' => 'Solo se aceptan archivos PDF'], 422);
        }

        if (!is_dir(self::PDF_DIR)) {
            mkdir(self::PDF_DIR, 0755, true);
        }

        $filename    = 'resultado_' . preg_replace('/[^a-zA-Z0-9\-]/', '_', $idSolicitudKey) . '_adjunto.pdf';
        $fullPath    = self::PDF_DIR . $filename;
        $relativePath = self::PDF_URL_PREFIX . $filename;

        $file->moveTo($fullPath);

        // Guardar la ruta en lab_results
        $this->resultRepository->updateAttachmentPath($idSolicitudKey, $relativePath);

        return $this->json($response, [
            'message'         => 'PDF adjuntado correctamente. Se usará como informe oficial.',
            'idSolicitudKey'  => $idSolicitudKey,
            'attachmentPath'  => $relativePath,
        ]);
    }

    /**
     * POST /orders/{id}/results/send-email
     *
     * Genera el PDF (o usa el adjunto) y lo envía por correo.
     * Body (opcional): { "email": "otro@correo.com", "mensaje": "..." }
     */
    public function sendEmail(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $body    = $request->getParsedBody();
        $email   = $body['email']   ?? null;
        $mensaje = $body['mensaje'] ?? null;

        try {
            $result = $this->sendEmailUseCase->execute($args['id'], $email, $mensaje);
            return $this->json($response, array_merge($result, ['message' => 'Correo enviado correctamente']));
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrada') ? 404 : 422;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
