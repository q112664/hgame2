<?php

use Illuminate\Filesystem\Filesystem;

test('docker compose defaults to postgresql', function () {
    $filesystem = app(Filesystem::class);
    $compose = $filesystem->get(base_path('docker-compose.yml'));
    $dockerfile = $filesystem->get(base_path('Dockerfile'));

    expect($compose)
        ->toContain('DB_CONNECTION: pgsql')
        ->toContain('DB_HOST: postgres')
        ->toContain('DB_PORT: 5432')
        ->toContain('image: postgres:16')
        ->toContain('postgres_data:')
        ->not->toContain('DB_CONNECTION: mysql')
        ->not->toContain('image: mysql:');

    expect($dockerfile)->toContain('pgsql');
});

test('docker compose includes redis for cache queue and session', function () {
    $filesystem = app(Filesystem::class);
    $compose = $filesystem->get(base_path('docker-compose.yml'));
    $dockerfile = $filesystem->get(base_path('Dockerfile'));
    $envExample = $filesystem->get(base_path('.env.example'));
    $deployDoc = $filesystem->get(base_path('docs/1panel-deploy.md'));

    expect($compose)
        ->toContain('image: redis:7-alpine')
        ->toContain('redis_data:')
        ->toContain('REDIS_HOST: redis')
        ->toContain('CACHE_STORE: redis')
        ->toContain('QUEUE_CONNECTION: redis')
        ->toContain('SESSION_DRIVER: redis')
        ->toContain('queue:work')
        ->toContain('scheduler:')
        ->toContain('schedule:work');

    expect($dockerfile)->toContain('redis');

    expect($envExample)
        ->toContain('REDIS_CLIENT=phpredis')
        ->toContain('REDIS_HOST=127.0.0.1');

    expect($deployDoc)
        ->toContain('Redis')
        ->toContain('REDIS_HOST=redis')
        ->toContain('CACHE_STORE=redis')
        ->toContain('已部署环境升级：加上 Redis')
        ->toContain('docker compose up -d --build');
});
