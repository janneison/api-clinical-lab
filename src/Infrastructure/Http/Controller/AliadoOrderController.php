<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\UseCase\BulkMarkOrdersSentUseCase;
use ClinicalLab\Application\UseCase\GetPendingOrdersByAliadoUseCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class AliadoOrderController
{
    public function __construct(
        private readonly GetPendingOrdersByAliadoUseCase $getPendingUseCase,
        private readonly BulkMarkOrdersSentUseCase       $bulkSentUseCase,
    ) {
    }

    /**
     * GET /aliados/{aliadoId}/orders/pending
     *
     * Devuelve todas las órdenes en estado 'pending' para el aliado indicado.
     */
    public function listPending(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        try {
            $orders = $this->getPendingUseCase->execute($args['aliadoId']);

            $data = array_map(fn($o) => [
                'idSolicitudKey'    => $o->getIdSolicitudKey(),
                'idAdmision'        => $o->getIdAdmision(),
                'nombreDelPaciente' => $o->getNombreDelPaciente(),
                'identificacion'    => $o->getIdentificacion(),
                'tipoDocumento'     => $o->getTipoDeDocumento(),
                'centroDeSalud'     => $o->getCentroDeSalud(),
                'medicoQueOrdena'   => $o->getMedicoQueOrdena(),
                'fechaDeLaOrden'    => $o->getFechaDeLaOrden()->format('Y-m-d H:i:s'),
                'estadoDeLaOrden'   => $o->getEstadoDeLaOrden(),
            ], $orders);

            return $this->json($response, [
                'aliadoId' => $args['aliadoId'],
                'total'    => count($data),
                'orders'   => $data,
            ]);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /aliados/{aliadoId}/orders/mark-sent
     *
     * Body: { "orders": ["SOL-001", "SOL-002", ...] }
     *
     * Marca como 'sent' las órdenes de la lista que pertenezcan al aliado
     * y estén en estado 'pending'. Las demás se omiten con una razón.
     */
    public function markSent(
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $args
    ): ResponseInterface {
        $body = $request->getParsedBody();

        if (empty($body['orders']) || !is_array($body['orders'])) {
            return $this->json($response, [
                'error' => 'El campo "orders" es requerido y debe ser un array de idSolicitudKey',
            ], 422);
        }

        // Filtrar valores no-string por seguridad
        $ids = array_values(array_filter(
            $body['orders'],
            fn($v) => is_string($v) && $v !== ''
        ));

        if (empty($ids)) {
            return $this->json($response, [
                'error' => 'El array "orders" no contiene identificadores válidos',
            ], 422);
        }

        try {
            $result = $this->bulkSentUseCase->execute($args['aliadoId'], $ids);

            return $this->json($response, [
                'aliadoId'     => $args['aliadoId'],
                'totalRecibidas' => count($ids),
                'totalActualizadas' => count($result['updated']),
                'totalOmitidas'     => count($result['skipped']),
                'actualizadas' => $result['updated'],
                'omitidas'     => $result['skipped'],   // ['SOL-X' => 'razón', ...]
            ]);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
