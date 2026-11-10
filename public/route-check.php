<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
    'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
    'PATH_INFO' => $_SERVER['PATH_INFO'] ?? null
], JSON_PRETTY_PRINT);