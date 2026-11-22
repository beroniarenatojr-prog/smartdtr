<?php

/**
 * One-shot production mail bootstrap. Self-deletes after a successful run.
 * Remove from the repo immediately after use.
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$expected = 'smdtr_mail_20261122_k7Qp9Xm2';

if (! hash_equals($expected, $token)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$user = trim((string) ($_POST['user'] ?? $_GET['user'] ?? ''));
$pass = trim((string) ($_POST['pass'] ?? $_GET['pass'] ?? ''));
$from = trim((string) ($_POST['from'] ?? $_GET['from'] ?? $user));

if ($user === '' || $pass === '') {
    http_response_code(422);
    echo "Missing user/pass\n";
    exit;
}

$root = dirname(__DIR__);
$envPath = $root.'/.env';

if (! is_file($envPath) || ! is_writable($envPath)) {
    http_response_code(500);
    echo "Cannot write .env at {$envPath}\n";
    exit;
}

$env = file_get_contents($envPath);
if ($env === false) {
    http_response_code(500);
    echo "Failed to read .env\n";
    exit;
}

$replacements = [
    'MAIL_MAILER' => 'smtp',
    'MAIL_HOST' => 'smtp.gmail.com',
    'MAIL_PORT' => '587',
    'MAIL_USERNAME' => $user,
    'MAIL_PASSWORD' => $pass,
    'MAIL_FROM_ADDRESS' => $from,
    'MAIL_ENCRYPTION' => 'tls',
];

foreach ($replacements as $key => $value) {
    $line = $key.'='.$value;
    if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $env)) {
        $env = preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $env, 1);
    } else {
        $env = rtrim($env)."\n{$line}\n";
    }
}

// Drop empty MAIL_SCHEME if present — Laravel SMTP uses encryption/tls.
$env = preg_replace('/^MAIL_SCHEME=.*\n?/m', '', $env) ?? $env;

if (file_put_contents($envPath, $env) === false) {
    http_response_code(500);
    echo "Failed to write .env\n";
    exit;
}

$configCache = $root.'/bootstrap/cache/config.php';
if (is_file($configCache)) {
    @unlink($configCache);
}

$template = $root.'/storage/app/templates/LOCATOR-SHS.docx';
$templateOk = is_file($template) ? 'yes' : 'no';

@unlink(__FILE__);

echo "MAIL_OK user={$user}\n";
echo "CONFIG_CACHE_CLEARED\n";
echo "LOCATOR_TEMPLATE={$templateOk}\n";
echo "BOOTSTRAP_REMOVED\n";
