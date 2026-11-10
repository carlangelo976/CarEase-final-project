
<?php
header('Content-Type: application/json');

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');

echo json_encode([
    'host_configured' => $host !== false && $host !== '',
    'port_configured' => $port !== false && $port !== '',
    'user_configured' => (bool) getenv('DB_USER'),
    'database_configured' => (bool) getenv('DB_NAME')
]);
