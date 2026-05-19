<?php

namespace ClinicalLab\Application\UseCase;

use ClinicalLab\Domain\Entity\Patient;
use ClinicalLab\Domain\Repository\PatientRepositoryInterface;
use RuntimeException;

class PatientUseCase
{
    public function __construct(
        private readonly PatientRepositoryInterface $patientRepo,
    ) {
    }

    /** @return array{data: Patient[], total: int, page: int, limit: int} */
    public function list(?string $search = null, int $page = 1, int $limit = 20): array
    {
        return [
            'data'  => $this->patientRepo->findAll($search, $page, $limit),
            'total' => $this->patientRepo->countAll($search),
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    public function findById(int $id): Patient
    {
        $patient = $this->patientRepo->findById($id);
        if (!$patient) {
            throw new RuntimeException("Paciente no encontrado: {$id}");
        }
        return $patient;
    }

    /**
     * Busca un paciente por documento. Si no existe, lo crea.
     * Retorna el paciente (existente o recién creado) y si fue creado.
     *
     * @return array{patient: Patient, created: bool}
     */
    public function findOrCreate(
        string $tipoDocumento,
        string $identificacion,
        string $nombre,
        string $sexo,
        string $fechaNacimiento
    ): array {
        $existing = $this->patientRepo->findByDocument($tipoDocumento, $identificacion);

        if ($existing) {
            return ['patient' => $existing, 'created' => false];
        }

        $newPatient = new Patient(
            0,
            $tipoDocumento,
            $identificacion,
            $nombre,
            $sexo,
            new \DateTimeImmutable($fechaNacimiento)
        );

        $id = $this->patientRepo->save($newPatient);

        return [
            'patient' => new Patient($id, $tipoDocumento, $identificacion, $nombre, $sexo, new \DateTimeImmutable($fechaNacimiento)),
            'created' => true,
        ];
    }
}
