<?php

/**
 * Bootstrap de teste dedicado (referenciado por `phpunit.xml`, no lugar de
 * `vendor/autoload.php` direto).
 *
 * Por que isto existe: o container de backend (docker-compose.yml) define
 * DB_CONNECTION=mysql/DB_HOST=mysql/DB_DATABASE=watch_crm como variáveis de
 * ambiente reais do processo — necessário pra `php artisan serve` funcionar
 * contra o MySQL de dev. `phpunit.xml` já declara `<env ... force="true">`
 * pros valores de teste (sqlite in-memory), mas isso só afeta `getenv()`/
 * `$_ENV` — o Laravel (via `vlucas/phpdotenv`) lê `$_SERVER` primeiro ao
 * resolver `env()`/`config()`, e o PHPUnit NÃO atualiza `$_SERVER`. Sem este
 * arquivo, `php artisan test`/`RefreshDatabase` roda contra o MySQL de dev
 * real e apaga os dados de demonstração (aconteceu de fato durante a
 * TASK-017, confirmado e corrigido).
 *
 * Este bootstrap seta `putenv()` + `$_ENV` + `$_SERVER` nas três formas,
 * ANTES de qualquer coisa (inclusive `vendor/autoload.php`) carregar —
 * garante que a primeira leitura de `env('DB_CONNECTION')` em qualquer
 * bootstrap do Laravel já veja o valor de teste, independente de qual
 * adapter do phpdotenv seja consultado primeiro.
 */
$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'SESSION_DRIVER' => 'array',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($testingEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
