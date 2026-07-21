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
