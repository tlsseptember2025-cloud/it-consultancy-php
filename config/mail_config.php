<?php

/**
 * SMTP configuration.
 *
 * Secrets must be stored in .env and never committed to source control.
 * Supported names are SMTP_* and MAIL_* for compatibility with existing
 * deployments.
 */
$envFile = dirname(__DIR__) . '/.env';

if (!is_file($envFile)) {
    throw new RuntimeException('Mail configuration unavailable.');
}

$env = parse_ini_file($envFile, false, INI_SCANNER_RAW);

if ($env === false) {
    throw new RuntimeException('Mail configuration unavailable.');
}

$get = static function (string $primary, string $fallback = '') use ($env): string {
    $value = getenv($primary);
    if ($value !== false && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    $value = $env[$primary] ?? ($fallback !== '' ? ($env[$fallback] ?? '') : '');
    return trim((string) $value);
};

$config = [
    'host' => $get('SMTP_HOST', 'MAIL_HOST'),
    'port' => (int) ($get('SMTP_PORT', 'MAIL_PORT') ?: 587),
    'username' => $get('SMTP_USERNAME', 'MAIL_USERNAME'),
    'password' => $get('SMTP_PASSWORD', 'MAIL_PASSWORD'),
    'from_name' => $get('SMTP_FROM_NAME', 'MAIL_FROM_NAME') ?: 'IT Consultancy',
    'admin_email' => $get('SMTP_ADMIN_EMAIL', 'MAIL_ADMIN_EMAIL'),
];

if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '' || $config['admin_email'] === '') {
    throw new RuntimeException('SMTP configuration is incomplete.');
}

return $config;
