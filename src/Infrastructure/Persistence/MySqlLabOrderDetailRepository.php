<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Domain\Repository\LabOrderDetailRepositoryInterface;
use PDO;

class MySqlLabOrderDetailRepository implements LabOrderDetailRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function saveMany(array $details): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lab_order_details (
                id_solicitud_key, id_admision, cups, nombre_laboratorio, fecha_toma_muestra, metodo, reactivo,
                invima, estado_resultado, fecha_resultado, tipo_id_bacteriologo, id_bacteriologo
            ) VALUES (
                :id_solicitud_key, :id_admision, :cups, :nombre_laboratorio, :fecha_toma_muestra, :metodo, :reactivo,
                :invima, :estado_resultado, :fecha_resultado, :tipo_id_bacteriologo, :id_bacteriologo
            )'
        );

        /** @var LabOrderDetail $detail */
        foreach ($details as $detail) {
            $stmt->execute([
                'id_solicitud_key' => $detail->getIdSolicitudKey(),
                'id_admision' => $detail->getIdAdmision(),
                'cups' => $detail->getCups(),
                'nombre_laboratorio' => $detail->getNombreDelLaboratorio(),
                'fecha_toma_muestra' => $detail->getFechaTomaMuestra()?->format('Y-m-d H:i:s'),
                'metodo' => $detail->getMetodo(),
                'reactivo' => $detail->getReactivo(),
                'invima' => $detail->getInvima(),
                'estado_resultado' => $detail->getEstadoDelResultado(),
                'fecha_resultado' => $detail->getFechaResultado()?->format('Y-m-d H:i:s'),
                'tipo_id_bacteriologo' => $detail->getTipoIdentificacionDelBacteriologo(),
                'id_bacteriologo' => $detail->getIdentificacionDelBacteriologo(),
            ]);
        }
    }
}
