<?php

/**
 * Seed de exámenes reales — basado en resultados de Laboratorio Clínico Fleming S.A.S
 * y EVM SAS IPS (Ciudadela), con reactivos del listado de laboratorios parametrizados.
 *
 * Exámenes incluidos:
 *   902210  Hemograma IV (NIHON KOHDEN / MINDRAY)
 *   907106  Uroanalisis (Mission / winner)
 *   901236  Urocultivo y Antibiograma (Ad-Bio / LABG&M)
 *   901304  Examen Directo Fresco — Frotis Vaginal (Biosistems / winner)
 *   906039  Treponema pallidum Anticuerpos MHTPA (Abbott / Biosistems / Onsite)
 *   906249  VIH 1 y 2 Anticuerpos (Abbott / Onsite / AD / Ad-Bio)
 *   903818  Colesterol Total (Biosistems / winner / Biomed / Human)
 *   903868  Triglicéridos (Biosistems / Biomed)
 *   903815  Colesterol HDL (Biosistems / winner / Human)
 *   903816  Colesterol LDL (Biosistems)
 *   903895  Creatinina en Suero (Biosistems)
 *   903841  Glucosa en Suero (Biosistems / Human)
 *
 * Uso:
 *   php database/seed_exam_catalog_labs.php
 */

declare(strict_types=1);

// ── Entorno ───────────────────────────────────────────────────────────────────
$envFile = dirname(__DIR__) . '/config/.env';
if (!file_exists($envFile)) {
    $envFile = dirname(__DIR__) . '/.env';
}
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

echo "✅  Conectado a la base de datos" . PHP_EOL;

// ── Helpers ───────────────────────────────────────────────────────────────────
function upsertType(PDO $pdo, string $cups, string $nombre, ?string $desc = null): void
{
    $pdo->prepare(
        'INSERT INTO exam_types (cups, nombre, descripcion, activo)
         VALUES (:cups, :nombre, :desc, 1)
         ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion)'
    )->execute(['cups' => $cups, 'nombre' => $nombre, 'desc' => $desc]);
}

function upsertParam(PDO $pdo, array $p): int
{
    $pdo->prepare(
        'INSERT INTO exam_parameters
            (cups, codigo, nombre, unidad, valor_min_ref, valor_max_ref,
             tipo_resultado, etiqueta_booleano, sexo, edad_min, edad_max,
             obligatorio, orden, activo)
         VALUES
            (:cups,:codigo,:nombre,:unidad,:min,:max,
             :tipo,:etiqueta,:sexo,:emin,:emax,:oblig,:orden,1)
         ON DUPLICATE KEY UPDATE
            nombre           = VALUES(nombre),
            unidad           = VALUES(unidad),
            valor_min_ref    = VALUES(valor_min_ref),
            valor_max_ref    = VALUES(valor_max_ref),
            tipo_resultado   = VALUES(tipo_resultado),
            etiqueta_booleano= VALUES(etiqueta_booleano),
            obligatorio      = VALUES(obligatorio),
            orden            = VALUES(orden),
            activo           = 1'
    )->execute([
        'cups'     => $p['cups'],
        'codigo'   => $p['codigo'],
        'nombre'   => $p['nombre'],
        'unidad'   => $p['unidad']    ?? null,
        'min'      => $p['min']       ?? null,
        'max'      => $p['max']       ?? null,
        'tipo'     => $p['tipo']      ?? 'numerico',
        'etiqueta' => $p['etiqueta']  ?? null,
        'sexo'     => $p['sexo']      ?? '*',
        'emin'     => $p['emin']      ?? null,
        'emax'     => $p['emax']      ?? null,
        'oblig'    => $p['oblig']     ?? 0,
        'orden'    => $p['orden']     ?? 0,
    ]);

    // Recuperar el id (sea INSERT o UPDATE)
    $row = $pdo->prepare(
        'SELECT id FROM exam_parameters
          WHERE cups=:cups AND codigo=:codigo AND sexo=:sexo
            AND (edad_min <=> :emin) AND (edad_max <=> :emax)
          LIMIT 1'
    );
    $row->execute([
        'cups'   => $p['cups'],
        'codigo' => $p['codigo'],
        'sexo'   => $p['sexo']  ?? '*',
        'emin'   => $p['emin']  ?? null,
        'emax'   => $p['emax']  ?? null,
    ]);
    return (int) $row->fetchColumn();
}

function upsertRange(PDO $pdo, int $parameterId, string $reactivo, array $r): void
{
    $pdo->prepare(
        'INSERT INTO exam_parameter_ranges
            (parameter_id, reactivo, valor_min_ref, valor_max_ref, sexo, edad_min, edad_max, activo)
         VALUES (:pid, :reactivo, :min, :max, :sexo, :emin, :emax, 1)
         ON DUPLICATE KEY UPDATE
            valor_min_ref = VALUES(valor_min_ref),
            valor_max_ref = VALUES(valor_max_ref),
            activo        = 1'
    )->execute([
        'pid'     => $parameterId,
        'reactivo'=> $reactivo,
        'min'     => $r['min']  ?? null,
        'max'     => $r['max']  ?? null,
        'sexo'    => $r['sexo'] ?? '*',
        'emin'    => $r['emin'] ?? null,
        'emax'    => $r['emax'] ?? null,
    ]);
}

// ── Catálogo ──────────────────────────────────────────────────────────────────
// Estructura:
//   cups => [
//     'nombre'  => string,
//     'desc'    => string|null,
//     'params'  => [
//       [
//         'codigo', 'nombre', 'unidad', 'min', 'max',
//         'tipo'    => 'numerico'|'texto'|'booleano',
//         'etiqueta'=> null|'positivo_negativo'|'reactivo_no_reactivo',
//         'sexo', 'emin', 'emax', 'oblig', 'orden',
//         'rangos'  => [ 'NombreReactivo' => ['min'=>x,'max'=>y,'sexo'=>'*'] ]
//       ]
//     ]
//   ]

$catalog = [];

// ═══════════════════════════════════════════════════════════════════════════════
// 902210 — Hemograma IV (Método Automático con histograma)
// Reactivos: NIHON KOHDEN (ID 22), MINDRAY (ID 23)
// Fuente PDF: Fleming (NIHON KOHDEN) y EVM SAS IPS Ciudadela (MINDRAY BC-3000)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['902210'] = [
    'nombre' => 'Hemograma IV [Hemoglobina, Hematocrito, Eritrocitos, Índices, Leucograma, Plaquetas, Histograma] Método Automático',
    'desc'   => 'CUPS 902210 — Hemograma completo automatizado con diferencial de 5 partes e histograma. Equipos: NIHON KOHDEN (Fleming) y MINDRAY BC-3000 (EVM/Ciudadela).',
    'params' => [
        // ── Leucocitos ────────────────────────────────────────────────────────
        ['codigo'=>'wbc',     'nombre'=>'Recuento de Leucocitos (WBC)',                    'unidad'=>'10^9/L',   'min'=>4.00,  'max'=>10.00, 'oblig'=>1,'orden'=>1,
         'rangos'=>['NIHON KOHDEN'=>['min'=>4.00,'max'=>10.00],'MINDRAY'=>['min'=>4.00,'max'=>10.00]]],

        // ── Diferencial absoluto ──────────────────────────────────────────────
        ['codigo'=>'neu_abs', 'nombre'=>'Neutrófilos # (NEU#)',                            'unidad'=>'10^9/L',   'min'=>2.50,  'max'=>75.00, 'oblig'=>1,'orden'=>2,
         'rangos'=>['NIHON KOHDEN'=>['min'=>2.50,'max'=>75.00],'MINDRAY'=>['min'=>2.00,'max'=>7.00]]],
        ['codigo'=>'lym_abs', 'nombre'=>'Linfocitos # (LYM#)',                             'unidad'=>'10^9/L',   'min'=>0.80,  'max'=>4.80,  'oblig'=>1,'orden'=>3,
         'rangos'=>['NIHON KOHDEN'=>['min'=>0.80,'max'=>4.80],'MINDRAY'=>['min'=>0.80,'max'=>4.00]]],
        ['codigo'=>'mon_abs', 'nombre'=>'Monocitos # (MON#)',                              'unidad'=>'10^9/L',   'min'=>0.12,  'max'=>1.80,  'oblig'=>1,'orden'=>4,
         'rangos'=>['NIHON KOHDEN'=>['min'=>0.12,'max'=>1.80],'MINDRAY'=>['min'=>0.12,'max'=>0.80]]],

        // ── Diferencial porcentual ────────────────────────────────────────────
        ['codigo'=>'neu_pct', 'nombre'=>'Neutrófilos % (NEU%)',                            'unidad'=>'%',        'min'=>50.0,  'max'=>70.0,  'oblig'=>1,'orden'=>5,
         'rangos'=>['NIHON KOHDEN'=>['min'=>50.0,'max'=>70.0],'MINDRAY'=>['min'=>45.0,'max'=>70.0]]],
        ['codigo'=>'lym_pct', 'nombre'=>'Linfocitos % (LYM%)',                             'unidad'=>'%',        'min'=>20.0,  'max'=>40.0,  'oblig'=>1,'orden'=>6,
         'rangos'=>['NIHON KOHDEN'=>['min'=>20.0,'max'=>40.0],'MINDRAY'=>['min'=>25.0,'max'=>45.0]]],
        ['codigo'=>'mon_pct', 'nombre'=>'Monocitos % (MON%)',                              'unidad'=>'%',        'min'=>3.0,   'max'=>12.0,  'oblig'=>1,'orden'=>7,
         'rangos'=>['NIHON KOHDEN'=>['min'=>3.0,'max'=>12.0],'MINDRAY'=>['min'=>3.0,'max'=>12.0]]],

        // ── Serie roja ────────────────────────────────────────────────────────
        ['codigo'=>'rbc',     'nombre'=>'Recuento de Eritrocitos (RBC)',                   'unidad'=>'10^12/L',  'min'=>3.50,  'max'=>5.50,  'oblig'=>1,'orden'=>8,
         'rangos'=>['NIHON KOHDEN'=>['min'=>3.50,'max'=>5.50],'MINDRAY'=>['min'=>4.00,'max'=>5.00]]],
        ['codigo'=>'hgb',     'nombre'=>'Hemoglobina (HGB)',                               'unidad'=>'g/dL',     'min'=>11.0,  'max'=>16.0,  'oblig'=>1,'orden'=>9,
         'rangos'=>[
             'NIHON KOHDEN' =>['min'=>11.0,'max'=>16.0],
             'MINDRAY'      =>['min'=>11.0,'max'=>16.0],
             'MINDRAY_M'    =>['min'=>12.0,'max'=>17.0,'sexo'=>'M'],
             'MINDRAY_F'    =>['min'=>11.0,'max'=>16.0,'sexo'=>'F'],
         ]],
        ['codigo'=>'hct',     'nombre'=>'Hematocrito (HCT)',                               'unidad'=>'%',        'min'=>37.0,  'max'=>54.0,  'oblig'=>1,'orden'=>10,
         'rangos'=>['NIHON KOHDEN'=>['min'=>37.0,'max'=>54.0],'MINDRAY'=>['min'=>36.0,'max'=>52.0]]],
        ['codigo'=>'mcv',     'nombre'=>'Volumen Corpuscular Medio (MCV)',                 'unidad'=>'fL',       'min'=>80.0,  'max'=>100.0, 'oblig'=>1,'orden'=>11,
         'rangos'=>['NIHON KOHDEN'=>['min'=>80.0,'max'=>100.0],'MINDRAY'=>['min'=>80.0,'max'=>100.0]]],
        ['codigo'=>'mch',     'nombre'=>'Hemoglobina Corpuscular Media (MCH)',             'unidad'=>'pg',       'min'=>27.0,  'max'=>34.0,  'oblig'=>1,'orden'=>12,
         'rangos'=>['NIHON KOHDEN'=>['min'=>27.0,'max'=>34.0],'MINDRAY'=>['min'=>27.0,'max'=>34.0]]],
        ['codigo'=>'mchc',    'nombre'=>'Concentración Media de Hb Corpuscular (MCHC)',   'unidad'=>'g/dL',     'min'=>32.0,  'max'=>36.0,  'oblig'=>1,'orden'=>13,
         'rangos'=>['NIHON KOHDEN'=>['min'=>32.0,'max'=>36.0],'MINDRAY'=>['min'=>31.0,'max'=>36.0]]],
        ['codigo'=>'rdw_cv',  'nombre'=>'Índice de Distribución Eritrocitaria (RDW-CV)',  'unidad'=>'%',        'min'=>11.0,  'max'=>16.0,  'orden'=>14,
         'rangos'=>['NIHON KOHDEN'=>['min'=>11.0,'max'=>16.0],'MINDRAY'=>['min'=>11.0,'max'=>16.0]]],
        ['codigo'=>'rdw_sd',  'nombre'=>'Índice de Distribución Eritrocitaria (RDW-SD)',  'unidad'=>'fL',       'min'=>35.0,  'max'=>56.0,  'orden'=>15,
         'rangos'=>['NIHON KOHDEN'=>['min'=>35.0,'max'=>56.0],'MINDRAY'=>['min'=>35.0,'max'=>56.0]]],

        // ── Plaquetas ─────────────────────────────────────────────────────────
        ['codigo'=>'plt',     'nombre'=>'Recuento de Plaquetas (PLT)',                     'unidad'=>'10^9/L',   'min'=>150.0, 'max'=>450.0, 'oblig'=>1,'orden'=>16,
         'rangos'=>['NIHON KOHDEN'=>['min'=>150.0,'max'=>450.0],'MINDRAY'=>['min'=>150.0,'max'=>450.0]]],
        ['codigo'=>'mpv',     'nombre'=>'Volumen Plaquetario Medio (MPV)',                 'unidad'=>'fL',       'min'=>6.5,   'max'=>12.0,  'orden'=>17,
         'rangos'=>['NIHON KOHDEN'=>['min'=>6.5,'max'=>12.0],'MINDRAY'=>['min'=>7.0,'max'=>12.0]]],
        ['codigo'=>'pdw',     'nombre'=>'Índice de Distribución Plaquetaria (PDW)',        'unidad'=>'fL',       'min'=>9.0,   'max'=>17.0,  'orden'=>18,
         'rangos'=>['NIHON KOHDEN'=>['min'=>9.0,'max'=>17.0],'MINDRAY'=>['min'=>7.0,'max'=>12.0]]],
        ['codigo'=>'pct',     'nombre'=>'Plaquetocrito (PCT)',                             'unidad'=>'%',        'min'=>0.108, 'max'=>0.282, 'orden'=>19,
         'rangos'=>['NIHON KOHDEN'=>['min'=>0.108,'max'=>0.282],'MINDRAY'=>['min'=>0.0,'max'=>1.0]]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 907106 — Uroanalisis (Parcial de Orina)
// Reactivos: Mission (ID 28), winner (ID 5)
// Fuente PDF: Fleming (NIHON KOHDEN analizador BF 6900 / Mission)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['907106'] = [
    'nombre' => 'Uroanalisis',
    'desc'   => 'CUPS 907106 — Parcial de orina con tira reactiva y microscopía. Reactivos: Mission, winner.',
    'params' => [
        ['codigo'=>'color',       'nombre'=>'Color',                    'unidad'=>'',       'tipo'=>'texto',    'oblig'=>1,'orden'=>1,  'rangos'=>[]],
        ['codigo'=>'aspecto',     'nombre'=>'Aspecto',                  'unidad'=>'',       'tipo'=>'texto',    'oblig'=>1,'orden'=>2,  'rangos'=>[]],
        ['codigo'=>'densidad',    'nombre'=>'Densidad',                 'unidad'=>'',       'min'=>1.005,'max'=>1.030,'oblig'=>1,'orden'=>3,
         'rangos'=>['Mission'=>['min'=>1.005,'max'=>1.030],'winner'=>['min'=>1.005,'max'=>1.030]]],
        ['codigo'=>'ph',          'nombre'=>'pH',                       'unidad'=>'',       'min'=>5.0,  'max'=>8.0,  'oblig'=>1,'orden'=>4,
         'rangos'=>['Mission'=>['min'=>5.0,'max'=>8.0],'winner'=>['min'=>5.0,'max'=>8.0]]],
        ['codigo'=>'proteinas',   'nombre'=>'Proteínas',                'unidad'=>'mg/dL',  'tipo'=>'texto',    'orden'=>5,  'rangos'=>[]],
        ['codigo'=>'glucosa_o',   'nombre'=>'Glucosa (orina)',          'unidad'=>'mg/dL',  'tipo'=>'texto',    'orden'=>6,  'rangos'=>[]],
        ['codigo'=>'leucocitos_e','nombre'=>'Leucocitos (Esterasa)',    'unidad'=>'Cel/ul', 'tipo'=>'texto',    'orden'=>7,  'rangos'=>[]],
        ['codigo'=>'cetonas',     'nombre'=>'Cetonas',                  'unidad'=>'mg/dL',  'tipo'=>'texto',    'orden'=>8,  'rangos'=>[]],
        ['codigo'=>'bilirrubina', 'nombre'=>'Bilirrubina',              'unidad'=>'mg/dL',  'tipo'=>'texto',    'orden'=>9,  'rangos'=>[]],
        ['codigo'=>'urobilinogeno','nombre'=>'Urobilinógeno',           'unidad'=>'mg/dL',  'tipo'=>'texto',    'orden'=>10, 'rangos'=>[]],
        ['codigo'=>'sangre',      'nombre'=>'Sangre',                   'unidad'=>'',       'tipo'=>'texto',    'orden'=>11, 'rangos'=>[]],
        ['codigo'=>'nitritos',    'nombre'=>'Nitritos',                 'unidad'=>'',       'tipo'=>'booleano', 'etiqueta'=>'positivo_negativo','orden'=>12,'rangos'=>[]],
        ['codigo'=>'microalbuminuria','nombre'=>'Microalbuminuria',     'unidad'=>'mg/dL',  'tipo'=>'texto',    'orden'=>13, 'rangos'=>[]],
        // Microscopía
        ['codigo'=>'cel_epiteliales','nombre'=>'Células Epiteliales',  'unidad'=>'/campo',  'tipo'=>'texto',    'orden'=>14, 'rangos'=>[]],
        ['codigo'=>'leucocitos_m','nombre'=>'Leucocitos (microscopía)', 'unidad'=>'/campo',  'tipo'=>'texto',    'orden'=>15, 'rangos'=>[]],
        ['codigo'=>'hematies_m',  'nombre'=>'Hematíes (microscopía)',   'unidad'=>'/campo',  'tipo'=>'texto',    'orden'=>16, 'rangos'=>[]],
        ['codigo'=>'cilindros',   'nombre'=>'Cilindros',                'unidad'=>'/campo',  'tipo'=>'texto',    'orden'=>17, 'rangos'=>[]],
        ['codigo'=>'cristales',   'nombre'=>'Cristales',                'unidad'=>'',        'tipo'=>'texto',    'orden'=>18, 'rangos'=>[]],
        ['codigo'=>'moco',        'nombre'=>'Moco',                     'unidad'=>'',        'tipo'=>'texto',    'orden'=>19, 'rangos'=>[]],
        ['codigo'=>'bacterias_o', 'nombre'=>'Bacterias (orina)',        'unidad'=>'',        'tipo'=>'texto',    'orden'=>20, 'rangos'=>[]],
        ['codigo'=>'otros_o',     'nombre'=>'Otros',                    'unidad'=>'',        'tipo'=>'texto',    'orden'=>21, 'rangos'=>[]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 901236 — Urocultivo y Antibiograma (MIC Automático)
// Reactivos: Ad-Bio (ID 31), LABG&M (ID 34)
// Fuente PDF: Fleming — resultado cualitativo + antibiograma
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['901236'] = [
    'nombre' => 'Urocultivo [Antibiograma MIC Automático]',
    'desc'   => 'CUPS 901236 — Urocultivo con antibiograma por concentración inhibitoria mínima (MIC). Reactivos: Ad-Bio, LABG&M.',
    'params' => [
        ['codigo'=>'gram_orina',      'nombre'=>'Gram de orina sin centrifugar',  'unidad'=>'', 'tipo'=>'texto',    'oblig'=>1,'orden'=>1,'rangos'=>[]],
        ['codigo'=>'bacteria_aislada','nombre'=>'Bacteria Aislada',               'unidad'=>'', 'tipo'=>'texto',    'oblig'=>1,'orden'=>2,'rangos'=>[]],
        ['codigo'=>'tiempo_incubacion','nombre'=>'Tiempo de Incubación',          'unidad'=>'h','tipo'=>'texto',    'orden'=>3,'rangos'=>[]],
        ['codigo'=>'resultado_cultivo','nombre'=>'Resultado del Cultivo',         'unidad'=>'', 'tipo'=>'booleano', 'etiqueta'=>'positivo_negativo','oblig'=>1,'orden'=>4,'rangos'=>[]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 901304 — Examen Directo Fresco (Frotis Vaginal / Secreción)
// Reactivos: Biosistems (ID 15), winner (ID 5), N/A (ID 21)
// Fuente PDF: Fleming — frotis de flujo vaginal
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['901304'] = [
    'nombre' => 'Examen Directo Fresco De Cualquier Muestra',
    'desc'   => 'CUPS 901304 — Incluye frotis vaginal con evaluación de vaginosis, vaginitis, Trichomonas, Candida y flora. Reactivos: Biosistems, winner.',
    'params' => [
        ['codigo'=>'color_v',       'nombre'=>'Color',                          'unidad'=>'','tipo'=>'texto','oblig'=>1,'orden'=>1,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'aspecto_v',     'nombre'=>'Aspecto',                        'unidad'=>'','tipo'=>'texto','oblig'=>1,'orden'=>2,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'ph_v',          'nombre'=>'pH',                             'unidad'=>'','min'=>3.8,'max'=>4.5,'orden'=>3,'sexo'=>'F',
         'rangos'=>['Biosistems'=>['min'=>3.8,'max'=>4.5,'sexo'=>'F'],'winner'=>['min'=>3.8,'max'=>4.5,'sexo'=>'F']]],
        ['codigo'=>'cel_epiteliales_v','nombre'=>'Células Epiteliales',         'unidad'=>'/campo','tipo'=>'texto','orden'=>4,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'celulas_guias', 'nombre'=>'Células Guías',                  'unidad'=>'','tipo'=>'booleano','etiqueta'=>'positivo_negativo','orden'=>5,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'bacterias_v',   'nombre'=>'Bacterias',                      'unidad'=>'','tipo'=>'texto','orden'=>6,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'leucocitos_v',  'nombre'=>'Leucocitos',                     'unidad'=>'','tipo'=>'texto','orden'=>7,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'micelios',      'nombre'=>'Micelios',                       'unidad'=>'','tipo'=>'texto','orden'=>8,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'levaduras_v',   'nombre'=>'Levaduras',                      'unidad'=>'','tipo'=>'texto','orden'=>9,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'vaginosis',     'nombre'=>'Vaginosis',                      'unidad'=>'','tipo'=>'booleano','etiqueta'=>'positivo_negativo','orden'=>10,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'vaginitis',     'nombre'=>'Vaginitis',                      'unidad'=>'','tipo'=>'booleano','etiqueta'=>'positivo_negativo','orden'=>11,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'blastoconidias','nombre'=>'Blastoconidias tipo Candida',    'unidad'=>'','tipo'=>'texto','orden'=>12,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'mobiluncus',    'nombre'=>'Mobiluncus',                     'unidad'=>'','tipo'=>'booleano','etiqueta'=>'positivo_negativo','orden'=>13,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'trichomonas',   'nombre'=>'Trichomonas vaginales',          'unidad'=>'','tipo'=>'texto','orden'=>14,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'lactobacilos',  'nombre'=>'Lactobacilos',                   'unidad'=>'','tipo'=>'texto','orden'=>15,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'cocobacilos',   'nombre'=>'Cocobacilos Gram Variables',     'unidad'=>'','tipo'=>'texto','orden'=>16,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'tipo_gvaginalis','nombre'=>'Tipo G. vaginalis',             'unidad'=>'','tipo'=>'texto','orden'=>17,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'reaccion_leuco','nombre'=>'Reacción Leucocitaria',          'unidad'=>'','tipo'=>'texto','orden'=>18,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'pseudomicelios','nombre'=>'Pseudomicelios',                 'unidad'=>'','tipo'=>'texto','orden'=>19,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'levaduras_gem', 'nombre'=>'Levaduras en Gemación',          'unidad'=>'','tipo'=>'texto','orden'=>20,'sexo'=>'F','rangos'=>[]],
        ['codigo'=>'examen_microsc','nombre'=>'Examen Microscópico',            'unidad'=>'','tipo'=>'texto','orden'=>21,'rangos'=>[]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 906039 — Treponema pallidum Anticuerpos (Prueba Treponémica MHTPA)
// Reactivos: Abbott (ID 19), AD (ID 24), Ad-Bio (ID 31), Biosistems (ID 15), Onsite (ID 20)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['906039'] = [
    'nombre' => 'Treponema pallidum ANTICUERPOS (Prueba Treponémica) Manual o Semiautomatizada o Automatizada',
    'desc'   => 'CUPS 906039 — FTA / MHTPA. Reactivos: Abbott, AD, Ad-Bio, Biosistems, Onsite.',
    'params' => [
        ['codigo'=>'treponema_ac','nombre'=>'Treponema pallidum Anticuerpos (MHTPA)',
         'unidad'=>'','tipo'=>'booleano','etiqueta'=>'reactivo_no_reactivo','oblig'=>1,'orden'=>1,
         'rangos'=>[]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 906249 — VIH 1 y 2 Anticuerpos
// Reactivos: Abbott (ID 19), AD (ID 24), Ad-Bio (ID 31), H&M (ID 37), N/A (ID 21), Onsite (ID 20)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['906249'] = [
    'nombre' => 'VIH 1 y 2 Anticuerpos',
    'desc'   => 'CUPS 906249 — Inmunocromatografía. Reactivos: Abbott, AD, Ad-Bio, H&M, Onsite.',
    'params' => [
        ['codigo'=>'vih_ac','nombre'=>'VIH Anticuerpos 1 y 2',
         'unidad'=>'','tipo'=>'booleano','etiqueta'=>'reactivo_no_reactivo','oblig'=>1,'orden'=>1,
         'rangos'=>[]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 903818 — Colesterol Total
// Reactivos: Biosistems (ID 15), winner (ID 5), Biomed (ID 14), Human (ID 8)
// Rangos: Fleming CM-250 Wiener / Bernardo BS-200
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['903818'] = [
    'nombre' => 'Colesterol Total',
    'desc'   => 'CUPS 903818 — Método enzimático. Reactivos: Biosistems, winner, Biomed, Human.',
    'params' => [
        ['codigo'=>'col_total','nombre'=>'Colesterol Total','unidad'=>'mg/dL',
         'min'=>0.0,'max'=>200.0,'oblig'=>1,'orden'=>1,
         'rangos'=>[
             'Biosistems'=>['min'=>0.0,'max'=>200.0],
             'winner'    =>['min'=>0.0,'max'=>200.0],
             'Biomed'    =>['min'=>0.0,'max'=>200.0],
             'Human'     =>['min'=>0.0,'max'=>200.0],
         ]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 903868 — Triglicéridos
// Reactivos: Biosistems (ID 15), Biomed (ID 14)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['903868'] = [
    'nombre' => 'Triglicéridos',
    'desc'   => 'CUPS 903868 — Método enzimático. Reactivos: Biosistems, Biomed.',
    'params' => [
        ['codigo'=>'trigliceridos','nombre'=>'Triglicéridos','unidad'=>'mg/dL',
         'min'=>0.0,'max'=>150.0,'oblig'=>1,'orden'=>1,
         'rangos'=>[
             'Biosistems'=>['min'=>0.0,'max'=>150.0],
             'Biomed'    =>['min'=>0.0,'max'=>150.0],
         ]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 903815 — Colesterol HDL
// Reactivos: Biosistems (ID 15), winner (ID 5), Human (ID 8)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['903815'] = [
    'nombre' => 'Colesterol de Alta Densidad [HDL]',
    'desc'   => 'CUPS 903815 — Método enzimático. Reactivos: Biosistems, winner, Human.',
    'params' => [
        ['codigo'=>'hdl','nombre'=>'Colesterol HDL','unidad'=>'mg/dL',
         'min'=>30.0,'max'=>70.0,'sexo'=>'M','oblig'=>1,'orden'=>1,
         'rangos'=>[
             'Biosistems_M'=>['min'=>55.0,'max'=>150.0,'sexo'=>'M'],
             'winner_M'    =>['min'=>30.0,'max'=>70.0, 'sexo'=>'M'],
             'Human_M'     =>['min'=>30.0,'max'=>70.0, 'sexo'=>'M'],
         ]],
        ['codigo'=>'hdl','nombre'=>'Colesterol HDL','unidad'=>'mg/dL',
         'min'=>30.0,'max'=>85.0,'sexo'=>'F','oblig'=>1,'orden'=>1,
         'rangos'=>[
             'Biosistems_F'=>['min'=>45.0,'max'=>150.0,'sexo'=>'F'],
             'winner_F'    =>['min'=>30.0,'max'=>85.0, 'sexo'=>'F'],
             'Human_F'     =>['min'=>30.0,'max'=>85.0, 'sexo'=>'F'],
         ]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 903816 — Colesterol LDL (Enzimático)
// Reactivos: Biosistems (ID 15)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['903816'] = [
    'nombre' => 'Colesterol de Baja Densidad [LDL] Enzimático',
    'desc'   => 'CUPS 903816 — Método enzimático. Reactivo: Biosistems.',
    'params' => [
        ['codigo'=>'ldl','nombre'=>'Colesterol LDL','unidad'=>'mg/dL',
         'min'=>0.0,'max'=>100.0,'oblig'=>1,'orden'=>1,
         'rangos'=>['Biosistems'=>['min'=>0.0,'max'=>100.0]]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 903895 — Creatinina en Suero u Otros Fluidos
// Reactivos: Biosistems (ID 15), BYOSISTEMS (ID 33)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['903895'] = [
    'nombre' => 'Creatinina en Suero u Otros Fluidos',
    'desc'   => 'CUPS 903895 — Método cinético. Reactivos: Biosistems, BYOSISTEMS.',
    'params' => [
        ['codigo'=>'creatinina','nombre'=>'Creatinina','unidad'=>'mg/dL',
         'min'=>0.70,'max'=>1.40,'sexo'=>'M','oblig'=>1,'orden'=>1,
         'rangos'=>[
             'Biosistems_M'  =>['min'=>0.70,'max'=>1.40,'sexo'=>'M'],
             'BYOSISTEMS_M'  =>['min'=>0.70,'max'=>1.30,'sexo'=>'M'],
         ]],
        ['codigo'=>'creatinina','nombre'=>'Creatinina','unidad'=>'mg/dL',
         'min'=>0.60,'max'=>1.20,'sexo'=>'F','oblig'=>1,'orden'=>1,
         'rangos'=>[
             'Biosistems_F'  =>['min'=>0.60,'max'=>1.20,'sexo'=>'F'],
             'BYOSISTEMS_F'  =>['min'=>0.66,'max'=>1.10,'sexo'=>'F'],
         ]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// 903841 — Glucosa en Suero u Otro Fluido
// Reactivos: Biosistems (ID 15), Human (ID 8)
// ═══════════════════════════════════════════════════════════════════════════════
$catalog['903841'] = [
    'nombre' => 'Glucosa en Suero u Otro Fluido Diferente a Orina',
    'desc'   => 'CUPS 903841 — Método enzimático. Reactivos: Biosistems, Human.',
    'params' => [
        ['codigo'=>'glucosa','nombre'=>'Glucosa en Suero','unidad'=>'mg/dL',
         'min'=>60.0,'max'=>110.0,'oblig'=>1,'orden'=>1,
         'rangos'=>[
             'Biosistems'=>['min'=>70.0,'max'=>110.0],
             'Human'     =>['min'=>70.0,'max'=>100.0],
         ]],
    ],
];

// ═══════════════════════════════════════════════════════════════════════════════
// Insertar en la base de datos
// ═══════════════════════════════════════════════════════════════════════════════
echo PHP_EOL . "── Tipos de examen ──────────────────────────────────────────" . PHP_EOL;
foreach ($catalog as $cups => $def) {
    upsertType($pdo, (string) $cups, $def['nombre'], $def['desc'] ?? null);
    echo "  ✔  {$cups}  {$def['nombre']}" . PHP_EOL;
}

echo PHP_EOL . "── Parámetros y rangos por reactivo ─────────────────────────" . PHP_EOL;
$totalParams = 0;
$totalRangos = 0;

foreach ($catalog as $cups => $def) {
    $countP = 0;
    $countR = 0;
    foreach ($def['params'] as $p) {
        $p['cups'] = (string) $cups;
        $paramId   = upsertParam($pdo, $p);
        $countP++;

        foreach (($p['rangos'] ?? []) as $reactivo => $rango) {
            upsertRange($pdo, $paramId, $reactivo, $rango);
            $countR++;
        }
    }
    echo "  ✔  {$cups}  {$countP} parámetro(s)  {$countR} rango(s)" . PHP_EOL;
    $totalParams += $countP;
    $totalRangos += $countR;
}

echo PHP_EOL . "── Resumen ──────────────────────────────────────────────────" . PHP_EOL;
echo "  Tipos de examen    : " . $pdo->query("SELECT COUNT(*) FROM exam_types")->fetchColumn()           . PHP_EOL;
echo "  Parámetros totales : " . $pdo->query("SELECT COUNT(*) FROM exam_parameters")->fetchColumn()      . PHP_EOL;
echo "  Rangos por reactivo: " . $pdo->query("SELECT COUNT(*) FROM exam_parameter_ranges")->fetchColumn(). PHP_EOL;
echo PHP_EOL . "✅  Catálogo de laboratorios cargado correctamente." . PHP_EOL;
