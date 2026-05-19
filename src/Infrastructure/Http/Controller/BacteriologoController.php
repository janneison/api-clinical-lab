<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Domain\Entity\Bacteriologo;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use ClinicalLab\Domain\Repository\BacteriologoRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class BacteriologoController
{
    private const FIRMA_DIR        = __DIR__ . '/../../../../storage/firmas/';
    private const FIRMA_URL_PREFIX = '/storage/firmas/';
    private const ALLOWED_MIME     = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    private const MAX_SIZE_BYTES   = 2 * 1024 * 1024; // 2 MB

    public function __construct(
        private readonly BacteriologoRepositoryInterface $bacteriologoRepository,
        private readonly AliadoRepositoryInterface       $aliadoRepository,
    ) {
    }

    // GET /aliados/{aliadoId}/bacteriologos
    public function listByAliado(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->aliadoRepository->findById($args['aliadoId'])) {
            return $this->json($response, ['error' => "Aliado no encontrado: {$args['aliadoId']}"], 404);
        }

        $params      = $request->getQueryParams();
        $soloActivos = ($params['activo'] ?? '1') !== '0';

        $bacteriologos = $this->bacteriologoRepository->findByAliadoId($args['aliadoId'], $soloActivos);

        return $this->json($response, array_map(fn($b) => $this->serialize($b), $bacteriologos));
    }

    // GET /bacteriologos/{id}
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bact = $this->bacteriologoRepository->findById((int) $args['id']);
        if (!$bact) {
            return $this->json($response, ['error' => "Bacteriólogo no encontrado: {$args['id']}"], 404);
        }
        return $this->json($response, $this->serialize($bact));
    }

    // POST /aliados/{aliadoId}/bacteriologos
    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->aliadoRepository->findById($args['aliadoId'])) {
            return $this->json($response, ['error' => "Aliado no encontrado: {$args['aliadoId']}"], 404);
        }

        $body = $request->getParsedBody();

        foreach (['tipoDocumento', 'identificacion', 'nombre'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        // Verificar duplicado
        if ($this->bacteriologoRepository->findByDocument($body['tipoDocumento'], $body['identificacion'])) {
            return $this->json($response, ['error' => 'Ya existe un bacteriólogo con ese documento'], 422);
        }

        $id = $this->bacteriologoRepository->save(new Bacteriologo(
            0,
            $args['aliadoId'],
            trim($body['tipoDocumento']),
            trim($body['identificacion']),
            trim($body['nombre']),
            $body['tarjetaProfesional'] ?? null,
            $body['universidad']        ?? null,
            null,
            true,
        ));

        $bact = $this->bacteriologoRepository->findById($id);
        return $this->json($response, $this->serialize($bact), 201);
    }

    // PUT /bacteriologos/{id}
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bact = $this->bacteriologoRepository->findById((int) $args['id']);
        if (!$bact) {
            return $this->json($response, ['error' => "Bacteriólogo no encontrado: {$args['id']}"], 404);
        }

        $body = $request->getParsedBody();

        if (empty($body['nombre'])) {
            return $this->json($response, ['error' => 'Campo requerido: nombre'], 422);
        }

        $this->bacteriologoRepository->update(new Bacteriologo(
            $bact->getId(),
            $bact->getAliadoId(),
            $body['tipoDocumento']    ?? $bact->getTipoDocumento(),
            $body['identificacion']   ?? $bact->getIdentificacion(),
            trim($body['nombre']),
            $body['tarjetaProfesional'] ?? $bact->getTarjetaProfesional(),
            $body['universidad']        ?? $bact->getUniversidad(),
            $bact->getFirmaPath(),
            (bool) ($body['activo'] ?? $bact->isActivo()),
        ));

        return $this->json($response, ['message' => 'Bacteriólogo actualizado']);
    }

    // DELETE /bacteriologos/{id}
    public function deactivate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bact = $this->bacteriologoRepository->findById((int) $args['id']);
        if (!$bact) {
            return $this->json($response, ['error' => "Bacteriólogo no encontrado: {$args['id']}"], 404);
        }

        $this->bacteriologoRepository->deactivate((int) $args['id']);
        return $this->json($response, ['message' => 'Bacteriólogo desactivado']);
    }

    // POST /bacteriologos/{id}/firma
    public function uploadFirma(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $bact = $this->bacteriologoRepository->findById((int) $args['id']);
        if (!$bact) {
            return $this->json($response, ['error' => "Bacteriólogo no encontrado: {$args['id']}"], 404);
        }

        $files = $request->getUploadedFiles();

        if (empty($files['firma'])) {
            return $this->json($response, ['error' => 'Se requiere el campo "firma" como archivo multipart'], 422);
        }

        $file = $files['firma'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, ['error' => 'Error al subir el archivo: código ' . $file->getError()], 422);
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return $this->json($response, ['error' => 'El archivo supera el tamaño máximo de 2 MB'], 422);
        }

        $mime = $file->getClientMediaType();
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return $this->json($response, ['error' => 'Formato no permitido. Use PNG o JPG'], 422);
        }

        if (!is_dir(self::FIRMA_DIR)) {
            mkdir(self::FIRMA_DIR, 0755, true);
        }

        $ext      = self::ALLOWED_MIME[$mime];
        $filename = 'firma_bact_' . $bact->getId() . '.' . $ext;

        // Eliminar firma anterior
        foreach (['png', 'jpg'] as $oldExt) {
            $old = self::FIRMA_DIR . 'firma_bact_' . $bact->getId() . '.' . $oldExt;
            if (file_exists($old)) unlink($old);
        }

        $file->moveTo(self::FIRMA_DIR . $filename);

        $firmaPath = self::FIRMA_URL_PREFIX . $filename;
        $this->bacteriologoRepository->updateFirma($bact->getId(), $firmaPath);

        return $this->json($response, [
            'message'   => 'Firma actualizada correctamente',
            'firmaPath' => $firmaPath,
        ]);
    }

    private function serialize(Bacteriologo $b): array
    {
        return [
            'id'                 => $b->getId(),
            'aliadoId'           => $b->getAliadoId(),
            'tipoDocumento'      => $b->getTipoDocumento(),
            'identificacion'     => $b->getIdentificacion(),
            'nombre'             => $b->getNombre(),
            'tarjetaProfesional' => $b->getTarjetaProfesional(),
            'universidad'        => $b->getUniversidad(),
            'firmaPath'          => $b->getFirmaPath(),
            'activo'             => $b->isActivo(),
        ];
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
