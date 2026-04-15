<?php

use Slim\Factory\AppFactory;

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write("Hello, Hexlet!");
    return $response;
});

$app->run();
