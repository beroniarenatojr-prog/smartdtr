<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$token = (string) ($_GET['token'] ?? '');
if (! hash_equals('smdtr_verify_20261122_n4Rt8Wk1', $token)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    if (Illuminate\Support\Facades\Schema::hasColumn('staff_users', 'email_verified_at')) {
        echo "COLUMN_EXISTS\n";
    } else {
        $exit = Illuminate\Support\Facades\Artisan::call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_11_22_151950_add_email_verification_to_staff_users_table.php',
        ]);
        echo 'MIGRATE_EXIT='.$exit."\n";
        echo Illuminate\Support\Facades\Artisan::output();
        echo Illuminate\Support\Facades\Schema::hasColumn('staff_users', 'email_verified_at') ? "COLUMN_OK\n" : "COLUMN_MISSING\n";
    }
} catch (Throwable $e) {
    echo 'FAIL '.$e->getMessage()."\n";
}

@unlink(__FILE__);
echo "SCRIPT_REMOVED\n";
