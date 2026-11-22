<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$token = (string) ($_GET['token'] ?? '');
if (! hash_equals('smdtr_mail_20261122_k7Qp9Xm2', $token)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    if (Illuminate\Support\Facades\Schema::hasTable('email_change_requests')) {
        echo "TABLE_EXISTS\n";
    } else {
        $exit = Illuminate\Support\Facades\Artisan::call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_11_22_142752_create_email_change_requests_table.php',
        ]);
        echo 'MIGRATE_EXIT='.$exit."\n";
        echo Illuminate\Support\Facades\Artisan::output();
        echo Illuminate\Support\Facades\Schema::hasTable('email_change_requests') ? "TABLE_OK\n" : "TABLE_MISSING\n";
    }
} catch (Throwable $e) {
    echo 'FAIL '.$e->getMessage()."\n";
}

@unlink(__FILE__);
echo "SCRIPT_REMOVED\n";
