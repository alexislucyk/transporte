<?php
/**
 * Carga de variables de entorno desde un archivo .env (formato KEY=VALUE).
 *
 * Requisito:
 * - Este loader se ejecuta antes de leer getenv() para DB.
 */

function loadEnvFile(string $path): void {
    if (!is_file($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;

        // soporta KEY=VALUE y KEY="VALUE"
        $pos = strpos($line, '=');
        if ($pos === false) continue;

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        // quitar comillas simples/dobles
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last = $val[strlen($val)-1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }

        if ($key !== '') {
            // Forzar seteo del valor del .env en runtime.
            // (Esto evita casos donde getenv() devuelve false/'' aunque el .env exista.)
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }


    }
}

// Auto-load desde la raíz del proyecto (.env)
$rootDir = dirname(__DIR__); // core/ -> raíz
// También intentar un loader alternativo por si la extensión no deja leer .env en runtime.
// Si existe 'env.php' o 'env.local.php', se prioriza.
loadEnvFile($rootDir . DIRECTORY_SEPARATOR . '.env');


