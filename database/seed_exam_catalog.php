<?php

/**
 * Seed del catálogo de exámenes y parámetros
 *
 * Carga exam_types y exam_parameters para los 10 CUPS usados en las órdenes de prueba.
 *
 * Uso:
 *   php database/seed_exam_catalog.php
 */

declare(strict_types=1);

// ── Entorno ───────────────────────────────────────────────────────────────────
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (getenv(trim($k)) === false) {
            putenv(trim($k) . '=' . trim($v));
        }
    }
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'localhost',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'clinical_lab'
    ),
    getenv('DB_USERNAME') ?: 'api_user',
    getenv('DB_PASSWORD') ?: 'secret',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "✅  Conectado" . PHP_EOL;

// ── Helpers ───────────────────────────────────────────────────────────────────
function upsertType(PDO $pdo, string $cups, string $nombre, ?string $desc = null): void
{
    $pdo->prepare(
        'INSERT INTO exam_types (cups, nombre, descripcion, activo)
         VALUES (:cups, :nombre, :desc, 1)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion)'
    )->execute(['cups' => $cups, 'nombre' => $nombre, 'desc' => $desc]);
}

function upsertParam(PDO $pdo, array $p): void
{
    $pdo->prepare(
        'INSERT INTO exam_parameters
            (cups, codigo, nombre, unidad, valor_min_ref, valor_max_ref,
             sexo, edad_min, edad_max, obligatorio, orden, activo)
         VALUES
            (:cups,:codigo,:nombre,:unidad,:min,:max,:sexo,:emin,:emax,:oblig,:orden,1)
         ON DUPLICATE KEY UPDATE
            nombre        = VALUES(nombre),
            unidad        = VALUES(unidad),
            valor_min_ref = VALUES(valor_min_ref),
            valor_max_ref = VALUES(valor_max_ref),
            obligatorio   = VALUES(obligatorio),
            orden         = VALUES(orden),
            activo        = 1'
    )->execute([
        'cups'   => $p['cups'],
        'codigo' => $p['codigo'],
        'nombre' => $p['nombre'],
        'unidad' => $p['unidad'] ?? null,
        'min'    => $p['min']    ?? null,
        'max'    => $p['max']    ?? null,
        'sexo'   => $p['sexo']   ?? '*',
        'emin'   => $p['emin']   ?? null,
        'emax'   => $p['emax']   ?? null,
        'oblig'  => $p['oblig']  ?? 0,
        'orden'  => $p['orden']  ?? 0,
    ]);
}

// ── Catálogo ──────────────────────────────────────────────────────────────────
// IMPORTANTE: las claves se declaran como asignaciones separadas para que PHP
// las trate siempre como string y no las convierta a int.

$catalog = [];

// ── 903820 Hemograma Completo ─────────────────────────────────────────────────
$catalog['903820'] = [
    'nombre' => 'Hemograma Completo',
    'desc'   => 'CBC – Complete Blood Count con diferencial leucocitario e índices eritrocitarios',
    'params' => [
        // Core (obligatorios)
        ['codigo' => 'wbc',     'nombre' => 'Leucocitos',                          'unidad' => '10³/µL', 'min' => 4.5,   'max' => 11.0,  'oblig' => 1, 'orden' => 1],
        ['codigo' => 'rbc',     'nombre' => 'Eritrocitos',                         'unidad' => '10⁶/µL', 'min' => 4.5,   'max' => 5.9,   'sexo' => 'M', 'oblig' => 1, 'orden' => 2],
        ['codigo' => 'rbc',     'nombre' => 'Eritrocitos',                         'unidad' => '10⁶/µL', 'min' => 4.0,   'max' => 5.2,   'sexo' => 'F', 'oblig' => 1, 'orden' => 2],
        ['codigo' => 'hb',      'nombre' => 'Hemoglobina',                         'unidad' => 'g/dL',   'min' => 13.5,  'max' => 17.5,  'sexo' => 'M', 'oblig' => 1, 'orden' => 3],
        ['codigo' => 'hb',      'nombre' => 'Hemoglobina',                         'unidad' => 'g/dL',   'min' => 12.0,  'max' => 16.0,  'sexo' => 'F', 'oblig' => 1, 'orden' => 3],
        ['codigo' => 'hct',     'nombre' => 'Hematocrito',                         'unidad' => '%',      'min' => 41.0,  'max' => 53.0,  'sexo' => 'M', 'oblig' => 1, 'orden' => 4],
        ['codigo' => 'hct',     'nombre' => 'Hematocrito',                         'unidad' => '%',      'min' => 36.0,  'max' => 46.0,  'sexo' => 'F', 'oblig' => 1, 'orden' => 4],
        ['codigo' => 'plt',     'nombre' => 'Plaquetas',                           'unidad' => '10³/µL', 'min' => 150.0, 'max' => 400.0, 'oblig' => 1, 'orden' => 5],
        // Índices eritrocitarios
        ['codigo' => 'vcm',     'nombre' => 'VCM (Volumen Corpuscular Medio)',     'unidad' => 'fL',     'min' => 80.0,  'max' => 100.0, 'orden' => 6],
        ['codigo' => 'hcm',     'nombre' => 'HCM (Hemoglobina Corpuscular Media)', 'unidad' => 'pg',     'min' => 27.0,  'max' => 33.0,  'orden' => 7],
        ['codigo' => 'chcm',    'nombre' => 'CHCM (Concentración Hb Corpuscular)', 'unidad' => 'g/dL',   'min' => 32.0,  'max' => 36.0,  'orden' => 8],
        ['codigo' => 'rdw',     'nombre' => 'RDW (Amplitud Distribución Eritrocitos)', 'unidad' => '%',  'min' => 11.5,  'max' => 14.5,  'orden' => 9],
        // Diferencial leucocitario (%)
        ['codigo' => 'neu_pct', 'nombre' => 'Neutrófilos %',                       'unidad' => '%',      'min' => 50.0,  'max' => 70.0,  'orden' => 10],
        ['codigo' => 'lin_pct', 'nombre' => 'Linfocitos %',                        'unidad' => '%',      'min' => 20.0,  'max' => 40.0,  'orden' => 11],
        ['codigo' => 'mon_pct', 'nombre' => 'Monocitos %',                         'unidad' => '%',      'min' => 2.0,   'max' => 8.0,   'orden' => 12],
        ['codigo' => 'eos_pct', 'nombre' => 'Eosinófilos %',                       'unidad' => '%',      'min' => 1.0,   'max' => 4.0,   'orden' => 13],
        ['codigo' => 'bas_pct', 'nombre' => 'Basófilos %',                         'unidad' => '%',      'min' => 0.0,   'max' => 1.0,   'orden' => 14],
        // Avanzado / automatizado
        ['codigo' => 'mpv',     'nombre' => 'MPV (Volumen Plaquetario Medio)',     'unidad' => 'fL',     'min' => 7.5,   'max' => 12.5,  'orden' => 15],
        ['codigo' => 'ret',     'nombre' => 'Reticulocitos %',                     'unidad' => '%',      'min' => 0.5,   'max' => 2.5,   'orden' => 16],
    ],
];

// ── 904010 Glucosa en Ayunas ──────────────────────────────────────────────────
$catalog['904010'] = [
    'nombre' => 'Glucosa en Ayunas',
    'params' => [
        ['codigo' => 'glucosa', 'nombre' => 'Glucosa', 'unidad' => 'mg/dL', 'min' => 70.0, 'max' => 100.0, 'oblig' => 1, 'orden' => 1],
    ],
];

// ── 904855 Perfil Lipídico ────────────────────────────────────────────────────
$catalog['904855'] = [
    'nombre' => 'Perfil Lipídico',
    'params' => [
        ['codigo' => 'col_total',     'nombre' => 'Colesterol Total', 'unidad' => 'mg/dL', 'max' => 200.0, 'oblig' => 1, 'orden' => 1],
        ['codigo' => 'hdl',           'nombre' => 'HDL Colesterol',   'unidad' => 'mg/dL', 'min' => 40.0,  'oblig' => 1, 'orden' => 2],
        ['codigo' => 'ldl',           'nombre' => 'LDL Colesterol',   'unidad' => 'mg/dL', 'max' => 130.0, 'oblig' => 1, 'orden' => 3],
        ['codigo' => 'trigliceridos', 'nombre' => 'Triglicéridos',    'unidad' => 'mg/dL', 'max' => 150.0, 'oblig' => 1, 'orden' => 4],
        ['codigo' => 'vldl',          'nombre' => 'VLDL Colesterol',  'unidad' => 'mg/dL', 'max' => 30.0,               'orden' => 5],
    ],
];

// ── 904030 Creatinina Sérica ──────────────────────────────────────────────────
$catalog['904030'] = [
    'nombre' => 'Creatinina Sérica',
    'params' => [
        ['codigo' => 'creatinina', 'nombre' => 'Creatinina', 'unidad' => 'mg/dL', 'min' => 0.7, 'max' => 1.2, 'sexo' => 'M', 'oblig' => 1, 'orden' => 1],
        ['codigo' => 'creatinina', 'nombre' => 'Creatinina', 'unidad' => 'mg/dL', 'min' => 0.5, 'max' => 1.0, 'sexo' => 'F', 'oblig' => 1, 'orden' => 1],
    ],
];

// ── 904040 Urea / BUN ─────────────────────────────────────────────────────────
$catalog['904040'] = [
    'nombre' => 'Urea / BUN',
    'params' => [
        ['codigo' => 'urea', 'nombre' => 'Urea', 'unidad' => 'mg/dL', 'min' => 15.0, 'max' => 45.0, 'oblig' => 1, 'orden' => 1],
        ['codigo' => 'bun',  'nombre' => 'BUN',  'unidad' => 'mg/dL', 'min' => 7.0,  'max' => 20.0, 'oblig' => 1, 'orden' => 2],
    ],
];

// ── 904100 TSH ────────────────────────────────────────────────────────────────
$catalog['904100'] = [
    'nombre' => 'TSH (Hormona Estimulante de Tiroides)',
    'params' => [
        ['codigo' => 'tsh', 'nombre' => 'TSH', 'unidad' => 'mUI/L', 'min' => 0.4, 'max' => 4.0, 'oblig' => 1, 'orden' => 1],
    ],
];

// ── 904110 T4 Libre ───────────────────────────────────────────────────────────
$catalog['904110'] = [
    'nombre' => 'T4 Libre',
    'params' => [
        ['codigo' => 't4_libre', 'nombre' => 'T4 Libre', 'unidad' => 'ng/dL', 'min' => 0.8, 'max' => 1.8, 'oblig' => 1, 'orden' => 1],
    ],
];

// ── 904200 Parcial de Orina ───────────────────────────────────────────────────
$catalog['904200'] = [
    'nombre' => 'Parcial de Orina',
    'params' => [
        ['codigo' => 'ph',        'nombre' => 'pH',                  'unidad' => '',       'min' => 5.0,   'max' => 8.0,   'oblig' => 1, 'orden' => 1],
        ['codigo' => 'densidad',  'nombre' => 'Densidad',            'unidad' => '',       'min' => 1.005, 'max' => 1.030, 'oblig' => 1, 'orden' => 2],
        ['codigo' => 'proteinas', 'nombre' => 'Proteínas',           'unidad' => 'mg/dL',  'max' => 14.0,                              'orden' => 3],
        ['codigo' => 'glucosa_o', 'nombre' => 'Glucosa (orina)',     'unidad' => 'mg/dL',  'max' => 0.0,                               'orden' => 4],
        ['codigo' => 'leu_campo', 'nombre' => 'Leucocitos/campo',    'unidad' => '/campo',  'max' => 5.0,                               'orden' => 5],
        ['codigo' => 'hem_campo', 'nombre' => 'Hematíes/campo',      'unidad' => '/campo',  'max' => 3.0,                               'orden' => 6],
    ],
];

// ── 904300 HbA1c ──────────────────────────────────────────────────────────────
$catalog['904300'] = [
    'nombre' => 'Hemoglobina Glicosilada HbA1c',
    'params' => [
        ['codigo' => 'hba1c', 'nombre' => 'HbA1c', 'unidad' => '%', 'max' => 5.7, 'oblig' => 1, 'orden' => 1],
    ],
];

// ── 904400 Proteína C Reactiva ────────────────────────────────────────────────
$catalog['904400'] = [
    'nombre' => 'Proteína C Reactiva (PCR)',
    'params' => [
        ['codigo' => 'pcr', 'nombre' => 'PCR', 'unidad' => 'mg/L', 'max' => 1.0, 'oblig' => 1, 'orden' => 1],
    ],
];

// ── Insertar ──────────────────────────────────────────────────────────────────
echo PHP_EOL . "── Tipos de examen ──" . PHP_EOL;
foreach ($catalog as $cups => $def) {
    upsertType($pdo, (string) $cups, $def['nombre'], $def['desc'] ?? null);
    echo "  ✔  {$cups}  {$def['nombre']}" . PHP_EOL;
}

echo PHP_EOL . "── Parámetros ──" . PHP_EOL;
foreach ($catalog as $cups => $def) {
    foreach ($def['params'] as $p) {
        $p['cups'] = (string) $cups;
        upsertParam($pdo, $p);
    }
    echo "  ✔  {$cups}  " . count($def['params']) . " parámetro(s)" . PHP_EOL;
}

echo PHP_EOL . "── Resumen ──" . PHP_EOL;
echo "  Tipos de examen : " . $pdo->query("SELECT COUNT(*) FROM exam_types")->fetchColumn()      . PHP_EOL;
echo "  Parámetros      : " . $pdo->query("SELECT COUNT(*) FROM exam_parameters")->fetchColumn() . PHP_EOL;
echo PHP_EOL . "✅  Catálogo cargado." . PHP_EOL;
