<?php
// DB config centralizada para Trans Cargo Hub.
// Solución definitiva: eliminar dependencia de .env en runtime.
// Se prioriza una constante hardcodeada segura para evitar bloqueos por loader.
// Si más adelante querés .env, se puede reintroducir, pero ahora priorizamos que funcione.

if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'trans_dev_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    // Password real de tu DB (según database_schema/db anterior del proyecto)
    define('DB_PASS', 'isidoro9');
}

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
    die("Error de conexión: " . $e->getMessage());
}

