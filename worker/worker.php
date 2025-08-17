<?php

declare(strict_types=1);

use DI\Container;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;
use RinhaSlim\App\Actions\Queue\ProcessAsyncPaymentAction;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessFallbackPaymentAction;
use RinhaSlim\App\Actions\Payment\StorePaymentAction;
use RinhaSlim\App\Infrastructure\Http\HttpClientService;
use RinhaSlim\App\Infrastructure\Repository\PdoPaymentRepository;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();

// Environment variables
$container->set('payment.main_url', $_ENV['PAYMENT_MAIN_URL']);
$container->set('payment.fallback_url', $_ENV['PAYMENT_FALLBACK_URL']);
$container->set('redis.host', $_ENV['REDIS_HOST']);
$container->set('redis.port', (int)$_ENV['REDIS_PORT']);

// Database configuration
$container->set('db.dsn', $_ENV['DB_DSN'] ?? 'pgsql:host=postgres;dbname=payments');
$container->set('db.username', $_ENV['DB_USER'] ?? 'payment_user');
$container->set('db.password', $_ENV['DB_PASS'] ?? 'payment_pass');

// HTTP Client Service
$container->set(HttpClientService::class, function () {
    return new HttpClientService();
});

// Redis Client
$container->set(RedisClient::class, function ($container) {
    return new RedisClient([
        'scheme' => 'tcp',
        'host'   => $container->get('redis.host'),
        'port'   => $container->get('redis.port'),
    ]);
});

// Database Repository
$container->set(PdoPaymentRepository::class, function ($container) {
    return new PdoPaymentRepository(
        $container->get('db.dsn'),
        $container->get('db.username'),
        $container->get('db.password')
    );
});

// Store Payment Action
$container->set(StorePaymentAction::class, function ($container) {
    return new StorePaymentAction(
        $container->get(PdoPaymentRepository::class)
    );
});

// Payment Actions (with storage)
$container->set(ProcessPaymentAction::class, function ($container) {
    return new ProcessPaymentAction(
        $container->get('payment.main_url'),
        $container->get(HttpClientService::class),
        $container->get(StorePaymentAction::class)
    );
});

$container->set(ProcessFallbackPaymentAction::class, function ($container) {
    return new ProcessFallbackPaymentAction(
        $container->get('payment.fallback_url'),
        $container->get(HttpClientService::class),
        $container->get(StorePaymentAction::class)
    );
});

// Async Payment Action
$container->set(ProcessAsyncPaymentAction::class, function ($container) {
    return new ProcessAsyncPaymentAction(
        $container->get(RedisClient::class),
        $container->get(ProcessPaymentAction::class),
        $container->get(ProcessFallbackPaymentAction::class)
    );
});

// Execute worker
$worker = $container->get(ProcessAsyncPaymentAction::class);
$worker->execute();