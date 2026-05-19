<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Application\Dto\OrderFilterDto;
use ClinicalLab\Application\UseCase\PatientUseCase;
use ClinicalLab\Domain\Entity\Patient;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use ClinicalLab\Domain\Repository\PatientRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

class PatientController
{
    public function __construct(
        private readonly PatientUseCase              $useCase,
        private readonly LabOrderRepositoryInterface $orderRepository,
        private readonly PatientRepositoryInterface  $patientRepository,
    ) {
    }

    // GET /patients
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $search = $params['q']    ?? null;
        $page   = max(1, (int) ($params['page']  ?? 1));
        $limit  = min(100, max(1, (int) ($params['limit'] ?? 20)));

        $result = $this->useCase->list($search ?: null, $page, $limit);

        $data = array_map(fn($p) => $this->serialize($p), $result['data']);

        return $this->json($response, [
            'data'       => $data,
            'pagination' => [
                'total'       => $result['total'],
                'page'        => $result['page'],
                'limit'       => $result['limit'],
                'total_pages' => (int) ceil($result['total'] / $result['limit']),
            ],
        ]);
    }

    // GET /patients/{id}
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            $patient = $this->useCase->findById((int) $args['id']);
        } catch (RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 404);
        }

        $orders = $this->orderRepository->findByFilter(new OrderFilterDto(
            aliadoIds: null,
            patientId: $patient->getId(),
            limit:     1000,
        ));

        $ordersData = array_map(fn($o) => [
            'idSolicitudKey'  => $o->getIdSolicitudKey(),
            'fechaDeLaOrden'  => $o->getFechaDeLaOrden()->format('Y-m-d H:i:s'),
            'estadoDeLaOrden' => $o->getEstadoDeLaOrden(),
            'idAliado'        => $o->getIdAliado(),
            'centroDeSalud'   => $o->getCentroDeSalud(),
        ], $orders);

        return $this->json($response, array_merge($this->serialize($patient), [
            'ordenes'      => $ordersData,
            'totalOrdenes' => count($ordersData),
        ]));
    }

    // POST /patients
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['tipoDocumento', 'identificacion', 'nombre', 'sexo', 'fechaNacimiento'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        // Verificar duplicado
        if ($this->patientRepository->findByDocument($body['tipoDocumento'], $body['identificacion'])) {
            return $this->json($response, ['error' => 'Ya existe un paciente con ese tipo y número de documento'], 422);
        }

        try {
            $fechaNacimiento = new DateTimeImmutable($body['fechaNacimiento']);
        } catch (Throwable) {
            return $this->json($response, ['error' => 'fechaNacimiento debe tener formato YYYY-MM-DD'], 422);
        }

        $sexo = strtoupper(trim($body['sexo']));
        if (!in_array($sexo, ['M', 'F'], true)) {
            return $this->json($response, ['error' => 'sexo debe ser M o F'], 422);
        }

        $patient = new Patient(
            0,
            trim($body['tipoDocumento']),
            trim($body['identificacion']),
            trim($body['nombre']),
            $sexo,
            $fechaNacimiento,
            isset($body['email'])    ? trim($body['email'])    : null,
            isset($body['telefono']) ? trim($body['telefono']) : null,
        );

        try {
            $id = $this->patientRepository->save($patient);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }

        $created = $this->patientRepository->findById($id);
        return $this->json($response, $this->serialize($created), 201);
    }

    // PUT /patients/{id}
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $existing = $this->patientRepository->findById((int) $args['id']);
        if (!$existing) {
            return $this->json($response, ['error' => "Paciente no encontrado: {$args['id']}"], 404);
        }

        $body = $request->getParsedBody();

        // Validar fechaNacimiento si se envía
        $fechaNacimiento = $existing->getFechaNacimiento();
        if (!empty($body['fechaNacimiento'])) {
            try {
                $fechaNacimiento = new DateTimeImmutable($body['fechaNacimiento']);
            } catch (Throwable) {
                return $this->json($response, ['error' => 'fechaNacimiento debe tener formato YYYY-MM-DD'], 422);
            }
        }

        // Validar sexo si se envía
        $sexo = $existing->getSexo();
        if (!empty($body['sexo'])) {
            $sexo = strtoupper(trim($body['sexo']));
            if (!in_array($sexo, ['M', 'F'], true)) {
                return $this->json($response, ['error' => 'sexo debe ser M o F'], 422);
            }
        }

        // Si cambia el documento, verificar que no exista otro paciente con ese doc
        $nuevoTipo = trim($body['tipoDocumento'] ?? $existing->getTipoDocumento());
        $nuevoId   = trim($body['identificacion'] ?? $existing->getIdentificacion());

        if ($nuevoTipo !== $existing->getTipoDocumento() || $nuevoId !== $existing->getIdentificacion()) {
            $conflict = $this->patientRepository->findByDocument($nuevoTipo, $nuevoId);
            if ($conflict && $conflict->getId() !== $existing->getId()) {
                return $this->json($response, ['error' => 'Ya existe otro paciente con ese tipo y número de documento'], 422);
            }
        }

        $updated = new Patient(
            $existing->getId(),
            $nuevoTipo,
            $nuevoId,
            trim($body['nombre']   ?? $existing->getNombre()),
            $sexo,
            $fechaNacimiento,
            array_key_exists('email',    $body) ? (trim($body['email'])    ?: null) : $existing->getEmail(),
            array_key_exists('telefono', $body) ? (trim($body['telefono']) ?: null) : $existing->getTelefono(),
        );

        try {
            $this->patientRepository->update($updated);
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }

        return $this->json($response, $this->serialize($this->patientRepository->findById($existing->getId())));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function serialize(Patient $p): array
    {
        return [
            'id'              => $p->getId(),
            'tipoDocumento'   => $p->getTipoDocumento(),
            'identificacion'  => $p->getIdentificacion(),
            'nombre'          => $p->getNombre(),
            'sexo'            => $p->getSexo(),
            'fechaNacimiento' => $p->getFechaNacimiento()->format('Y-m-d'),
            'email'           => $p->getEmail(),
            'telefono'        => $p->getTelefono(),
        ];
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
