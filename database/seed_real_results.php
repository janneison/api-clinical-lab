<?php

/**
 * Seed de resultados reales — basado en PDFs de laboratorio
 *
 * Órdenes creadas:
 *   SOL-REAL-001  BALLESTEROS MARTINEZ LILIANA PAOLA  (EVM SAS IPS Ciudadela)
 *                 → Hemograma IV (902210)  MINDRAY BC-3000
 *
 *   SOL-REAL-002  LOPÈZ ESPITIA VICTORIA  (Fleming S.A.S)
 *                 → Hemograma IV (902210)  NIHON KOHDEN
 *                 → Urocultivo y Antibiograma (901236)
 *
 *   SOL-REAL-003  RIVERO ARRIETA KELLY  (Fleming S.A.S)
 *                 → Hemograma IV (902210)  NIHON KOHDEN
 *                 → Colesterol Total (903818)
 *                 → Triglicéridos (903868)
 *                 → Creatinina en Suero (903895)
 *                 → Glucosa en Suero (903841)
 *                 → Colesterol HDL (903815)
 *                 → Colesterol LDL (903816)
 *                 → Frotis Vaginal (901304)
 *                 → Treponema pallidum MHTPA (906039)
 *                 → Urocultivo y Antibiograma (901236)
 *                 → Uroanalisis (907106)
 *                 → VIH 1 y 2 Anticuerpos (906249)
 *
 * Uso:
 *   php database/seed_real_results.php
 */

declare(strict_types=1);

// ── Entorno ───────────────────────────────────────────────────────────────────
$envFile = dirname(__DIR__) . '/config/.env';
if (!file_exists($envFile)) {
    $envFile = dirname(__DIR__) . '/.env';
}
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
        getenv('DB_NAME') ?: 'clinical_lab'),
    getenv('DB_USERNAME') ?: 'api_user',
    getenv('DB_PASSWORD') ?: 'secret',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
echo "✅  Conectado" . PHP_EOL;

// ── Helpers ───────────────────────────────────────────────────────────────────
function upsertOrder(PDO $pdo, array $data): void
{
    $cols  = implode(', ', array_keys($data));
    $ph    = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
    $upd   = implode(', ', array_map(fn($k) => "{$k}=VALUES({$k})", array_keys($data)));
    $pdo->prepare("INSERT INTO lab_orders ({$cols}) VALUES ({$ph}) ON DUPLICATE KEY UPDATE {$upd}")
        ->execute($data);
}

function insertDetail(PDO $pdo, array $data): void
{
    $st = $pdo->prepare("SELECT id FROM lab_order_details WHERE id_solicitud_key=? AND cups=?");
    $st->execute([$data['id_solicitud_key'], $data['cups']]);
    if ($st->fetchColumn()) { echo "      ⚠️  Detalle {$data['cups']} ya existe\n"; return; }
    $cols = implode(', ', array_keys($data));
    $ph   = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
    $pdo->prepare("INSERT INTO lab_order_details ({$cols}) VALUES ({$ph})")->execute($data);
}

function insertResult(PDO $pdo, array $data): void
{
    $st = $pdo->prepare("SELECT id FROM lab_results WHERE id_solicitud_key=? AND cups=?");
    $st->execute([$data['id_solicitud_key'], $data['cups']]);
    if ($st->fetchColumn()) { echo "      ⚠️  Resultado {$data['cups']} ya existe\n"; return; }
    $cols = implode(', ', array_keys($data));
    $ph   = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
    $pdo->prepare("INSERT INTO lab_results ({$cols}) VALUES ({$ph})")->execute($data);
}

// ── Verificar aliados ─────────────────────────────────────────────────────────
foreach (['ALIADO-001', 'ALIADO-002'] as $aid) {
    if (!$pdo->query("SELECT id FROM aliados WHERE id='$aid'")->fetchColumn()) {
        $pdo->prepare("INSERT INTO aliados (id, nombre, activo) VALUES (?,?,1)")
            ->execute([$aid, $aid === 'ALIADO-001' ? 'Laboratorio Clínico Norte' : 'Laboratorio Clínico Sur']);
        echo "  ✔  Aliado $aid creado\n";
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SOL-REAL-001 — BALLESTEROS MARTINEZ LILIANA PAOLA
// Sede CIUDADELA — EVM SAS IPS — Nro. Recepción 93273
// Hemograma IV — MINDRAY BC-3000 — Bacterióloga: Dra. Diosmary Palacios Gonzalez
// ═══════════════════════════════════════════════════════════════════════════════
echo PHP_EOL . "── SOL-REAL-001  Ballesteros Martínez Liliana Paola ──" . PHP_EOL;

upsertOrder($pdo, [
    'id_solicitud_key'    => 'SOL-REAL-001',
    'id_admision'         => 'ADM-93273',
    'id_atencion'         => null,
    'tipo_documento'      => 'CC',
    'identificacion'      => '30669686',
    'nombre_paciente'     => 'BALLESTEROS MARTINEZ LILIANA PAOLA',
    'sexo'                => 'F',
    'fecha_nacimiento'    => '1982-10-30',   // 42A 6M 14D al 13-abr-2026
    'centro_salud'        => 'HOSPITAL MATE CIUDADELA METROPOLITANA',
    'fecha_orden'         => '2025-06-06 07:20:58',
    'medico_ordena'       => 'ASIGNADO NO',
    'numero_autorizacion' => null,
    'id_aliado'           => 'ALIADO-001',
    'fecha_envio'         => '2025-06-09 05:26:00',
    'porc_ejecucion'      => 100.00,
    'estado_orden'        => 'completed',
]);
echo "  ✔  Orden creada\n";

insertDetail($pdo, [
    'id_solicitud_key'   => 'SOL-REAL-001',
    'id_admision'        => 'ADM-93273',
    'cups'               => '902210',
    'nombre_laboratorio' => 'Hemograma IV [Hemoglobina, Hematocrito, Eritrocitos, Índices, Leucograma, Plaquetas, Histograma] Método Automático',
    'fecha_toma_muestra' => '2025-06-06 07:20:58',
    'metodo'             => 'Método Automático',
    'reactivo'           => 'MINDRAY',
    'invima'             => null,
    'estado_resultado'   => 'FINAL',
    'fecha_resultado'    => '2025-06-09 05:26:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'    => '00000001',
]);
echo "  ✔  Detalle 902210\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-001',
    'cups'             => '902210',
    'values_json'      => json_encode([
        // Serie roja
        'hgb'    => ['valor' => 11.40, 'unidad' => 'gr/dl',    'referencia' => '12 a 17',    'flag' => 'bajo'],
        'hct'    => ['valor' => 35.70, 'unidad' => '%',         'referencia' => '36 a 52',    'flag' => 'bajo'],
        'rbc'    => ['valor' => 4.47,  'unidad' => 'x10^6/uL', 'referencia' => '3.50 a 5',   'flag' => 'normal'],
        'mcv'    => ['valor' => 80.00, 'unidad' => 'fL',        'referencia' => '80 a 100',   'flag' => 'normal'],
        'mch'    => ['valor' => 31.30, 'unidad' => 'pg',        'referencia' => '27 a 34',    'flag' => 'normal'],
        'mchc'   => ['valor' => 39.20, 'unidad' => 'gr/dl',    'referencia' => '31 a 36',    'flag' => 'alto'],
        'rdw_cv' => ['valor' => 15.70, 'unidad' => 'fL',        'referencia' => '11 a 16',    'flag' => 'normal'],
        'rdw_sd' => ['valor' => 42.30, 'unidad' => 'fL',        'referencia' => '35 a 56',    'flag' => 'normal'],
        // Leucocitos
        'wbc'    => ['valor' => 11.00, 'unidad' => 'x10^6/L',  'referencia' => '4 a 10',     'flag' => 'alto'],
        'neu_pct'=> ['valor' => 75.60, 'unidad' => '%',         'referencia' => '45 a 70',    'flag' => 'alto'],
        'lym_pct'=> ['valor' => 18.20, 'unidad' => '%',         'referencia' => '25 a 45',    'flag' => 'bajo'],
        'mon_pct'=> ['valor' => 6.20,  'unidad' => '%',         'referencia' => '3 a 12',     'flag' => 'normal'],
        // Plaquetas
        'plt'    => ['valor' => 130.00,'unidad' => 'x10^3/uL', 'referencia' => '150 a 450',  'flag' => 'bajo'],
        'pct'    => ['valor' => 0.14,  'unidad' => '%',         'referencia' => '0.11 a 0.50','flag' => 'normal'],
        'mpv'    => ['valor' => 10.80, 'unidad' => 'fL',        'referencia' => '6.50 a 12',  'flag' => 'normal'],
        'pdw'    => ['valor' => 15.70, 'unidad' => 'fL',        'referencia' => '6.50 a 12',  'flag' => 'alto'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2025-06-09 05:26:00',
]);
echo "  ✔  Resultado 902210\n";

// ═══════════════════════════════════════════════════════════════════════════════
// SOL-REAL-002 — LOPÈZ ESPITIA VICTORIA
// Fleming S.A.S — No. Orden 6090027 — Sede: Puesto de salud 13 de junio
// Hemograma IV (NIHON KOHDEN) + Urocultivo y Antibiograma
// Bacterióloga: Yenifer Peñaranda Muñoz / María del Amparo Rada Hernández
// ═══════════════════════════════════════════════════════════════════════════════
echo PHP_EOL . "── SOL-REAL-002  López Espitia Victoria ──" . PHP_EOL;

upsertOrder($pdo, [
    'id_solicitud_key'    => 'SOL-REAL-002',
    'id_admision'         => 'ADM-6090027',
    'id_atencion'         => null,
    'tipo_documento'      => 'CC',
    'identificacion'      => '1073969620',
    'nombre_paciente'     => 'LOPÈZ ESPITIA VICTORIA',
    'sexo'                => 'F',
    'fecha_nacimiento'    => '2001-01-01',   // 24 años al 09-jun-2025
    'centro_salud'        => 'LABORATORIO CLINICO FLEMING S.A.S — Puesto de salud 13 de junio',
    'fecha_orden'         => '2025-06-09 08:28:00',
    'medico_ordena'       => 'CONSULTA EXTERNA',
    'numero_autorizacion' => null,
    'id_aliado'           => 'ALIADO-002',
    'fecha_envio'         => '2025-06-09 17:00:00',
    'porc_ejecucion'      => 100.00,
    'estado_orden'        => 'completed',
]);
echo "  ✔  Orden creada\n";

// ── Detalle 902210 ────────────────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-002',
    'id_admision'          => 'ADM-6090027',
    'cups'                 => '902210',
    'nombre_laboratorio'   => 'Hemograma IV Método Automático',
    'fecha_toma_muestra'   => '2025-06-09 08:28:00',
    'metodo'               => 'Método Automático',
    'reactivo'             => 'NIHON KOHDEN',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2025-06-09 17:00:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 902210\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-002',
    'cups'             => '902210',
    'values_json'      => json_encode([
        // Leucocitos
        'wbc'     => ['valor' => 10.87, 'unidad' => '10^9/L',  'referencia' => '4.00 - 10.00',  'flag' => 'alto'],
        'neu_abs' => ['valor' => 7.65,  'unidad' => '10^9/L',  'referencia' => '2.50 - 75.00',  'flag' => 'normal'],
        'lym_abs' => ['valor' => 2.47,  'unidad' => '10^9/L',  'referencia' => '0.80 - 4.80',   'flag' => 'normal'],
        'mon_abs' => ['valor' => 0.75,  'unidad' => '10^9/L',  'referencia' => '0.12 - 1.80',   'flag' => 'normal'],
        'neu_pct' => ['valor' => 70.4,  'unidad' => '%',        'referencia' => '50.0 - 70.0',   'flag' => 'alto'],
        'lym_pct' => ['valor' => 22.7,  'unidad' => '%',        'referencia' => '20.0 - 40.0',   'flag' => 'normal'],
        'mon_pct' => ['valor' => 6.9,   'unidad' => '%',        'referencia' => '3.0 - 12.0',    'flag' => 'normal'],
        // Serie roja
        'rbc'     => ['valor' => 3.43,  'unidad' => '10^12/L', 'referencia' => '3.50 - 5.50',   'flag' => 'bajo'],
        'hgb'     => ['valor' => 9.4,   'unidad' => 'g/dL',    'referencia' => '11.0 - 16.0',   'flag' => 'bajo'],
        'hct'     => ['valor' => 29.8,  'unidad' => '%',        'referencia' => '37.0 - 54.0',   'flag' => 'bajo'],
        'mcv'     => ['valor' => 87.0,  'unidad' => 'fL',       'referencia' => '80.0 - 100.0',  'flag' => 'normal'],
        'mch'     => ['valor' => 27.5,  'unidad' => 'pg',       'referencia' => '27.0 - 34.0',   'flag' => 'normal'],
        'mchc'    => ['valor' => 31.6,  'unidad' => 'g/dL',    'referencia' => '32.0 - 36.0',   'flag' => 'bajo'],
        'rdw_cv'  => ['valor' => 14.0,  'unidad' => '%',        'referencia' => '11.0 - 16.0',   'flag' => 'normal'],
        'rdw_sd'  => ['valor' => 39.9,  'unidad' => 'fL',       'referencia' => '35.0 - 56.0',   'flag' => 'normal'],
        // Plaquetas
        'plt'     => ['valor' => 297,   'unidad' => '10^9/L',  'referencia' => '150 - 450',      'flag' => 'normal'],
        'mpv'     => ['valor' => 8.8,   'unidad' => 'fL',       'referencia' => '6.5 - 12.0',    'flag' => 'normal'],
        'pdw'     => ['valor' => 14.3,  'unidad' => '',         'referencia' => '9.0 - 17.0',    'flag' => 'normal'],
        'pct'     => ['valor' => 0.260, 'unidad' => '%',        'referencia' => '0.108 - 0.282', 'flag' => 'normal'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2025-06-09 17:00:00',
]);
echo "  ✔  Resultado 902210\n";

// ── Detalle 901236 (Urocultivo) ───────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-002',
    'id_admision'          => 'ADM-6090027',
    'cups'                 => '901236',
    'nombre_laboratorio'   => 'Urocultivo [Antibiograma MIC Automático]',
    'fecha_toma_muestra'   => '2025-06-09 08:28:00',
    'metodo'               => 'Cultivo 48 horas',
    'reactivo'             => 'Ad-Bio',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2025-06-09 17:00:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000003',
]);
echo "  ✔  Detalle 901236\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-002',
    'cups'             => '901236',
    'values_json'      => json_encode([
        'tiempo_incubacion'  => ['valor' => '48 horas'],
        'gram_orina'         => ['valor' => 'No se observan microorganismos'],
        'bacteria_aislada'   => ['valor' => 'Negativo en 48 horas de incubación'],
        'resultado_cultivo'  => ['valor' => false, 'etiqueta' => 'Negativo'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2025-06-09 17:00:00',
]);
echo "  ✔  Resultado 901236\n";

// ═══════════════════════════════════════════════════════════════════════════════
// SOL-REAL-003 — RIVERO ARRIETA KELLY
// Fleming S.A.S — No. Orden 2190064 — Sede: Costa hermosa
// 13 exámenes — Bacteriólogas: Yenifer Peñaranda Muñoz / Katy De La Cruz González
//               / Trelira (firma ilegible)
// ═══════════════════════════════════════════════════════════════════════════════
echo PHP_EOL . "── SOL-REAL-003  Rivero Arrieta Kelly ──" . PHP_EOL;

upsertOrder($pdo, [
    'id_solicitud_key'    => 'SOL-REAL-003',
    'id_admision'         => 'ADM-2190064',
    'id_atencion'         => null,
    'tipo_documento'      => 'CC',
    'identificacion'      => '1101812961',
    'nombre_paciente'     => 'RIVERO ARRIETA KELLY',
    'sexo'                => 'F',
    'fecha_nacimiento'    => '2003-02-19',   // 23 años al 19-feb-2026
    'centro_salud'        => 'LABORATORIO CLINICO FLEMING S.A.S — Costa hermosa',
    'fecha_orden'         => '2026-02-19 09:33:00',
    'medico_ordena'       => 'CONSULTA EXTERNA',
    'numero_autorizacion' => null,
    'id_aliado'           => 'ALIADO-002',
    'fecha_envio'         => '2026-02-20 13:31:00',
    'porc_ejecucion'      => 100.00,
    'estado_orden'        => 'completed',
]);
echo "  ✔  Orden creada\n";

// ── 902210 Hemograma IV ───────────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '902210',
    'nombre_laboratorio'   => 'Hemograma IV Método Automático',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Método Automático',
    'reactivo'             => 'NIHON KOHDEN',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 902210\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '902210',
    'values_json'      => json_encode([
        'wbc'     => ['valor' => 10.78, 'unidad' => '10^9/L',  'referencia' => '4.00 - 10.00',  'flag' => 'alto'],
        'neu_abs' => ['valor' => 6.38,  'unidad' => '10^9/L',  'referencia' => '2.50 - 75.00',  'flag' => 'normal'],
        'lym_abs' => ['valor' => 4.29,  'unidad' => '10^9/L',  'referencia' => '0.80 - 4.80',   'flag' => 'normal'],
        'mon_abs' => ['valor' => 0.11,  'unidad' => '10^9/L',  'referencia' => '0.12 - 1.80',   'flag' => 'bajo'],
        'neu_pct' => ['valor' => 59.2,  'unidad' => '%',        'referencia' => '50.0 - 70.0',   'flag' => 'normal'],
        'lym_pct' => ['valor' => 39.8,  'unidad' => '%',        'referencia' => '20.0 - 40.0',   'flag' => 'normal'],
        'mon_pct' => ['valor' => 1.0,   'unidad' => '%',        'referencia' => '3.0 - 12.0',    'flag' => 'bajo'],
        'rbc'     => ['valor' => 4.38,  'unidad' => '10^12/L', 'referencia' => '3.50 - 5.50',   'flag' => 'normal'],
        'hgb'     => ['valor' => 12.6,  'unidad' => 'g/dL',    'referencia' => '11.0 - 16.0',   'flag' => 'normal'],
        'hct'     => ['valor' => 37.2,  'unidad' => '%',        'referencia' => '37.0 - 54.0',   'flag' => 'normal'],
        'mcv'     => ['valor' => 80.0,  'unidad' => 'fL',       'referencia' => '80.0 - 100.0',  'flag' => 'normal'],
        'mch'     => ['valor' => 28.7,  'unidad' => 'pg',       'referencia' => '27.0 - 34.0',   'flag' => 'normal'],
        'mchc'    => ['valor' => 35.9,  'unidad' => 'g/dL',    'referencia' => '32.0 - 36.0',   'flag' => 'normal'],
        'rdw_cv'  => ['valor' => 13.7,  'unidad' => '%',        'referencia' => '11.0 - 16.0',   'flag' => 'normal'],
        'rdw_sd'  => ['valor' => 32.9,  'unidad' => 'fL',       'referencia' => '35.0 - 56.0',   'flag' => 'bajo'],
        'plt'     => ['valor' => 231,   'unidad' => '10^9/L',  'referencia' => '150 - 450',      'flag' => 'normal'],
        'mpv'     => ['valor' => 12.4,  'unidad' => 'fL',       'referencia' => '6.5 - 12.0',    'flag' => 'alto'],
        'pdw'     => ['valor' => 19.4,  'unidad' => '',         'referencia' => '9.0 - 17.0',    'flag' => 'alto'],
        'pct'     => ['valor' => 0.290, 'unidad' => '%',        'referencia' => '0.108 - 0.282', 'flag' => 'alto'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 902210\n";

// ── 903818 Colesterol Total ───────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '903818',
    'nombre_laboratorio'   => 'Colesterol Total',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Método Enzimático',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 903818\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '903818',
    'values_json'      => json_encode([
        'col_total' => [
            'valor'       => 174,
            'unidad'      => 'mg/dL',
            'referencia'  => '0 - 200',
            'flag'        => 'normal',
            'interpretacion' => 'Valor deseable menor de 200 mg/dL',
        ],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 903818\n";

// ── 903868 Triglicéridos ──────────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '903868',
    'nombre_laboratorio'   => 'Triglicéridos',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Método Enzimático',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 903868\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '903868',
    'values_json'      => json_encode([
        'trigliceridos' => [
            'valor'          => 107,
            'unidad'         => 'mg/dL',
            'referencia'     => '0 - 150',
            'flag'           => 'normal',
            'interpretacion' => 'Valor óptimo menor de 150 mg/dL',
        ],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 903868\n";

// ── 903895 Creatinina en Suero ────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '903895',
    'nombre_laboratorio'   => 'Creatinina en Suero u Otros Fluidos',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Método Cinético',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 903895\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '903895',
    'values_json'      => json_encode([
        'creatinina' => ['valor' => 0.75, 'unidad' => 'mg/dL', 'referencia' => '0.60 - 1.20', 'flag' => 'normal'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 903895\n";

// ── 903841 Glucosa en Suero ───────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '903841',
    'nombre_laboratorio'   => 'Glucosa en Suero u Otro Fluido Diferente a Orina',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Método Enzimático',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 903841\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '903841',
    'values_json'      => json_encode([
        'glucosa' => ['valor' => 75, 'unidad' => 'mg/dL', 'referencia' => '70 - 110', 'flag' => 'normal'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 903841\n";

// ── 903815 Colesterol HDL ─────────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '903815',
    'nombre_laboratorio'   => 'Colesterol de Alta Densidad [HDL]',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Método Enzimático',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 903815\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '903815',
    'values_json'      => json_encode([
        'hdl' => [
            'valor'          => 64,
            'unidad'         => 'mg/dL',
            'referencia'     => '30 - 70',
            'flag'           => 'normal',
            'interpretacion' => 'Valor alto mayor de 60 mg/dL',
        ],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 903815\n";

// ── 903816 Colesterol LDL ─────────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '903816',
    'nombre_laboratorio'   => 'Colesterol de Baja Densidad [LDL] Enzimático',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Método Enzimático',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 903816\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '903816',
    'values_json'      => json_encode([
        'ldl' => [
            'valor'          => 89,
            'unidad'         => 'mg/dL',
            'referencia'     => '0 - 100',
            'flag'           => 'normal',
            'interpretacion' => 'Valor óptimo menor de 100 mg/dL',
        ],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 903816\n";

// ── 901304 Frotis Vaginal ─────────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '901304',
    'nombre_laboratorio'   => 'Examen Directo Fresco De Cualquier Muestra (Frotis vaginal)',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Examen directo fresco',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000002',
]);
echo "  ✔  Detalle 901304\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '901304',
    'values_json'      => json_encode([
        'aspecto_v'       => ['valor' => 'ligeramente turbio'],
        'ph_v'            => ['valor' => 7.0,       'referencia' => '3.8 - 4.5', 'flag' => 'alto'],
        'cel_epiteliales_v' => ['valor' => '3-6'],
        'celulas_guias'   => ['valor' => true,  'etiqueta' => 'Positivo'],
        'bacterias_v'     => ['valor' => '++'],
        'leucocitos_v'    => ['valor' => 'AUSENTE'],
        'micelios'        => ['valor' => 'No se observan'],
        'levaduras_v'     => ['valor' => 'No se observan'],
        'vaginosis'       => ['valor' => true,  'etiqueta' => 'Positivo'],
        'vaginitis'       => ['valor' => false, 'etiqueta' => 'Negativo'],
        'blastoconidias'  => ['valor' => 'No se observan'],
        'mobiluncus'      => ['valor' => true,  'etiqueta' => 'Positivo'],
        'trichomonas'     => ['valor' => 'No se observan'],
        'lactobacilos'    => ['valor' => 'AUSENTE'],
        'cocobacilos'     => ['valor' => 'Cocobacilos Gram Variables'],
        'tipo_gvaginalis' => ['valor' => '+++'],
        'reaccion_leuco'  => ['valor' => 'AUSENTE'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 901304\n";

// ── 906039 Treponema pallidum MHTPA ──────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '906039',
    'nombre_laboratorio'   => 'Treponema pallidum ANTICUERPOS (Prueba Treponémica) MHTPA',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'MHTPA',
    'reactivo'             => 'Biosistems',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000004',
]);
echo "  ✔  Detalle 906039\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '906039',
    'values_json'      => json_encode([
        'treponema_ac' => ['valor' => false, 'etiqueta' => 'NEGATIVO'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 906039\n";

// ── 901236 Urocultivo y Antibiograma ─────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '901236',
    'nombre_laboratorio'   => 'Urocultivo [Antibiograma MIC Automático]',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Cultivo 48 horas',
    'reactivo'             => 'Ad-Bio',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000005',
]);
echo "  ✔  Detalle 901236\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '901236',
    'values_json'      => json_encode([
        'tiempo_incubacion' => ['valor' => '48 horas'],
        'gram_orina'        => ['valor' => 'NO SE OBSERVAN GERMENES'],
        'bacteria_aislada'  => ['valor' => 'NEGATIVO A LAS 48 HORAS DE INCUBACION'],
        'resultado_cultivo' => ['valor' => false, 'etiqueta' => 'Negativo'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 901236\n";

// ── 907106 Uroanalisis ────────────────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '907106',
    'nombre_laboratorio'   => 'Uroanalisis',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Tira reactiva + microscopía',
    'reactivo'             => 'Mission',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000005',
]);
echo "  ✔  Detalle 907106\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '907106',
    'values_json'      => json_encode([
        // Tira reactiva
        'color'          => ['valor' => 'AMARILLO'],
        'aspecto'        => ['valor' => 'LIG TURBIO'],
        'densidad'       => ['valor' => 1020,   'referencia' => '1005 - 1030', 'flag' => 'normal'],
        'ph'             => ['valor' => 5.0,    'referencia' => '5.0 - 8.0',   'flag' => 'normal'],
        'proteinas'      => ['valor' => 'NEGATIVO', 'unidad' => 'mg/dL'],
        'sangre'         => ['valor' => 'NEGATIVO', 'unidad' => 'Cel/ul'],
        'nitritos'       => ['valor' => false,  'etiqueta' => 'NEGATIVO'],
        'leucocitos_e'   => ['valor' => 'NEGATIVO', 'unidad' => 'Cel/ul'],
        'glucosa_o'      => ['valor' => 'NEGATIVO', 'unidad' => 'mg/dL'],
        'cetonas'        => ['valor' => 'NEGATIVO', 'unidad' => 'mg/dL'],
        'bilirrubina'    => ['valor' => 'NEGATIVO', 'unidad' => 'g/dL'],
        'urobilinogeno'  => ['valor' => 'NEGATIVO', 'unidad' => 'UE/dL'],
        // Microscopía
        'cel_epiteliales' => ['valor' => '+'],
        'leucocitos_m'    => ['valor' => '2-4'],
        'hematies_m'      => ['valor' => '1-3'],
        'moco'            => ['valor' => '+'],
        'bacterias_o'     => ['valor' => '+++'],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 907106\n";

// ── 906249 VIH 1 y 2 Anticuerpos ─────────────────────────────────────────────
insertDetail($pdo, [
    'id_solicitud_key'     => 'SOL-REAL-003',
    'id_admision'          => 'ADM-2190064',
    'cups'                 => '906249',
    'nombre_laboratorio'   => 'VIH 1 y 2 Anticuerpos',
    'fecha_toma_muestra'   => '2026-02-19 09:33:00',
    'metodo'               => 'Inmunocromatografía',
    'reactivo'             => 'Onsite',
    'invima'               => null,
    'estado_resultado'     => 'FINAL',
    'fecha_resultado'      => '2026-02-20 13:31:00',
    'tipo_id_bacteriologo' => 'CC',
    'id_bacteriologo'      => '00000004',
]);
echo "  ✔  Detalle 906249\n";

insertResult($pdo, [
    'id_solicitud_key' => 'SOL-REAL-003',
    'cups'             => '906249',
    'values_json'      => json_encode([
        'vih_ac' => [
            'valor'   => false,
            'etiqueta'=> 'PRESUNTIVAMENTE NO REACTIVO',
            'nota'    => 'Esta prueba mide los anticuerpos de los virus HIV-1 y HIV-2. Ante la sospecha clínica o contacto reciente y un resultado no reactivo es necesario realizar una nueva prueba en dos meses.',
        ],
    ], JSON_UNESCAPED_UNICODE),
    'attachment_path' => null,
    'received_at'     => '2026-02-20 13:31:00',
]);
echo "  ✔  Resultado 906249\n";

// ── Resumen ───────────────────────────────────────────────────────────────────
echo PHP_EOL . "── Resumen ──────────────────────────────────────────────────" . PHP_EOL;
echo "  Órdenes reales : " . $pdo->query("SELECT COUNT(*) FROM lab_orders WHERE id_solicitud_key LIKE 'SOL-REAL-%'")->fetchColumn() . PHP_EOL;
echo "  Detalles reales: " . $pdo->query("SELECT COUNT(*) FROM lab_order_details WHERE id_solicitud_key LIKE 'SOL-REAL-%'")->fetchColumn() . PHP_EOL;
echo "  Resultados reales: " . $pdo->query("SELECT COUNT(*) FROM lab_results WHERE id_solicitud_key LIKE 'SOL-REAL-%'")->fetchColumn() . PHP_EOL;

echo PHP_EOL . "Órdenes de prueba disponibles:" . PHP_EOL;
echo "  SOL-REAL-001  CC 30669686   BALLESTEROS MARTINEZ LILIANA PAOLA  (Hemograma IV — MINDRAY)" . PHP_EOL;
echo "  SOL-REAL-002  CC 1073969620 LOPÈZ ESPITIA VICTORIA              (Hemograma IV + Urocultivo)" . PHP_EOL;
echo "  SOL-REAL-003  CC 1101812961 RIVERO ARRIETA KELLY                (13 exámenes — perfil completo)" . PHP_EOL;
echo PHP_EOL . "✅  Seed de resultados reales completado." . PHP_EOL;
