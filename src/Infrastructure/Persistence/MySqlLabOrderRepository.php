<?php

namespace ClinicalLab\Infrastructure\Persistence;

use ClinicalLab\Domain\Entity\LabOrder;
use ClinicalLab\Domain\Entity\LabOrderDetail;
use ClinicalLab\Domain\Repository\LabOrderRepositoryInterface;
use DateTimeImmutable;
use PDO;

class MySqlLabOrderRepository implements LabOrderRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function save(LabOrder $order): void
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO lab_orders (
                id_solicitud_key, id_admision, id_atencion, tipo_documento, identificacion, nombre_paciente,
                sexo, fecha_nacimiento, centro_salud, fecha_orden, medico_ordena, numero_autorizacion,
                id_aliado, fecha_envio, porc_ejecucion, estado_orden
            ) VALUES (:id_solicitud_key, :id_admision, :id_atencion, :tipo_documento, :identificacion,
                :nombre_paciente, :sexo, :fecha_nacimiento, :centro_salud, :fecha_orden, :medico_ordena,
                :numero_autorizacion, :id_aliado, :fecha_envio, :porc_ejecucion, :estado_orden)'
        );

        $stmt->execute([
            'id_solicitud_key' => $order->getIdSolicitudKey(),
            'id_admision' => $order->getIdAdmision(),
            'id_atencion' => $order->getIdAtencion(),
            'tipo_documento' => $order->getTipoDeDocumento(),
            'identificacion' => $order->getIdentificacion(),
            'nombre_paciente' => $order->getNombreDelPaciente(),
            'sexo' => $order->getSexo(),
            'fecha_nacimiento' => $order->getFechaDeNacimiento()->format('Y-m-d'),
            'centro_salud' => $order->getCentroDeSalud(),
            'fecha_orden' => $order->getFechaDeLaOrden()->format('Y-m-d H:i:s'),
            'medico_ordena' => $order->getMedicoQueOrdena(),
            'numero_autorizacion' => $order->getNumeroDeAutorizacion(),
            'id_aliado' => $order->getIdAliado(),
            'fecha_envio' => $order->getFechaEnvio()?->format('Y-m-d H:i:s'),
            'porc_ejecucion' => $order->getPorcEjecucion(),
            'estado_orden' => $order->getEstadoDeLaOrden(),
        ]);
    }

    public function update(LabOrder $order): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE lab_orders SET fecha_envio = :fecha_envio, porc_ejecucion = :porc_ejecucion,
                estado_orden = :estado_orden WHERE id_solicitud_key = :id_solicitud_key'
        );

        $stmt->execute([
            'fecha_envio' => $order->getFechaEnvio()?->format('Y-m-d H:i:s'),
            'porc_ejecucion' => $order->getPorcEjecucion(),
            'estado_orden' => $order->getEstadoDeLaOrden(),
            'id_solicitud_key' => $order->getIdSolicitudKey(),
        ]);
    }

    public function findByIdSolicitudKey(string $idSolicitudKey): ?LabOrder
    {
        $stmt = $this->connection->prepare('SELECT * FROM lab_orders WHERE id_solicitud_key = :id');
        $stmt->execute(['id' => $idSolicitudKey]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $order = new LabOrder(
            $row['id_solicitud_key'],
            $row['id_admision'],
            $row['id_atencion'],
            $row['tipo_documento'],
            $row['identificacion'],
            $row['nombre_paciente'],
            $row['sexo'],
            new DateTimeImmutable($row['fecha_nacimiento']),
            $row['centro_salud'],
            new DateTimeImmutable($row['fecha_orden']),
            $row['medico_ordena'],
            $row['numero_autorizacion'],
            $row['id_aliado'],
            $row['fecha_envio'] ? new DateTimeImmutable($row['fecha_envio']) : null,
            (float) $row['porc_ejecucion'],
            $row['estado_orden']
        );

        $detailsStmt = $this->connection->prepare('SELECT * FROM lab_order_details WHERE id_solicitud_key = :id');
        $detailsStmt->execute(['id' => $idSolicitudKey]);

        while ($detailRow = $detailsStmt->fetch()) {
            $order->addDetail(new LabOrderDetail(
                $detailRow['id_solicitud_key'],
                $detailRow['id_admision'],
                $detailRow['cups'],
                $detailRow['nombre_laboratorio'],
                $detailRow['fecha_toma_muestra'] ? new DateTimeImmutable($detailRow['fecha_toma_muestra']) : null,
                $detailRow['metodo'],
                $detailRow['reactivo'],
                $detailRow['invima'],
                $detailRow['estado_resultado'],
                $detailRow['fecha_resultado'] ? new DateTimeImmutable($detailRow['fecha_resultado']) : null,
                $detailRow['tipo_id_bacteriologo'],
                $detailRow['id_bacteriologo']
            ));
        }

        return $order;
    }
}
