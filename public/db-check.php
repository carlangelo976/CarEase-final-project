
<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');

    if (!$host || !$name || !$user || !$pass) {
        throw new Exception('Missing database configuration');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Database connected successfully!'
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'error_code' => $e->getCode()
    ]);
}
