<?php

/**
 * Router para php -S (desarrollo).
 * Redirige todo a index.php excepto archivos físicos reales dentro de public/.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Si existe como archivo físico dentro de public/ → servirlo directamente
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Todo lo demás → Slim
require __DIR__ . '/index.php';
