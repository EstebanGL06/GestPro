<?php

$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];
$db = $config['db'] ?? [];

$host    = $db['host'] ?? (getenv('DB_HOST') ?: 'localhost');
$dbname  = $db['name'] ?? (getenv('DB_NAME') ?: 'gestpro_verifactu');
$user_db = $db['user'] ?? (getenv('DB_USER') ?: 'root');
$pass_db = $db['pass'] ?? (getenv('DB_PASS') ?: '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user_db, $pass_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    error_log("Error de conexión a la BD: " . $e->getMessage());
    die("Error interno del servidor. Por favor, inténtelo más tarde.");
}
