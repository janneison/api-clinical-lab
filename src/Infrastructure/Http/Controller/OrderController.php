<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\LabOrderDetailDto;
use ClinicalLab\Application\Dto\LabOrderRequestDto;
use ClinicalLab\Application\Dto\OrderFilterDto;
use ClinicalLab\Application\UseCase\CreateLabOrderUseCase;
use ClinicalLab\Application\UseCase\SendLabOrderUseCase;
use ClinicalLab\Domain\Entity\Role;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class OrderController
{
    public function __construct(
        private readonly CreateLabOrderUseCase $createUseCase,
        private readonly SendLabOrderUseCase $sendUseCase,
        private readonly LabOrderRepositoryInterface $orderRepository
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $auth   = $request->getAttribute('auth');
        $role   = $auth['role'] ?? '';
        $params = $request->getQueryParams();

        // Determinar restricción por aliado y/o centro de salud según rol
        if (in_array($role, [Role::ADMIN, Role::LAB_OPERATOR], true)) {
            $aliadoIds       = null;   // sin restricción
            $healthCenterIds = null;
        } elseif ($role === Role::MEDICO) {
            // El médico solo ve las órdenes de sus centros de salud asignados
            $aliadoIds       = null;
            $healthCenterIds = $auth['health_centers'] ?? [];
        } else {
            // aliado_operator y viewer solo ven sus aliados
            $aliadoIds       = $auth['aliados'] ?? [];
            $healthCenterIds = null;
        }

        // Validar y parsear parámetros
        $estado     = $params['estado']      ?? null;
        $fechaDesde = $params['fecha_desde'] ?? null;
        $fechaHasta = $params['fecha_hasta'] ?? null;
        $cups       = $params['cups']        ?? null;
        $page       = max(1, (int) ($params['page']  ?? 1));
        $limit      = min(100, max(1, (int) ($params['limit'] ?? 20)));

        $estadosValidos = ['pending', 'sent', 'completed'];
        if ($estado !== null && !in_array($estado, $estadosValidos, true)) {
            return $this->json($response, [
                'error' => "Estado inválido. Valores permitidos: " . implode(', ', $estadosValidos),
            ], 422);
        }

        if ($fechaDesde !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            return $this->json($response, ['error' => 'fecha_desde debe tener formato YYYY-MM-DD'], 422);
        }

        if ($fechaHasta !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            return $this->json($response, ['error' => 'fecha_hasta debe tener formato YYYY-MM-DD'], 422);
        }

        $filter = new OrderFilterDto(
            aliadoIds:       $aliadoIds,
            estado:          $estado,
            fechaDesde:      $fechaDesde,
            fechaHasta:      $fechaHasta,
            cups:            $cups,
            page:            $page,
            limit:           $limit,
            healthCenterIds: $healthCenterIds,
        );

        $orders = $this->orderRepository->findByFilter($filter);
        $total  = $this->orderRepository->countByFilter($filter);

        $items = array_map(fn($o) => [
            'idSolicitudKey'    => $o->getIdSolicitudKey(),
            'idAdmision'        => $o->getIdAdmision(),
            'nombreDelPaciente' => $o->getNombreDelPaciente(),
            'identificacion'    => $o->getIdentificacion(),
            'tipoDocumento'     => $o->getTipoDeDocumento(),
            'sexo'              => $o->getSexo(),
            'centroDeSalud'     => $o->getCentroDeSalud(),
            'medicoQueOrdena'   => $o->getMedicoQueOrdena(),
            'idAliado'          => $o->getIdAliado(),
            'fechaDeLaOrden'    => $o->getFechaDeLaOrden()->format('Y-m-d H:i:s'),
            'fechaEnvio'        => $o->getFechaEnvio()?->format('Y-m-d H:i:s'),
            'estadoDeLaOrden'   => $o->getEstadoDeLaOrden(),
            'porcEjecucion'     => $o->getPorcEjecucion(),
        ], $orders);

        return $this->json($response, [
            'data'       => $items,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface    {
        $body = $request->getParsedBody();

        $required = ['idSolicitudKey', 'idAdmision', 'tipoDeDocumento', 'identificacion',
                     'nombreDelPaciente', 'sexo', 'fechaDeNacimiento', 'centroDeSalud',
                     'fechaDeLaOrden', 'medicoQueOrdena', 'detalles'];

        foreach ($required as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido faltante: $field"], 422);
            }
        }

        if (!is_array($body['detalles']) || count($body['detalles']) === 0) {
            return $this->json($response, ['error' => 'Debe incluir al menos un detalle'], 422);
        }

        try {
            $detalles = array_map(fn(array $d) => new LabOrderDetailDto(
                $d['idSolicitudKey'] ?? $body['idSolicitudKey'],
                $d['idAdmision'] ?? $body['idAdmision'],
                $d['cups'] ?? '',
                $d['nombreDelLaboratorio'] ?? '',
                $d['fechaTomaMuestra'] ?? null,
                $d['metodo'] ?? null,
                $d['reactivo'] ?? null,
                $d['invima'] ?? null,
                $d['estadoDelResultado'] ?? null,
                $d['fechaResultado'] ?? null,
                $d['tipoIdentificacionDelBacteriologo'] ?? null,
                $d['identificacionDelBacteriologo'] ?? null
            ), $body['detalles']);

            $dto = new LabOrderRequestDto(
                $body['idSolicitudKey'],
                $body['idAdmision'],
                $body['idAtencion'] ?? null,
                $body['tipoDeDocumento'],
                $body['identificacion'],
                $body['nombreDelPaciente'],
                $body['sexo'],
                $body['fechaDeNacimiento'],
                $body['centroDeSalud'],
                $body['fechaDeLaOrden'],
                $body['medicoQueOrdena'],
                $body['numeroDeAutorizacion'] ?? null,
                $body['idAliado'] ?? null,
                $body['porcEjecucion'] ?? '0',
                $detalles,
                isset($body['healthCenterId'])        ? (int) $body['healthCenterId']        : null,
                isset($body['medicoId'])              ? (int) $body['medicoId']              : null,
                $body['tipoDocumentoMedico']          ?? null,
                $body['identificacionMedico']         ?? null,
            );

            $order = $this->createUseCase->execute($dto);

            return $this->json($response, [
                'idSolicitudKey'  => $order->getIdSolicitudKey(),
                'estadoDeLaOrden' => $order->getEstadoDeLaOrden(),
                'porcEjecucion'   => $order->getPorcEjecucion(),
                'medicoId'        => $order->getMedicoId(),
                'detalles'        => count($order->getDetails()),
            ], 201);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $order = $this->orderRepository->findByIdSolicitudKey($args['id']);

        if (!$order) {
            return $this->json($response, ['error' => 'Orden no encontrada'], 404);
        }

        $details = array_map(fn($d) => [
            'cups'                             => $d->getCups(),
            'nombreDelLaboratorio'             => $d->getNombreDelLaboratorio(),
            'fechaTomaMuestra'                 => $d->getFechaTomaMuestra()?->format('Y-m-d H:i:s'),
            'metodo'                           => $d->getMetodo(),
            'reactivo'                         => $d->getReactivo(),
            'invima'                           => $d->getInvima(),
            'estadoDelResultado'               => $d->getEstadoDelResultado(),
            'fechaResultado'                   => $d->getFechaResultado()?->format('Y-m-d H:i:s'),
            'tipoIdentificacionDelBacteriologo' => $d->getTipoIdentificacionDelBacteriologo(),
            'identificacionDelBacteriologo'    => $d->getIdentificacionDelBacteriologo(),
        ], $order->getDetails());

        return $this->json($response, [
            'idSolicitudKey'    => $order->getIdSolicitudKey(),
            'idAdmision'        => $order->getIdAdmision(),
            'nombreDelPaciente' => $order->getNombreDelPaciente(),
            'estadoDeLaOrden'   => $order->getEstadoDeLaOrden(),
            'porcEjecucion'     => $order->getPorcEjecucion(),
            'fechaEnvio'        => $order->getFechaEnvio()?->format('Y-m-d H:i:s'),
            'medicoId'          => $order->getMedicoId(),
            'medicoQueOrdena'   => $order->getMedicoQueOrdena(),
            'detalles'          => $details,
        ]);
    }

    public function send(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $this->sendUseCase->execute($args['id']);
            $order = $this->orderRepository->findByIdSolicitudKey($args['id']);

            return $this->json($response, [
                'idSolicitudKey' => $order->getIdSolicitudKey(),
                'estadoDeLaOrden' => $order->getEstadoDeLaOrden(),
                'fechaEnvio' => $order->getFechaEnvio()?->format(DATE_ATOM),
            ]);
        } catch (RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'no encontrada') ? 404 : 502;
            return $this->json($response, ['error' => $e->getMessage()], $code);
        }
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
