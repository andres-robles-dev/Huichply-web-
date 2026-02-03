<?php
declare(strict_types=1);

function env_load(string $path): array
{
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value, '"\' ');
    }
    return $env;
}

function env_get(array $env, string $key, $default = null)
{
    return $env[$key] ?? $default;
}
