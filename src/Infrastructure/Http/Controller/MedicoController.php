<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Domain\Entity\Medico;
use ClinicalLab\Domain\Repository\MedicoRepositoryInterface;
use ClinicalLab\Domain\Repository\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class MedicoController
{
    public function __construct(
        private readonly MedicoRepositoryInterface $medicoRepository,
        private readonly UserRepositoryInterface   $userRepository,
    ) {
    }

    // GET /medicos
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params     = $request->getQueryParams();
        $search     = $params['q']      ?? null;
        $soloActivos = ($params['activo'] ?? '1') !== '0';

        $medicos = $this->medicoRepository->findAll($search ?: null, $soloActivos);
        return $this->json($response, array_map(fn($m) => $this->serialize($m), $medicos));
    }

    // GET /medicos/{id}
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $medico = $this->medicoRepository->findById((int) $args['id']);
        if (!$medico) {
            return $this->json($response, ['error' => "Médico no encontrado: {$args['id']}"], 404);
        }
        return $this->json($response, $this->serialize($medico));
    }

    // POST /medicos
    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['tipoDocumento', 'identificacion', 'nombre'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        if ($this->medicoRepository->findByDocument($body['tipoDocumento'], $body['identificacion'])) {
            return $this->json($response, ['error' => 'Ya existe un médico con ese documento'], 422);
        }

        // Validar user_id si se envía
        $userId = null;
        if (!empty($body['userId'])) {
            $userId = (int) $body['userId'];
            if (!$this->userRepository->findById($userId)) {
                return $this->json($response, ['error' => "Usuario no encontrado: {$userId}"], 422);
            }
            // Verificar que ese usuario no tenga ya un médico asociado
            if ($this->medicoRepository->findByUserId($userId)) {
                return $this->json($response, ['error' => 'Ese usuario ya tiene un médico asociado'], 422);
            }
        }

        try {
            $id = $this->medicoRepository->save(new Medico(
                0,
                trim($body['tipoDocumento']),
                trim($body['identificacion']),
                trim($body['nombre']),
                isset($body['especialidad'])    ? trim($body['especialidad'])    : null,
                isset($body['registroMedico'])  ? trim($body['registroMedico'])  : null,
                $userId,
                true,
            ));
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }

        return $this->json($response, $this->serialize($this->medicoRepository->findById($id)), 201);
    }

    // PUT /medicos/{id}
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $medico = $this->medicoRepository->findById((int) $args['id']);
        if (!$medico) {
            return $this->json($response, ['error' => "Médico no encontrado: {$args['id']}"], 404);
        }

        $body = $request->getParsedBody();

        // Validar user_id si cambia
        $userId = $medico->getUserId();
        if (array_key_exists('userId', $body)) {
            $userId = $body['userId'] !== null && $body['userId'] !== '' ? (int) $body['userId'] : null;
            if ($userId !== null) {
                if (!$this->userRepository->findById($userId)) {
                    return $this->json($response, ['error' => "Usuario no encontrado: {$userId}"], 422);
                }
                $existing = $this->medicoRepository->findByUserId($userId);
                if ($existing && $existing->getId() !== $medico->getId()) {
                    return $this->json($response, ['error' => 'Ese usuario ya tiene un médico asociado'], 422);
                }
            }
        }

        try {
            $this->medicoRepository->update(new Medico(
                $medico->getId(),
                trim($body['tipoDocumento']   ?? $medico->getTipoDocumento()),
                trim($body['identificacion']  ?? $medico->getIdentificacion()),
                trim($body['nombre']          ?? $medico->getNombre()),
                array_key_exists('especialidad',   $body) ? ($body['especialidad']   ?: null) : $medico->getEspecialidad(),
                array_key_exists('registroMedico', $body) ? ($body['registroMedico'] ?: null) : $medico->getRegistroMedico(),
                $userId,
                (bool) ($body['activo'] ?? $medico->isActivo()),
            ));
        } catch (Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }

        return $this->json($response, $this->serialize($this->medicoRepository->findById($medico->getId())));
    }

    // DELETE /medicos/{id}
    public function deactivate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $medico = $this->medicoRepository->findById((int) $args['id']);
        if (!$medico) {
            return $this->json($response, ['error' => "Médico no encontrado: {$args['id']}"], 404);
        }
        $this->medicoRepository->deactivate((int) $args['id']);
        return $this->json($response, ['message' => 'Médico desactivado']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function serialize(Medico $m): array
    {
        return [
            'id'             => $m->getId(),
            'tipoDocumento'  => $m->getTipoDocumento(),
            'identificacion' => $m->getIdentificacion(),
            'nombre'         => $m->getNombre(),
            'especialidad'   => $m->getEspecialidad(),
            'registroMedico' => $m->getRegistroMedico(),
            'userId'         => $m->getUserId(),
            'activo'         => $m->isActivo(),
        ];
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
