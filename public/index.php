<?php

declare(strict_types=1);

use DI\Container;
use RinhaSlim\App\Controllers\Payment\PaymentController;
use RinhaSlim\App\Controllers\Payment\PaymentSummaryController;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$app->post('/payments', PaymentController::class);

$app->get('/payments-summary', PaymentSummaryController::class);

$app->run();
