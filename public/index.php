<?php

use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
use DI\Container;
use Valitron\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

session_start();

$container = new Container();

$container->set('renderer', function () {
    $renderer = new PhpRenderer(__DIR__ . '/../templates');
    $renderer->setLayout('layout.phtml');
    return $renderer;
});

$container->set('flash', function () {
    return new Messages();
});

$container->set(\PDO::class, function () {
    $databaseUrl = getenv('DATABASE_URL');
    if (!$databaseUrl) {
        throw new \Exception("DATABASE_URL not found in environment variables");
    }

    $params = parse_url($databaseUrl);

    $conStr = sprintf(
        "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
        $params['host'],
        $params['port'] ?? 5432,
        ltrim($params['path'], '/'),
        $params['user'],
        $params['pass']
    );

    $pdo = new \PDO($conStr);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $pdo;
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) {
    $messages = $this->get('flash')->getMessages();

    $params = [
        'flash' => $messages
    ];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('home');

$app->post('/urls', function ($request, $response) {
    $urlData = $request->getParsedBodyParam('url');
    $pdo = $this->get(\PDO::class);

    $v = new Validator(['url' => $urlData]);
    $v->rule('required', 'url')->message('URL не должен быть пустым');
    $v->rule('url', 'url')->message('Некорректный URL');
    $v->rule('lengthMax', 'url', 255)->message('URL слишком длинный');

    if (!$v->validate()) {
        $this->get('flash')->addMessage('danger', 'Некорректный URL');

        $params = [
            'url' => ['name' => $urlData],
            'errors' => $v->errors(),
            'flash' => $this->get('flash')->getMessages()
        ];

        return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
    }

    $parsedUrl = parse_url($urlData);
    $normalizedUrl = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";

    $stmt = $pdo->prepare("SELECT id FROM urls WHERE name = ?");
    $stmt->execute([$normalizedUrl]);
    $existingUrl = $stmt->fetch();

    if ($existingUrl) {
        $this->get('flash')->addMessage('info', 'Страница уже существует');
        $id = $existingUrl['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO urls (name, created_at) VALUES (?, ?)");
        $stmt->execute([$normalizedUrl, date('Y-m-d H:i:s')]);
        $id = $pdo->lastInsertId();

        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    }

    return $response->withHeader('Location', "/urls/{$id}")->withStatus(302);
})->setName('urls.store');

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    $pdo = $this->get(\PDO::class);

    $stmt = $pdo->prepare("SELECT * FROM urls WHERE id = ?");
    $stmt->execute([$id]);
    $url = $stmt->fetch();

    if (!$url) {
        return $response->withStatus(404)->write('Страница не найдена');
    }

    $stmt = $pdo->prepare("SELECT * FROM url_checks WHERE url_id = ? ORDER BY id DESC");
    $stmt->execute([$id]);
    $checks = $stmt->fetchAll();

    return $this->get('renderer')->render($response, 'urls/show.phtml', [
        'url' => $url,
        'checks' => $checks,
        'flash' => $this->get('flash')->getMessages()
    ]);
})->setName('urls.show');

$app->get('/urls', function ($request, $response) {
    $pdo = $this->get(\PDO::class);

    $sql = "SELECT urls.*, url_checks.status_code, url_checks.created_at AS last_check
            FROM urls LEFT JOIN (
                SELECT DISTINCT ON (url_id) * FROM url_checks
                ORDER BY url_id, id DESC
            ) AS url_checks
            ON urls.id = url_checks.url_id
            ORDER BY urls.id DESC";

    $urls = $pdo->query($sql)->fetchAll();

    return $this->get('renderer')->render($response, 'urls/index.phtml', [
        'urls' => $urls,
        'flash' => $this->get('flash')->getMessages()
    ]);
})->setName('urls.index');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) {
    $urlId = $args['url_id'];
    $pdo = $this->get(\PDO::class);

    $stmt = $pdo->prepare("SELECT name FROM urls WHERE id = ?");
    $stmt->execute([$urlId]);
    $url = $stmt->fetch();

    $client = new Client(['timeout' => 5.0]);

    try {
        $res = $client->get($url['name']);
        $statusCode = $res->getStatusCode();

        $stmt = $pdo->prepare(
            "INSERT INTO url_checks (url_id, status_code, created_at) VALUES (?, ?, ?)"
        );
        $stmt->execute([$urlId, $statusCode, date('Y-m-d H:i:s')]);

        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (ConnectException | RequestException $e) {
        $this->get('flash')->addMessage('danger', 'Произошла ошибка при проверке');
    }

    return $response->withHeader('Location', "/urls/{$urlId}")->withStatus(302);
});

$app->run();
