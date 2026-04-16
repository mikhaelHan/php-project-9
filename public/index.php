<?php

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

$app = AppFactory::create();

$renderer = new PhpRenderer(__DIR__ . '/../templates');
$renderer->setLayout('layout.phtml');

$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) use ($renderer) {
    return $renderer->render($response, 'index.phtml');
});

$app->run();
