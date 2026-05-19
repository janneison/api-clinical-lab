<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Domain\Entity\Patient;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use ClinicalLab\Domain\Repository\BacteriologoRepositoryInterface;
use ClinicalLab\Domain\Repository\ExamParameterRepositoryInterface;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultRepositoryInterface;
use ClinicalLab\Domain\Repository\LabResultValueRepositoryInterface;
use ClinicalLab\Domain\Repository\PatientRepositoryInterface;
use ClinicalLab\Infrastructure\Pdf\ResultPdfGenerator;
use DateTimeImmutable;
use RuntimeException;

class GenerateResultPdfUseCase
{
    public function __construct(
        private readonly LabOrderRepositoryInterface       $orderRepository,
        private readonly LabResultRepositoryInterface      $resultRepository,
        private readonly LabResultValueRepositoryInterface $resultValueRepository,
        private readonly ExamParameterRepositoryInterface  $parameterRepository,
        private readonly PatientRepositoryInterface        $patientRepository,
        private readonly AliadoRepositoryInterface         $aliadoRepository,
        private readonly BacteriologoRepositoryInterface   $bacteriologoRepository,
        private readonly ResultPdfGenerator                $pdfGenerator,
    ) {
    }

    /**
     * Retorna la ruta relativa del PDF.
     * Prioridad:
     *   1. PDF adjunto subido manualmente (attachment_path en lab_results)
     *   2. PDF generado automáticamente
     */
    public function execute(string $idSolicitudKey, bool $forceRegenerate = false): string
    {
        $order = $this->orderRepository->findByIdSolicitudKey($idSolicitudKey);
        if (!$order) {
            throw new RuntimeException('Orden no encontrada');
        }

        $results = $this->resultRepository->findAllByOrder($idSolicitudKey);
        if (empty($results)) {
            throw new RuntimeException('La orden no tiene resultados registrados');
        }

        // Prioridad 1: PDF adjunto subido manualmente
        if (!$forceRegenerate) {
            $attachmentPath = $this->resultRepository->findAttachmentByOrder($idSolicitudKey);
            if ($attachmentPath) {
                $fullPath = $this->pdfGenerator->getFullPath($attachmentPath);
                if (file_exists($fullPath)) {
                    return $attachmentPath;
                }
            }
        }

        // Prioridad 2: Generar PDF automáticamente
        $patient = $order->getPatientId()
            ? $this->patientRepository->findById($order->getPatientId())
            : null;

        if (!$patient) {
            $patient = $this->patientRepository->findByDocument(
                $order->getTipoDeDocumento(),
                $order->getIdentificacion()
            );
        }

        // Fallback: construir paciente temporal desde datos de texto de la orden
        if (!$patient) {
            $patient = new Patient(
                0,
                $order->getTipoDeDocumento(),
                $order->getIdentificacion(),
                $order->getNombreDelPaciente(),
                $order->getSexo(),
                $order->getFechaDeNacimiento(),
            );
        }

        $aliado = $order->getIdAliado()
            ? $this->aliadoRepository->findById($order->getIdAliado())
            : null;

        $resultados = [];
        foreach ($results as $row) {
            $labResultId = (int) $row['id'];
            $cups        = $row['cups'];

            $parameters = $this->parameterRepository->findByCups($cups);
            $paramMap   = [];
            foreach ($parameters as $p) {
                $paramMap[$p->getId()] = $p;
            }

            $structuredValues     = $this->resultValueRepository->findByLabResultId($labResultId);
            $valoresEstructurados = array_map(fn($v) => [
                'codigo'           => $paramMap[$v->getParameterId()]?->getCodigo() ?? (string) $v->getParameterId(),
                'nombre'           => $paramMap[$v->getParameterId()]?->getNombre() ?? '',
                'tipoResultado'    => $paramMap[$v->getParameterId()]?->getTipoResultado() ?? 'numerico',
                'valorNumerico'    => $v->getValorNumerico(),
                'valorTexto'       => $v->getValorTexto(),
                'valorBooleano'    => $v->getValorBooleano(),
                'reactivo'         => $v->getReactivo(),
                'unidad'           => $paramMap[$v->getParameterId()]?->getUnidad(),
                'valorMinRef'      => $paramMap[$v->getParameterId()]?->getValorMinRef(),
                'valorMaxRef'      => $paramMap[$v->getParameterId()]?->getValorMaxRef(),
                'etiquetaBooleano' => $paramMap[$v->getParameterId()]?->getEtiquetaBooleano(),
                'flag'             => $v->getFlag(),
            ], $structuredValues);

            $resultados[] = [
                'labResultId'          => $labResultId,
                'cups'                 => $cups,
                'bacteriologo'         => $this->resolveBacteriologoData($row['bacteriologo_id'] ?? null),
                'valuesJson'           => json_decode($row['values_json'], true),
                'valoresEstructurados' => $valoresEstructurados,
                'receivedAt'           => $row['received_at'],
            ];
        }

        return $this->pdfGenerator->generate($order, $patient, $aliado, $resultados);
    }

    private function resolveBacteriologoData(mixed $bacteriologoId): ?array
    {
        if (!$bacteriologoId) return null;
        $bact = $this->bacteriologoRepository->findById((int) $bacteriologoId);
        if (!$bact) return null;
        return [
            'id'                 => $bact->getId(),
            'nombre'             => $bact->getNombre(),
            'tipoDocumento'      => $bact->getTipoDocumento(),
            'identificacion'     => $bact->getIdentificacion(),
            'tarjetaProfesional' => $bact->getTarjetaProfesional(),
            'universidad'        => $bact->getUniversidad(),
            'firmaPath'          => $bact->getFirmaPath(),
        ];
    }
}
