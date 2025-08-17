<?php

declare(strict_types=1);

use DI\Container;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessFallbackPaymentAction;
use RinhaSlim\App\Actions\Queue\ProcessAsyncPaymentAction;
use RinhaSlim\App\Infrastructure\Http\HttpClientService;

require __DIR__ . '/../vendor/autoload.php';  // Note: go up one level to find vendor

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');  // Note: go up one level to find .env
$dotenv->load();

$container = new Container();

// Environment configurations
$container->set('payment.main_url', $_ENV['PAYMENT_MAIN_URL']);
$container->set('payment.fallback_url', $_ENV['PAYMENT_FALLBACK_URL']);
$container->set('redis.host', $_ENV['REDIS_HOST']);
$container->set('redis.port', (int)$_ENV['REDIS_PORT']);

// Register Redis client
$container->set(RedisClient::class, function ($container) {
    return new RedisClient([
        'scheme' => 'tcp',
        'host'   => $container->get('redis.host'),
        'port'   => $container->get('redis.port'),
    ]);
});

// Register payment actions
$container->set(ProcessPaymentAction::class, function ($container) {
    return new ProcessPaymentAction(
        $container->get('payment.main_url'),
        $container->get(HttpClientService::class)
    );
});

$container->set(ProcessFallbackPaymentAction::class, function ($container) {
    return new ProcessFallbackPaymentAction(
        $container->get('payment.fallback_url'),
        $container->get(HttpClientService::class)
    );
});

// Register async processor
$container->set(ProcessAsyncPaymentAction::class, function ($container) {
    return new ProcessAsyncPaymentAction(
        $container->get(RedisClient::class),
        $container->get(ProcessPaymentAction::class),
        $container->get(ProcessFallbackPaymentAction::class)
    );
});

// Start the worker
echo "Starting payment worker...\n";
$worker = $container->get(ProcessAsyncPaymentAction::class);
$worker->execute();