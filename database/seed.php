<?php

/**
 * Seed script — datos de prueba
 *
 * Crea:
 *   - 2 aliados
 *   - 1 usuario admin
 *   - 1 usuario lab_operator
 *   - 2 usuarios aliado_operator (uno por aliado)
 *   - 1 usuario viewer
 *
 *   ALIADO-001 (visible por admin y aliado_norte):
 *     - SOL-2025-0001  completed  con resultados
 *     - SOL-2025-0003  completed  con resultados
 *     - SOL-2025-0006  completed  con resultados
 *     - SOL-2025-0004  sent       sin resultados
 *     - SOL-2025-0007  pending    sin resultados
 *
 *   ALIADO-002 (visible por admin y aliado_sur):
 *     - SOL-2025-0002  completed  con resultados
 *     - SOL-2025-0008  completed  con resultados
 *     - SOL-2025-0009  completed  con resultados
 *     - SOL-2025-0010  completed  con resultados
 *     - SOL-2025-0005  sent       sin resultados
 *     - SOL-2025-0011  pending    sin resultados
 *     - SOL-2025-0012  pending    sin resultados
 *
 * Uso:
 *   php database/seed.php
 *
 * Variables de entorno leídas desde .env en la raíz del proyecto.
 */

declare(strict_types=1);

// ── Variables de entorno: primero el sistema (Docker), luego .env como fallback
// Las variables ya inyectadas por Docker/sistema tienen prioridad.
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        // Solo setear si NO está ya definida en el entorno del sistema
        if (getenv($key) === false) {
            putenv($key . '=' . trim($value));
        }
    }
}

// ── Conexión PDO ─────────────────────────────────────────────────────────────
$host   = getenv('DB_HOST')     ?: 'localhost';
$port   = getenv('DB_PORT')     ?: '3306';
$dbName = getenv('DB_NAME')     ?: 'clinical_lab';
$user   = getenv('DB_USERNAME') ?: 'api_user';
$pass   = getenv('DB_PASSWORD') ?: 'secret';

$dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo "❌  No se pudo conectar a la base de datos: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "✅  Conectado a {$dbName}@{$host}" . PHP_EOL;

// ── Helpers ───────────────────────────────────────────────────────────────────
function insert(PDO $pdo, string $table, array $data): void
{
    $cols        = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
    $stmt = $pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})");
    $stmt->execute($data);
}

function upsertOrder(PDO $pdo, array $data): void
{
    $cols         = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
    $updates      = implode(', ', array_map(fn($k) => "{$k} = VALUES({$k})", array_keys($data)));
    $stmt = $pdo->prepare(
        "INSERT INTO lab_orders ({$cols}) VALUES ({$placeholders})
         ON DUPLICATE KEY UPDATE {$updates}"
    );
    $stmt->execute($data);
}

// ── 1. Aliados ────────────────────────────────────────────────────────────────
echo PHP_EOL . "── Aliados ──" . PHP_EOL;

$aliados = [
    ['id' => 'ALIADO-001', 'nombre' => 'Laboratorio Clínico Norte',   'activo' => 1],
    ['id' => 'ALIADO-002', 'nombre' => 'Laboratorio Clínico Sur',     'activo' => 1],
];

foreach ($aliados as $aliado) {
    $exists = $pdo->query("SELECT id FROM aliados WHERE id = '{$aliado['id']}'")->fetchColumn();
    if ($exists) {
        echo "  ⚠️  Aliado {$aliado['id']} ya existe, omitido." . PHP_EOL;
        continue;
    }
    insert($pdo, 'aliados', $aliado);
    echo "  ✔  Aliado creado: {$aliado['id']} — {$aliado['nombre']}" . PHP_EOL;
}

// ── 2. Roles (deben existir) ──────────────────────────────────────────────────
$roleMap = [];
foreach ($pdo->query("SELECT id, name FROM roles") as $row) {
    $roleMap[$row['name']] = (int) $row['id'];
}

if (empty($roleMap)) {
    echo PHP_EOL . "❌  La tabla 'roles' está vacía. Ejecuta schema_auth.sql primero." . PHP_EOL;
    exit(1);
}

// ── 3. Usuarios ───────────────────────────────────────────────────────────────
echo PHP_EOL . "── Usuarios ──" . PHP_EOL;

/**
 * password = nombre_usuario + "123"  (ej. admin → admin123)
 * Todos los hashes se generan con password_hash(PASSWORD_BCRYPT).
 */
$users = [
    [
        'username' => 'admin',
        'email'    => 'admin@clinicallab.local',
        'password' => 'admin123',
        'role'     => 'admin',
        'aliados'  => [],
    ],
    [
        'username' => 'lab_op',
        'email'    => 'lab_op@clinicallab.local',
        'password' => 'lab_op123',
        'role'     => 'lab_operator',
        'aliados'  => [],
    ],
    [
        'username' => 'aliado_norte',
        'email'    => 'aliado_norte@clinicallab.local',
        'password' => 'aliado_norte123',
        'role'     => 'aliado_operator',
        'aliados'  => ['ALIADO-001'],
    ],
    [
        'username' => 'aliado_sur',
        'email'    => 'aliado_sur@clinicallab.local',
        'password' => 'aliado_sur123',
        'role'     => 'aliado_operator',
        'aliados'  => ['ALIADO-002'],
    ],
    [
        'username' => 'viewer',
        'email'    => 'viewer@clinicallab.local',
        'password' => 'viewer123',
        'role'     => 'viewer',
        'aliados'  => [],
    ],
];

foreach ($users as $u) {
    $exists = $pdo->query("SELECT id FROM users WHERE username = '{$u['username']}'")->fetchColumn();
    if ($exists) {
        echo "  ⚠️  Usuario '{$u['username']}' ya existe, omitido." . PHP_EOL;
        continue;
    }

    $roleId = $roleMap[$u['role']] ?? null;
    if ($roleId === null) {
        echo "  ❌  Rol '{$u['role']}' no encontrado para usuario '{$u['username']}'." . PHP_EOL;
        continue;
    }

    insert($pdo, 'users', [
        'username'      => $u['username'],
        'email'         => $u['email'],
        'password_hash' => password_hash($u['password'], PASSWORD_BCRYPT),
        'role_id'       => $roleId,
        'activo'        => 1,
    ]);

    $userId = (int) $pdo->lastInsertId();

    foreach ($u['aliados'] as $aliadoId) {
        insert($pdo, 'user_aliado', ['user_id' => $userId, 'aliado_id' => $aliadoId]);
    }

    $aliadosStr = empty($u['aliados']) ? '—' : implode(', ', $u['aliados']);
    echo "  ✔  Usuario creado: {$u['username']} [{$u['role']}]  aliados: {$aliadosStr}  pass: {$u['password']}" . PHP_EOL;
}

// ── 4. Órdenes ────────────────────────────────────────────────────────────────
echo PHP_EOL . "── Órdenes ──" . PHP_EOL;

/**
 * Estructura de cada orden de prueba:
 *   - id_solicitud_key  → clave única
 *   - details           → array de exámenes (CUPS)
 *   - results           → array de resultados (null = sin resultado)
 */
$orders = [
    // ── Orden 1: completed, con resultados ──────────────────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0001',
            'id_admision'         => 'ADM-10001',
            'id_atencion'         => 'ATE-20001',
            'tipo_documento'      => 'CC',
            'identificacion'      => '1020304050',
            'nombre_paciente'     => 'Carlos Andrés Pérez López',
            'sexo'                => 'M',
            'fecha_nacimiento'    => '1985-03-15',
            'centro_salud'        => 'Clínica Norte S.A.',
            'fecha_orden'         => '2025-04-10 08:30:00',
            'medico_ordena'       => 'Dr. Juan Rodríguez',
            'numero_autorizacion' => 'AUTH-001',
            'id_aliado'           => 'ALIADO-001',
            'fecha_envio'         => '2025-04-10 09:00:00',
            'porc_ejecucion'      => 100.00,
            'estado_orden'        => 'completed',
        ],
        'details' => [
            [
                'cups'                       => '903820',
                'nombre_laboratorio'         => 'Hemograma Completo',
                'fecha_toma_muestra'         => '2025-04-10 09:15:00',
                'metodo'                     => 'Automatizado',
                'reactivo'                   => 'Sysmex XN-1000',
                'invima'                     => 'INVIMA2020M-0001234',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-10 14:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '52001234',
            ],
            [
                'cups'                       => '904010',
                'nombre_laboratorio'         => 'Glucosa en Ayunas',
                'fecha_toma_muestra'         => '2025-04-10 09:15:00',
                'metodo'                     => 'Enzimático colorimétrico',
                'reactivo'                   => 'Roche Cobas',
                'invima'                     => 'INVIMA2021M-0005678',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-10 13:30:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '52001234',
            ],
        ],
        'results' => [
            [
                'cups'        => '903820',
                'values_json' => json_encode([
                    'leucocitos'   => ['valor' => '7.2',  'unidad' => '10³/µL', 'referencia' => '4.5-11.0'],
                    'eritrocitos'  => ['valor' => '4.8',  'unidad' => '10⁶/µL', 'referencia' => '4.5-5.9'],
                    'hemoglobina'  => ['valor' => '14.5', 'unidad' => 'g/dL',   'referencia' => '13.5-17.5'],
                    'hematocrito'  => ['valor' => '43.2', 'unidad' => '%',       'referencia' => '41-53'],
                    'plaquetas'    => ['valor' => '250',  'unidad' => '10³/µL', 'referencia' => '150-400'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-10 14:05:00',
            ],
            [
                'cups'        => '904010',
                'values_json' => json_encode([
                    'glucosa' => ['valor' => '92', 'unidad' => 'mg/dL', 'referencia' => '70-100'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-10 13:35:00',
            ],
        ],
    ],

    // ── Orden 2: completed, con resultados ──────────────────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0002',
            'id_admision'         => 'ADM-10002',
            'id_atencion'         => null,
            'tipo_documento'      => 'TI',
            'identificacion'      => '1098765432',
            'nombre_paciente'     => 'María Fernanda Gómez Ruiz',
            'sexo'                => 'F',
            'fecha_nacimiento'    => '2001-07-22',
            'centro_salud'        => 'Centro Médico Sur',
            'fecha_orden'         => '2025-04-15 10:00:00',
            'medico_ordena'       => 'Dra. Ana Martínez',
            'numero_autorizacion' => 'AUTH-002',
            'id_aliado'           => 'ALIADO-002',
            'fecha_envio'         => '2025-04-15 10:30:00',
            'porc_ejecucion'      => 100.00,
            'estado_orden'        => 'completed',
        ],
        'details' => [
            [
                'cups'                       => '904855',
                'nombre_laboratorio'         => 'Perfil Lipídico',
                'fecha_toma_muestra'         => '2025-04-15 10:45:00',
                'metodo'                     => 'Enzimático',
                'reactivo'                   => 'Abbott Architect',
                'invima'                     => 'INVIMA2022M-0009012',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-15 16:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '79345678',
            ],
        ],
        'results' => [
            [
                'cups'        => '904855',
                'values_json' => json_encode([
                    'colesterol_total' => ['valor' => '185', 'unidad' => 'mg/dL', 'referencia' => '<200'],
                    'hdl'              => ['valor' => '55',  'unidad' => 'mg/dL', 'referencia' => '>40'],
                    'ldl'              => ['valor' => '110', 'unidad' => 'mg/dL', 'referencia' => '<130'],
                    'trigliceridos'    => ['valor' => '100', 'unidad' => 'mg/dL', 'referencia' => '<150'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-15 16:05:00',
            ],
        ],
    ],

    // ── Orden 3: completed, con resultados ──────────────────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0003',
            'id_admision'         => 'ADM-10003',
            'id_atencion'         => 'ATE-20003',
            'tipo_documento'      => 'CC',
            'identificacion'      => '3344556677',
            'nombre_paciente'     => 'Luis Eduardo Torres Vargas',
            'sexo'                => 'M',
            'fecha_nacimiento'    => '1972-11-05',
            'centro_salud'        => 'Hospital Central',
            'fecha_orden'         => '2025-04-20 07:00:00',
            'medico_ordena'       => 'Dr. Pedro Sánchez',
            'numero_autorizacion' => null,
            'id_aliado'           => 'ALIADO-001',
            'fecha_envio'         => '2025-04-20 07:45:00',
            'porc_ejecucion'      => 100.00,
            'estado_orden'        => 'completed',
        ],
        'details' => [
            [
                'cups'                       => '904030',
                'nombre_laboratorio'         => 'Creatinina Sérica',
                'fecha_toma_muestra'         => '2025-04-20 08:00:00',
                'metodo'                     => 'Jaffé cinético',
                'reactivo'                   => 'Siemens Dimension',
                'invima'                     => 'INVIMA2020M-0003456',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-20 12:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '52001234',
            ],
            [
                'cups'                       => '904040',
                'nombre_laboratorio'         => 'Urea / BUN',
                'fecha_toma_muestra'         => '2025-04-20 08:00:00',
                'metodo'                     => 'Ureasa UV',
                'reactivo'                   => 'Siemens Dimension',
                'invima'                     => 'INVIMA2021M-0007890',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-20 12:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '52001234',
            ],
        ],
        'results' => [
            [
                'cups'        => '904030',
                'values_json' => json_encode([
                    'creatinina' => ['valor' => '0.9', 'unidad' => 'mg/dL', 'referencia' => '0.7-1.2'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-20 12:05:00',
            ],
            [
                'cups'        => '904040',
                'values_json' => json_encode([
                    'urea' => ['valor' => '28', 'unidad' => 'mg/dL', 'referencia' => '15-45'],
                    'bun'  => ['valor' => '13', 'unidad' => 'mg/dL', 'referencia' => '7-20'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-20 12:05:00',
            ],
        ],
    ],

    // ── Orden 4: sent, SIN resultados — ALIADO-001 ───────────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0004',
            'id_admision'         => 'ADM-10004',
            'id_atencion'         => null,
            'tipo_documento'      => 'CC',
            'identificacion'      => '9988776655',
            'nombre_paciente'     => 'Sandra Milena Castro Herrera',
            'sexo'                => 'F',
            'fecha_nacimiento'    => '1990-06-18',
            'centro_salud'        => 'Clínica Norte S.A.',
            'fecha_orden'         => '2025-04-28 09:00:00',
            'medico_ordena'       => 'Dr. Juan Rodríguez',
            'numero_autorizacion' => 'AUTH-004',
            'id_aliado'           => 'ALIADO-001',
            'fecha_envio'         => '2025-04-28 09:30:00',
            'porc_ejecucion'      => 0.00,
            'estado_orden'        => 'sent',
        ],
        'details' => [
            [
                'cups'                       => '903820',
                'nombre_laboratorio'         => 'Hemograma Completo',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
            [
                'cups'                       => '904010',
                'nombre_laboratorio'         => 'Glucosa en Ayunas',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
        ],
        'results' => [],   // sin resultados
    ],

    // ── Orden 5: sent, SIN resultados — ALIADO-002 ───────────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0005',
            'id_admision'         => 'ADM-10005',
            'id_atencion'         => 'ATE-20005',
            'tipo_documento'      => 'PA',
            'identificacion'      => 'AB123456',
            'nombre_paciente'     => 'John Michael Smith',
            'sexo'                => 'M',
            'fecha_nacimiento'    => '1978-09-30',
            'centro_salud'        => 'Centro Médico Sur',
            'fecha_orden'         => '2025-04-30 11:00:00',
            'medico_ordena'       => 'Dra. Ana Martínez',
            'numero_autorizacion' => null,
            'id_aliado'           => 'ALIADO-002',
            'fecha_envio'         => '2025-04-30 11:30:00',
            'porc_ejecucion'      => 0.00,
            'estado_orden'        => 'sent',
        ],
        'details' => [
            [
                'cups'                       => '904855',
                'nombre_laboratorio'         => 'Perfil Lipídico',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
        ],
        'results' => [],
    ],

    // ── Orden 6: completed, con resultados — ALIADO-001 ──────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0006',
            'id_admision'         => 'ADM-10006',
            'id_atencion'         => 'ATE-20006',
            'tipo_documento'      => 'CC',
            'identificacion'      => '7766554433',
            'nombre_paciente'     => 'Patricia Elena Morales Díaz',
            'sexo'                => 'F',
            'fecha_nacimiento'    => '1968-02-14',
            'centro_salud'        => 'Clínica Norte S.A.',
            'fecha_orden'         => '2025-04-22 08:00:00',
            'medico_ordena'       => 'Dr. Juan Rodríguez',
            'numero_autorizacion' => 'AUTH-006',
            'id_aliado'           => 'ALIADO-001',
            'fecha_envio'         => '2025-04-22 08:30:00',
            'porc_ejecucion'      => 100.00,
            'estado_orden'        => 'completed',
        ],
        'details' => [
            [
                'cups'                       => '904100',
                'nombre_laboratorio'         => 'TSH (Hormona Estimulante de Tiroides)',
                'fecha_toma_muestra'         => '2025-04-22 08:45:00',
                'metodo'                     => 'Electroquimioluminiscencia',
                'reactivo'                   => 'Roche Cobas e601',
                'invima'                     => 'INVIMA2021M-0011234',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-22 15:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '52001234',
            ],
            [
                'cups'                       => '904110',
                'nombre_laboratorio'         => 'T4 Libre',
                'fecha_toma_muestra'         => '2025-04-22 08:45:00',
                'metodo'                     => 'Electroquimioluminiscencia',
                'reactivo'                   => 'Roche Cobas e601',
                'invima'                     => 'INVIMA2021M-0011235',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-22 15:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '52001234',
            ],
        ],
        'results' => [
            [
                'cups'        => '904100',
                'values_json' => json_encode([
                    'tsh' => ['valor' => '2.1', 'unidad' => 'mUI/L', 'referencia' => '0.4-4.0'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-22 15:05:00',
            ],
            [
                'cups'        => '904110',
                'values_json' => json_encode([
                    't4_libre' => ['valor' => '1.2', 'unidad' => 'ng/dL', 'referencia' => '0.8-1.8'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-22 15:05:00',
            ],
        ],
    ],

    // ── Orden 7: pending, SIN resultados — ALIADO-001 ────────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0007',
            'id_admision'         => 'ADM-10007',
            'id_atencion'         => null,
            'tipo_documento'      => 'CC',
            'identificacion'      => '4455667788',
            'nombre_paciente'     => 'Andrés Felipe Vargas Ospina',
            'sexo'                => 'M',
            'fecha_nacimiento'    => '1995-08-20',
            'centro_salud'        => 'Hospital Central',
            'fecha_orden'         => '2025-05-01 07:30:00',
            'medico_ordena'       => 'Dr. Pedro Sánchez',
            'numero_autorizacion' => null,
            'id_aliado'           => 'ALIADO-001',
            'fecha_envio'         => null,
            'porc_ejecucion'      => 0.00,
            'estado_orden'        => 'pending',
        ],
        'details' => [
            [
                'cups'                       => '904030',
                'nombre_laboratorio'         => 'Creatinina Sérica',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
            [
                'cups'                       => '904855',
                'nombre_laboratorio'         => 'Perfil Lipídico',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
        ],
        'results' => [],
    ],

    // ── Orden 8: completed, con resultados — ALIADO-002 ──────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0008',
            'id_admision'         => 'ADM-10008',
            'id_atencion'         => 'ATE-20008',
            'tipo_documento'      => 'CC',
            'identificacion'      => '2233445566',
            'nombre_paciente'     => 'Claudia Inés Restrepo Arias',
            'sexo'                => 'F',
            'fecha_nacimiento'    => '1980-05-10',
            'centro_salud'        => 'Centro Médico Sur',
            'fecha_orden'         => '2025-04-12 09:00:00',
            'medico_ordena'       => 'Dra. Ana Martínez',
            'numero_autorizacion' => 'AUTH-008',
            'id_aliado'           => 'ALIADO-002',
            'fecha_envio'         => '2025-04-12 09:30:00',
            'porc_ejecucion'      => 100.00,
            'estado_orden'        => 'completed',
        ],
        'details' => [
            [
                'cups'                       => '904200',
                'nombre_laboratorio'         => 'Parcial de Orina',
                'fecha_toma_muestra'         => '2025-04-12 09:45:00',
                'metodo'                     => 'Tira reactiva + microscopía',
                'reactivo'                   => 'Sysmex UF-5000',
                'invima'                     => 'INVIMA2020M-0006789',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-12 13:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '79345678',
            ],
        ],
        'results' => [
            [
                'cups'        => '904200',
                'values_json' => json_encode([
                    'color'      => ['valor' => 'Amarillo',  'referencia' => 'Amarillo claro'],
                    'aspecto'    => ['valor' => 'Claro',     'referencia' => 'Claro'],
                    'ph'         => ['valor' => '6.0',       'unidad' => '',      'referencia' => '5.0-8.0'],
                    'densidad'   => ['valor' => '1.020',     'referencia' => '1.005-1.030'],
                    'proteinas'  => ['valor' => 'Negativo',  'referencia' => 'Negativo'],
                    'glucosa'    => ['valor' => 'Negativo',  'referencia' => 'Negativo'],
                    'leucocitos' => ['valor' => '2-4/campo', 'referencia' => '0-5/campo'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-12 13:05:00',
            ],
        ],
    ],

    // ── Orden 9: completed, con resultados — ALIADO-002 ──────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0009',
            'id_admision'         => 'ADM-10009',
            'id_atencion'         => null,
            'tipo_documento'      => 'CE',
            'identificacion'      => 'CE-998877',
            'nombre_paciente'     => 'Roberto Carlos Mendoza Fuentes',
            'sexo'                => 'M',
            'fecha_nacimiento'    => '1965-12-03',
            'centro_salud'        => 'Clínica del Sur Ltda.',
            'fecha_orden'         => '2025-04-18 10:30:00',
            'medico_ordena'       => 'Dr. Camilo Torres',
            'numero_autorizacion' => 'AUTH-009',
            'id_aliado'           => 'ALIADO-002',
            'fecha_envio'         => '2025-04-18 11:00:00',
            'porc_ejecucion'      => 100.00,
            'estado_orden'        => 'completed',
        ],
        'details' => [
            [
                'cups'                       => '904300',
                'nombre_laboratorio'         => 'Hemoglobina Glicosilada HbA1c',
                'fecha_toma_muestra'         => '2025-04-18 11:15:00',
                'metodo'                     => 'HPLC',
                'reactivo'                   => 'Bio-Rad D-10',
                'invima'                     => 'INVIMA2022M-0013456',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-18 17:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '79345678',
            ],
            [
                'cups'                       => '904010',
                'nombre_laboratorio'         => 'Glucosa en Ayunas',
                'fecha_toma_muestra'         => '2025-04-18 11:15:00',
                'metodo'                     => 'Enzimático colorimétrico',
                'reactivo'                   => 'Roche Cobas',
                'invima'                     => 'INVIMA2021M-0005678',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-18 14:00:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '79345678',
            ],
        ],
        'results' => [
            [
                'cups'        => '904300',
                'values_json' => json_encode([
                    'hba1c' => ['valor' => '7.2', 'unidad' => '%', 'referencia' => '<5.7 normal, 5.7-6.4 prediabetes, ≥6.5 diabetes'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-18 17:05:00',
            ],
            [
                'cups'        => '904010',
                'values_json' => json_encode([
                    'glucosa' => ['valor' => '138', 'unidad' => 'mg/dL', 'referencia' => '70-100'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-18 14:05:00',
            ],
        ],
    ],

    // ── Orden 10: completed, con resultados — ALIADO-002 ─────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0010',
            'id_admision'         => 'ADM-10010',
            'id_atencion'         => 'ATE-20010',
            'tipo_documento'      => 'CC',
            'identificacion'      => '5544332211',
            'nombre_paciente'     => 'Valentina Sofía Herrera Pinto',
            'sexo'                => 'F',
            'fecha_nacimiento'    => '1993-03-27',
            'centro_salud'        => 'Centro Médico Sur',
            'fecha_orden'         => '2025-04-25 08:00:00',
            'medico_ordena'       => 'Dra. Ana Martínez',
            'numero_autorizacion' => null,
            'id_aliado'           => 'ALIADO-002',
            'fecha_envio'         => '2025-04-25 08:30:00',
            'porc_ejecucion'      => 100.00,
            'estado_orden'        => 'completed',
        ],
        'details' => [
            [
                'cups'                       => '904400',
                'nombre_laboratorio'         => 'Proteína C Reactiva (PCR)',
                'fecha_toma_muestra'         => '2025-04-25 08:45:00',
                'metodo'                     => 'Inmunoturbidimetría',
                'reactivo'                   => 'Abbott Architect',
                'invima'                     => 'INVIMA2021M-0015678',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-25 12:30:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '79345678',
            ],
            [
                'cups'                       => '904855',
                'nombre_laboratorio'         => 'Perfil Lipídico',
                'fecha_toma_muestra'         => '2025-04-25 08:45:00',
                'metodo'                     => 'Enzimático',
                'reactivo'                   => 'Abbott Architect',
                'invima'                     => 'INVIMA2022M-0009012',
                'estado_resultado'           => 'FINAL',
                'fecha_resultado'            => '2025-04-25 12:30:00',
                'tipo_id_bacteriologo'       => 'CC',
                'id_bacteriologo'            => '79345678',
            ],
        ],
        'results' => [
            [
                'cups'        => '904400',
                'values_json' => json_encode([
                    'pcr' => ['valor' => '0.4', 'unidad' => 'mg/L', 'referencia' => '<1.0'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-25 12:35:00',
            ],
            [
                'cups'        => '904855',
                'values_json' => json_encode([
                    'colesterol_total' => ['valor' => '172', 'unidad' => 'mg/dL', 'referencia' => '<200'],
                    'hdl'              => ['valor' => '62',  'unidad' => 'mg/dL', 'referencia' => '>40'],
                    'ldl'              => ['valor' => '95',  'unidad' => 'mg/dL', 'referencia' => '<130'],
                    'trigliceridos'    => ['valor' => '75',  'unidad' => 'mg/dL', 'referencia' => '<150'],
                ]),
                'attachment_path' => null,
                'received_at'     => '2025-04-25 12:35:00',
            ],
        ],
    ],

    // ── Orden 11: pending, SIN resultados — ALIADO-002 ───────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0011',
            'id_admision'         => 'ADM-10011',
            'id_atencion'         => null,
            'tipo_documento'      => 'CC',
            'identificacion'      => '6677889900',
            'nombre_paciente'     => 'Diego Alejandro Ríos Castillo',
            'sexo'                => 'M',
            'fecha_nacimiento'    => '1988-11-15',
            'centro_salud'        => 'Clínica del Sur Ltda.',
            'fecha_orden'         => '2025-05-01 09:00:00',
            'medico_ordena'       => 'Dr. Camilo Torres',
            'numero_autorizacion' => null,
            'id_aliado'           => 'ALIADO-002',
            'fecha_envio'         => null,
            'porc_ejecucion'      => 0.00,
            'estado_orden'        => 'pending',
        ],
        'details' => [
            [
                'cups'                       => '903820',
                'nombre_laboratorio'         => 'Hemograma Completo',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
            [
                'cups'                       => '904300',
                'nombre_laboratorio'         => 'Hemoglobina Glicosilada HbA1c',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
        ],
        'results' => [],
    ],

    // ── Orden 12: pending, SIN resultados — ALIADO-002 ───────────────────────
    [
        'order' => [
            'id_solicitud_key'    => 'SOL-2025-0012',
            'id_admision'         => 'ADM-10012',
            'id_atencion'         => 'ATE-20012',
            'tipo_documento'      => 'TI',
            'identificacion'      => '1122334455',
            'nombre_paciente'     => 'Isabella Camila Suárez Mora',
            'sexo'                => 'F',
            'fecha_nacimiento'    => '2005-07-08',
            'centro_salud'        => 'Centro Médico Sur',
            'fecha_orden'         => '2025-05-02 10:00:00',
            'medico_ordena'       => 'Dra. Ana Martínez',
            'numero_autorizacion' => 'AUTH-012',
            'id_aliado'           => 'ALIADO-002',
            'fecha_envio'         => null,
            'porc_ejecucion'      => 0.00,
            'estado_orden'        => 'pending',
        ],
        'details' => [
            [
                'cups'                       => '904010',
                'nombre_laboratorio'         => 'Glucosa en Ayunas',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
            [
                'cups'                       => '904400',
                'nombre_laboratorio'         => 'Proteína C Reactiva (PCR)',
                'fecha_toma_muestra'         => null,
                'metodo'                     => null,
                'reactivo'                   => null,
                'invima'                     => null,
                'estado_resultado'           => null,
                'fecha_resultado'            => null,
                'tipo_id_bacteriologo'       => null,
                'id_bacteriologo'            => null,
            ],
        ],
        'results' => [],
    ],
];

foreach ($orders as $entry) {
    $o = $entry['order'];

    // Insertar / actualizar orden
    upsertOrder($pdo, $o);
    echo "  ✔  Orden {$o['id_solicitud_key']}  [{$o['estado_orden']}]  paciente: {$o['nombre_paciente']}" . PHP_EOL;

    // Detalles
    foreach ($entry['details'] as $d) {
        $detailData = array_merge(
            ['id_solicitud_key' => $o['id_solicitud_key'], 'id_admision' => $o['id_admision']],
            $d
        );
        // Evitar duplicados en re-ejecución
        $exists = $pdo->prepare(
            "SELECT id FROM lab_order_details WHERE id_solicitud_key = ? AND cups = ?"
        );
        $exists->execute([$o['id_solicitud_key'], $d['cups']]);
        if ($exists->fetchColumn()) {
            echo "      ⚠️  Detalle {$d['cups']} ya existe, omitido." . PHP_EOL;
            continue;
        }
        insert($pdo, 'lab_order_details', $detailData);
        echo "      ✔  Detalle CUPS {$d['cups']} — {$d['nombre_laboratorio']}" . PHP_EOL;
    }

    // Resultados
    foreach ($entry['results'] as $r) {
        $resultData = array_merge(['id_solicitud_key' => $o['id_solicitud_key']], $r);
        $exists = $pdo->prepare(
            "SELECT id FROM lab_results WHERE id_solicitud_key = ? AND cups = ?"
        );
        $exists->execute([$o['id_solicitud_key'], $r['cups']]);
        if ($exists->fetchColumn()) {
            echo "      ⚠️  Resultado {$r['cups']} ya existe, omitido." . PHP_EOL;
            continue;
        }
        insert($pdo, 'lab_results', $resultData);
        echo "      ✔  Resultado CUPS {$r['cups']}" . PHP_EOL;
    }
}

// ── Resumen ───────────────────────────────────────────────────────────────────
echo PHP_EOL . "── Resumen ──" . PHP_EOL;
echo "  Aliados   : " . $pdo->query("SELECT COUNT(*) FROM aliados")->fetchColumn()           . PHP_EOL;
echo "  Usuarios  : " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()             . PHP_EOL;
echo "  Órdenes   : " . $pdo->query("SELECT COUNT(*) FROM lab_orders")->fetchColumn()        . PHP_EOL;

$byAliado = $pdo->query(
    "SELECT id_aliado, estado_orden, COUNT(*) AS total
     FROM lab_orders GROUP BY id_aliado, estado_orden ORDER BY id_aliado, estado_orden"
);
foreach ($byAliado->fetchAll() as $row) {
    echo "    {$row['id_aliado']}  [{$row['estado_orden']}]  {$row['total']} orden(es)" . PHP_EOL;
}

echo "  Detalles  : " . $pdo->query("SELECT COUNT(*) FROM lab_order_details")->fetchColumn() . PHP_EOL;
echo "  Resultados: " . $pdo->query("SELECT COUNT(*) FROM lab_results")->fetchColumn()       . PHP_EOL;

echo PHP_EOL . "✅  Seed completado." . PHP_EOL;
echo PHP_EOL . "Credenciales de prueba:" . PHP_EOL;
echo "  admin          / admin123           (ve todas las órdenes)" . PHP_EOL;
echo "  lab_op         / lab_op123" . PHP_EOL;
echo "  aliado_norte   / aliado_norte123    (ALIADO-001 → 3 completed, 1 sent, 1 pending)" . PHP_EOL;
echo "  aliado_sur     / aliado_sur123      (ALIADO-002 → 4 completed, 1 sent, 2 pending)" . PHP_EOL;
echo "  viewer         / viewer123" . PHP_EOL;
