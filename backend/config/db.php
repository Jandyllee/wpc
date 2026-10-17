<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$serverName = strtolower($_SERVER['SERVER_NAME'] ?? '');
$isLocalDevelopment = PHP_SAPI === 'cli'
    || in_array($serverName, ['localhost', '127.0.0.1', '::1'], true);
$privateConfig = [];

if (!$isLocalDevelopment) {
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if (!is_string($documentRoot) || $documentRoot === '') {
        throw new RuntimeException('Production document root is unavailable.');
    }

    $privateConfigPath = dirname(rtrim($documentRoot, DIRECTORY_SEPARATOR))
        . DIRECTORY_SEPARATOR . 'wpc-private-config.php';
    $privateConfig = [];
    if (is_file($privateConfigPath)) {
        $privateConfig = require $privateConfigPath;
        if (!is_array($privateConfig)) {
            throw new RuntimeException('Production private configuration is invalid.');
        }
    }
}

function appConfigValue(string $key, ?string $default = null): ?string
{
    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    global $privateConfig;
    $privateValue = $privateConfig[$key] ?? null;
    return is_string($privateValue) && $privateValue !== '' ? $privateValue : $default;
}

if ($isLocalDevelopment) {
    $dbHost = appConfigValue('WPC_DB_HOST', '127.0.0.1');
    $dbUser = appConfigValue('WPC_DB_USER', 'root');
    $dbPassword = appConfigValue('WPC_DB_PASSWORD', '');
    $dbName = appConfigValue('WPC_DB_NAME', 'wpc');
} else {
    $dbHost = appConfigValue('WPC_DB_HOST');
    $dbUser = appConfigValue('WPC_DB_USER');
    $dbPassword = appConfigValue('WPC_DB_PASSWORD');
    $dbName = appConfigValue('WPC_DB_NAME');
    if (!is_string($dbHost) || $dbHost === ''
        || !is_string($dbUser) || $dbUser === ''
        || !is_string($dbPassword) || $dbPassword === ''
        || !is_string($dbName) || $dbName === '') {
        throw new RuntimeException('Production database environment is incomplete.');
    }
}

$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
$conn->set_charset('utf8mb4');
