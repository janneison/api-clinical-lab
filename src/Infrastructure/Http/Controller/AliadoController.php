<?php

namespace ClinicalLab\Infrastructure\Http\Controller;

use ClinicalLab\Domain\Entity\Aliado;
use ClinicalLab\Domain\Repository\AliadoRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AliadoController
{
    private const LOGO_DIR        = __DIR__ . '/../../../../storage/logos/';
    private const LOGO_URL_PREFIX = '/storage/logos/';
    private const ALLOWED_MIME    = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    private const MAX_SIZE_BYTES  = 2 * 1024 * 1024; // 2 MB

    public function __construct(private readonly AliadoRepositoryInterface $aliadoRepository)
    {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $aliados = $this->aliadoRepository->findAll();
        return $this->json($response, array_map(fn($a) => $this->serialize($a), $aliados));
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();

        foreach (['id', 'nombre'] as $field) {
            if (empty($body[$field])) {
                return $this->json($response, ['error' => "Campo requerido: {$field}"], 422);
            }
        }

        $id = strtoupper(trim($body['id']));

        if ($this->aliadoRepository->findById($id)) {
            return $this->json($response, ['error' => "Ya existe un aliado con el ID: {$id}"], 422);
        }

        $aliado = new Aliado(
            $id,
            trim($body['nombre']),
            (bool) ($body['activo'] ?? true),
            isset($body['nit'])       ? trim($body['nit'])       : null,
            isset($body['direccion']) ? trim($body['direccion']) : null,
            isset($body['email'])     ? trim($body['email'])     : null,
            null,
        );

        $this->aliadoRepository->save($aliado);

        return $this->json($response, $this->serialize($aliado), 201);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $aliado = $this->aliadoRepository->findById($args['id']);
        if (!$aliado) {
            return $this->json($response, ['error' => "Aliado no encontrado: {$args['id']}"], 404);
        }
        return $this->json($response, $this->serialize($aliado));
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $aliado = $this->aliadoRepository->findById($args['id']);
        if (!$aliado) {
            return $this->json($response, ['error' => "Aliado no encontrado: {$args['id']}"], 404);
        }

        $body = $request->getParsedBody();

        if (empty($body['nombre'])) {
            return $this->json($response, ['error' => 'Campo requerido: nombre'], 422);
        }

        $updated = new Aliado(
            $aliado->getId(),
            trim($body['nombre']),
            (bool) ($body['activo'] ?? $aliado->isActivo()),
            $body['nit']       ?? $aliado->getNit(),
            $body['direccion'] ?? $aliado->getDireccion(),
            $body['email']     ?? $aliado->getEmail(),
            $aliado->getLogoPath(),
        );

        $this->aliadoRepository->update($updated);

        return $this->json($response, [
            'message' => 'Aliado actualizado',
            'aliado'  => $this->serialize($updated),
        ]);
    }

    public function uploadLogo(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $aliado = $this->aliadoRepository->findById($args['id']);
        if (!$aliado) {
            return $this->json($response, ['error' => "Aliado no encontrado: {$args['id']}"], 404);
        }

        $files = $request->getUploadedFiles();

        if (empty($files['logo'])) {
            return $this->json($response, ['error' => 'Se requiere el campo "logo" como archivo multipart'], 422);
        }

        $file = $files['logo'];

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

        $ext      = self::ALLOWED_MIME[$mime];
        $filename = strtolower($aliado->getId()) . '_logo.' . $ext;
        $destDir  = self::LOGO_DIR;

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        foreach (['png', 'jpg'] as $oldExt) {
            $old = $destDir . strtolower($aliado->getId()) . '_logo.' . $oldExt;
            if (file_exists($old)) {
                unlink($old);
            }
        }

        $file->moveTo($destDir . $filename);

        $logoPath = self::LOGO_URL_PREFIX . $filename;
        $this->aliadoRepository->updateLogo($aliado->getId(), $logoPath);

        return $this->json($response, [
            'message'  => 'Logo actualizado correctamente',
            'logoPath' => $logoPath,
        ]);
    }

    private function serialize(Aliado $a): array
    {
        return [
            'id'        => $a->getId(),
            'nombre'    => $a->getNombre(),
            'nit'       => $a->getNit(),
            'direccion' => $a->getDireccion(),
            'email'     => $a->getEmail(),
            'logoPath'  => $a->getLogoPath(),
            'activo'    => $a->isActivo(),
        ];
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
