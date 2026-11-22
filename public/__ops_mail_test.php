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

$to = (string) ($_GET['to'] ?? config('mail.from.address'));
$user = (string) config('mail.mailers.smtp.username');

try {
    Illuminate\Support\Facades\Mail::raw(
        'SMART-DTR production mail check at '.now()->toDateTimeString(),
        function ($message) use ($to) {
            $message->to($to)->subject('SMART-DTR production mail OK');
        }
    );
    echo "SEND_OK from_config_user={$user} to={$to}\n";
} catch (Throwable $e) {
    echo 'SEND_FAIL '.$e->getMessage()."\n";
}

@unlink(__FILE__);
echo "TEST_REMOVED\n";
