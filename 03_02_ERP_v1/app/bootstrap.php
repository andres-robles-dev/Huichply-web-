<?php
declare(strict_types=1);

define('APP_BASE_PATH', dirname(__DIR__));

require_once __DIR__ . '/env.php';
$__ENV = env_load(APP_BASE_PATH . '/.env');

function cfg(string $key, ?string $default = null): ?string
{
    global $__ENV;
    return env_get($__ENV, $key, $default);
}

// Basic error handling
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
