<?php
declare(strict_types=1);

return [
    'db' => [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'name'     => getenv('DB_NAME') ?: 'otohasar',
        'user'     => getenv('DB_USER') ?: 'otohasar',
        'password' => getenv('DB_PASSWORD') ?: 'otohasar_secret',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'name'          => 'OTOHASAR',
        'url'           => getenv('APP_URL') ?: 'https://otohasar.neciparmagan.net.tr',
        'timezone'      => 'Europe/Istanbul',
        'upload_max'    => 20 * 1024 * 1024, // 20MB
        'token_ttl'     => 30 * 24 * 3600,   // 30 days
        'asset_version' => '41',
    ],
    'paths' => [
        'uploads' => __DIR__ . '/../public/uploads',
    ],
];
