<?php

/**
 * Seed de centros de salud y sus relaciones con aliados.
 *
 * Uso:
 *   php database/seed_health_centers.php
 */

declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        if (getenv(trim($k)) === false) putenv(trim($k) . '=' . trim($v));
    }
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'localhost',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'clinical_lab'
    ),
    getenv('DB_USERNAME') ?: 'api_user',
    getenv('DB_PASSWORD') ?: 'secret',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "✅  Conectado" . PHP_EOL;

// ── Centros de salud ──────────────────────────────────────────────────────────
$centers = [
    [
        'nombre'    => 'Clínica Norte S.A.',
        'ciudad'    => 'Bogotá',
        'direccion' => 'Calle 100 # 15-20',
        'telefono'  => '601-7001000',
        'aliados'   => ['ALIADO-001'],
    ],
    [
        'nombre'    => 'Centro Médico Sur',
        'ciudad'    => 'Medellín',
        'direccion' => 'Carrera 43A # 1-50',
        'telefono'  => '604-4441234',
        'aliados'   => ['ALIADO-002'],
    ],
    [
        'nombre'    => 'Hospital Central',
        'ciudad'    => 'Bogotá',
        'direccion' => 'Av. El Dorado # 68-85',
        'telefono'  => '601-3200000',
        'aliados'   => ['ALIADO-001'],
    ],
    [
        'nombre'    => 'Clínica del Sur Ltda.',
        'ciudad'    => 'Cali',
        'direccion' => 'Calle 5 # 36-08',
        'telefono'  => '602-5551234',
        'aliados'   => ['ALIADO-002'],
    ],
];

echo PHP_EOL . "── Centros de salud ──" . PHP_EOL;

foreach ($centers as $c) {
    // Verificar si ya existe
    $stmt = $pdo->prepare('SELECT id FROM health_centers WHERE nombre = :nombre');
    $stmt->execute(['nombre' => $c['nombre']]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $centerId = (int) $existing;
        echo "  ⚠️  '{$c['nombre']}' ya existe (id={$centerId}), omitido." . PHP_EOL;
    } else {
        $pdo->prepare(
            'INSERT INTO health_centers (nombre, ciudad, direccion, telefono, activo)
             VALUES (:nombre, :ciudad, :direccion, :telefono, 1)'
        )->execute([
            'nombre'    => $c['nombre'],
            'ciudad'    => $c['ciudad'],
            'direccion' => $c['direccion'],
            'telefono'  => $c['telefono'],
        ]);
        $centerId = (int) $pdo->lastInsertId();
        echo "  ✔  Centro creado: [{$centerId}] {$c['nombre']} — {$c['ciudad']}" . PHP_EOL;
    }

    // Asociar aliados
    foreach ($c['aliados'] as $aliadoId) {
        $pdo->prepare(
            'INSERT IGNORE INTO aliado_health_center (aliado_id, health_center_id)
             VALUES (:aliado_id, :health_center_id)'
        )->execute(['aliado_id' => $aliadoId, 'health_center_id' => $centerId]);
        echo "      ↳ Asociado a {$aliadoId}" . PHP_EOL;
    }
}

// ── Actualizar órdenes existentes con health_center_id ────────────────────────
echo PHP_EOL . "── Vinculando órdenes existentes a centros de salud ──" . PHP_EOL;

$orders = $pdo->query(
    "SELECT id_solicitud_key, centro_salud FROM lab_orders WHERE health_center_id IS NULL"
)->fetchAll();

$updated = 0;
foreach ($orders as $order) {
    $stmt = $pdo->prepare('SELECT id FROM health_centers WHERE nombre = :nombre LIMIT 1');
    $stmt->execute(['nombre' => $order['centro_salud']]);
    $centerId = $stmt->fetchColumn();

    if ($centerId) {
        $pdo->prepare(
            'UPDATE lab_orders SET health_center_id = :hc WHERE id_solicitud_key = :id'
        )->execute(['hc' => $centerId, 'id' => $order['id_solicitud_key']]);
        $updated++;
    }
}
echo "  ✔  {$updated} orden(es) vinculadas a centros de salud." . PHP_EOL;

// ── Migrar pacientes desde órdenes existentes ─────────────────────────────────
echo PHP_EOL . "── Migrando pacientes desde órdenes existentes ──" . PHP_EOL;

$pdo->exec(
    "INSERT IGNORE INTO patients (tipo_documento, identificacion, nombre, sexo, fecha_nacimiento)
     SELECT DISTINCT tipo_documento, identificacion, nombre_paciente, sexo, fecha_nacimiento
     FROM lab_orders WHERE patient_id IS NULL"
);

$pdo->exec(
    "UPDATE lab_orders lo
     JOIN patients p ON p.tipo_documento = lo.tipo_documento
                    AND p.identificacion  = lo.identificacion
     SET lo.patient_id = p.id
     WHERE lo.patient_id IS NULL"
);

$totalPacientes = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
echo "  ✔  Pacientes en tabla: {$totalPacientes}" . PHP_EOL;

// ── Resumen ───────────────────────────────────────────────────────────────────
echo PHP_EOL . "── Resumen ──" . PHP_EOL;
echo "  Centros de salud : " . $pdo->query("SELECT COUNT(*) FROM health_centers")->fetchColumn()        . PHP_EOL;
echo "  Relaciones       : " . $pdo->query("SELECT COUNT(*) FROM aliado_health_center")->fetchColumn()  . PHP_EOL;
echo PHP_EOL . "✅  Seed de centros de salud completado." . PHP_EOL;
