<?php

namespace ClinicalLab\Infrastructure\Http;

use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Domain\Service\ExternalLabClientInterface;
use Firebase\JWT\JWT;
use RuntimeException;

class ExternalLabApiClient implements ExternalLabClientInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $jwtSecret
    ) {
    }

    /**
     * @param LabOrderDetail[] $details
     */
    public function sendOrder(LabOrder $order, array $details): void
    {
        $payload = [
            'order' => [
                'idSolicitudKey' => $order->getIdSolicitudKey(),
                'idAdmision' => $order->getIdAdmision(),
                'idAtencion' => $order->getIdAtencion(),
                'tipoDeDocumento' => $order->getTipoDeDocumento(),
                'identificacion' => $order->getIdentificacion(),
                'nombreDelPaciente' => $order->getNombreDelPaciente(),
                'sexo' => $order->getSexo(),
                'fechaDeNacimiento' => $order->getFechaDeNacimiento()->format(DATE_ATOM),
                'centroDeSalud' => $order->getCentroDeSalud(),
                'fechaDeLaOrden' => $order->getFechaDeLaOrden()->format(DATE_ATOM),
                'medicoQueOrdena' => $order->getMedicoQueOrdena(),
                'numeroDeAutorizacion' => $order->getNumeroDeAutorizacion(),
                'idAliado' => $order->getIdAliado(),
                'porcEjecucion' => $order->getPorcEjecucion(),
                'estadoDeLaOrden' => $order->getEstadoDeLaOrden(),
            ],
            'detalles' => array_map(fn (LabOrderDetail $detail) => [
                'idSolicitudKey' => $detail->getIdSolicitudKey(),
                'idAdmision' => $detail->getIdAdmision(),
                'cups' => $detail->getCups(),
                'nombreDelLaboratorio' => $detail->getNombreDelLaboratorio(),
                'fechaTomaMuestra' => $detail->getFechaTomaMuestra()?->format(DATE_ATOM),
                'metodo' => $detail->getMetodo(),
                'reactivo' => $detail->getReactivo(),
                'invima' => $detail->getInvima(),
                'estadoDelResultado' => $detail->getEstadoDelResultado(),
                'fechaResultado' => $detail->getFechaResultado()?->format(DATE_ATOM),
                'tipoIdentificacionDelBacteriologo' => $detail->getTipoIdentificacionDelBacteriologo(),
                'identificacionDelBacteriologo' => $detail->getIdentificacionDelBacteriologo(),
            ], $details),
        ];

        $jwt = JWT::encode([
            'iss' => 'api-clinical-lab',
            'iat' => time(),
            'exp' => time() + 300,
        ], $this->jwtSecret, 'HS256');

        $headers = [
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->apiKey,
            'Authorization: Bearer ' . $jwt,
        ];

        $ch = curl_init($this->baseUrl . '/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $httpCode >= 400) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Fallo al enviar orden: ' . ($error ?: $response));
        }

        curl_close($ch);
    }
}
