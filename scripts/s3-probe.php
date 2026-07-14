<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = $argv[1] ?? 'serp-screenshots/task41-probe.txt';
$content = 'probe-'.date('c');

try {
    $put = Illuminate\Support\Facades\Storage::disk('s3')->put($path, $content);
    $read = Illuminate\Support\Facades\Storage::disk('s3')->get($path);

    echo json_encode([
        'put' => $put,
        'read_length' => is_string($read) ? strlen($read) : null,
        'read_matches' => $read === $content,
        'path' => $path,
    ], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode([
        'error' => $exception->getMessage(),
        'path' => $path,
    ], JSON_PRETTY_PRINT).PHP_EOL;
    exit(1);
}
