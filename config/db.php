<?php
// DB config centralizada para Trans Cargo Hub.
// Lee credenciales desde .env con fallbacks seguros.

function env_or_constant(string $key, string $fallback): string {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return isset($_ENV[$key]) && $_ENV[$key] !== '' ? (string)$_ENV[$key] : $fallback;
}

define('DB_HOST', env_or_constant('DB_HOST', 'localhost'));
define('DB_NAME', env_or_constant('DB_NAME', 'trans_dev_db'));
define('DB_USER', env_or_constant('DB_USER', 'root'));
define('DB_PASS', env_or_constant('DB_PASS', 'isidoro9'));

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    // Log interno con detalles (nunca expuesto al cliente)
    error_log("[DB CONNECTION ERROR] " . $e->getMessage() . " | DB: " . DB_NAME . " | User: " . DB_USER);
    // Mensaje genérico al usuario
    die("Error: No se pudo conectar a la base de datos. Contacte al administrador del sistema.");
}

