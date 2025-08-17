<?php

declare(strict_types=1);

use DI\Container;
use Dotenv\Dotenv;
use Predis\Client as RedisClient;
use Slim\Factory\AppFactory;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessFallbackPaymentAction;
use RinhaSlim\App\Controllers\Payment\PaymentController;
use RinhaSlim\App\Controllers\Payment\PaymentSummaryController;
use RinhaSlim\App\Actions\Queue\EnqueuePaymentAction;
use RinhaSlim\App\Infrastructure\Http\HttpClientService;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$container = new Container();
$container->set('payment.main_url', $_ENV['PAYMENT_MAIN_URL']);
$container->set('payment.fallback_url', $_ENV['PAYMENT_FALLBACK_URL']);
$container->set('redis.host', $_ENV['REDIS_HOST']);
$container->set('redis.port', (int)$_ENV['REDIS_PORT']);

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

$container->set(RedisClient::class, function ($container) {
    return new RedisClient([
        'scheme' => 'tcp',
        'host'   => $container->get('redis.host'),
        'port'   => $container->get('redis.port'),
    ]);
});

$container->set(EnqueuePaymentAction::class, function ($container) {
    return new EnqueuePaymentAction($container->get(RedisClient::class));
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$app->post('/payments', PaymentController::class);

$app->get('/payments-summary', PaymentSummaryController::class);

$app->run();
