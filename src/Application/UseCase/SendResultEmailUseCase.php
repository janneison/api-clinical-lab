<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\PatientRepositoryInterface;
use ClinicalLab\Infrastructure\Mail\ResultMailer;
use ClinicalLab\Infrastructure\Pdf\ResultPdfGenerator;
use PDO;
use RuntimeException;

class SendResultEmailUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly PatientRepositoryInterface  $patientRepository,
        private readonly GenerateResultPdfUseCase    $generatePdfUseCase,
        private readonly ResultMailer                $mailer,
        private readonly ResultPdfGenerator          $pdfGenerator,
        private readonly PDO                         $pdo,
    ) {
    }

    /**
     * @param string      $idSolicitudKey
     * @param string|null $emailOverride   Si se pasa, sobreescribe el email del paciente
     * @param string|null $mensaje         Mensaje personalizado para el cuerpo del correo
     */
    public function execute(
        string  $idSolicitudKey,
        ?string $emailOverride = null,
        ?string $mensaje       = null
    ): array {
        $order = $this->orderRepository->findByIdSolicitudKey($idSolicitudKey);
        if (!$order) {
            throw new RuntimeException('Orden no encontrada');
        }

        // Resolver paciente
        $patient = $order->getPatientId()
            ? $this->patientRepository->findById($order->getPatientId())
            : $this->patientRepository->findByDocument(
                $order->getTipoDeDocumento(),
                $order->getIdentificacion()
            );

        if (!$patient) {
            throw new RuntimeException('No se encontró el paciente asociado a la orden');
        }

        // Resolver email destino
        $emailDestino = $emailOverride ?? $patient->getEmail();
        if (!$emailDestino) {
            throw new RuntimeException(
                'El paciente no tiene email registrado. Proporcione un email en el body.'
            );
        }

        if (!filter_var($emailDestino, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Email inválido: {$emailDestino}");
        }

        // Generar PDF (o reutilizar si ya existe)
        $pdfRelativePath = $this->generatePdfUseCase->execute($idSolicitudKey);
        $pdfFullPath     = $this->pdfGenerator->getFullPath($pdfRelativePath);

        // Enviar correo
        try {
            $this->mailer->send(
                $emailDestino,
                $patient->getNombre(),
                $idSolicitudKey,
                $pdfFullPath,
                $mensaje
            );
            $estado = 'enviado';
            $error  = null;
        } catch (\Throwable $e) {
            $estado = 'error';
            $error  = $e->getMessage();
        }

        // Registrar en log
        $this->pdo->prepare(
            'INSERT INTO result_email_log (id_solicitud_key, email_destino, estado, error_mensaje)
             VALUES (:key, :email, :estado, :error)'
        )->execute([
            'key'    => $idSolicitudKey,
            'email'  => $emailDestino,
            'estado' => $estado,
            'error'  => $error,
        ]);

        if ($estado === 'error') {
            throw new RuntimeException("Error al enviar el correo: {$error}");
        }

        return [
            'idSolicitudKey' => $idSolicitudKey,
            'emailDestino'   => $emailDestino,
            'pdfPath'        => $pdfRelativePath,
            'estado'         => $estado,
        ];
    }
}
