<?php
declare(strict_types=1);

use Dotenv\Exception\InvalidPathException;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Carbon\Carbon;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/vendor/autoload.php';

// .env 読み込み
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (InvalidPathException $e) {
    error_log('⚠️ .env file not found or not readable: ' . $e->getMessage());
    $_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'production';
    $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? false;
    echo("please check dotenv");
    die();
}

$appName = $_ENV['APP_NAME'] ?? 'NoName';

// === Carbon で時刻処理 ===
$now = Carbon::now()->toDateTimeString();

// === Monolog 設定 ===
$log = new Logger('flashcard');
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$log->info("App loaded", ['app' => $appName, 'time' => $now]);

// Slim アプリ作成
$app = AppFactory::create();

/**
 * ====== CORS ミドルウェア ======
 */

// プリフライト（OPTIONS）用のルート
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response->withStatus(200);
});

// CORS ヘッダ付与
$app->add(function (Request $request, $handler): Response {
    $response = $handler->handle($request);

    $origin = $request->getHeaderLine('Origin');
    $whitelist = [
        'https://your-frontend.example.com',
        'http://localhost:5173',
    ];
    $allowOrigin = in_array($origin, $whitelist, true) ? $origin : $whitelist[0];

    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
        ->withHeader('Access-Control-Allow-Credentials', 'true')
        ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With')
        ->withHeader('Access-Control-Expose-Headers', 'Authorization, Content-Type')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Vary', 'Origin');
});

/**
 * ====== ルート定義 ======
 */
$app->get('/', function (Request $request, Response $response) use ($log) {
    $log->info("Root route accessed");
    $response->getBody()->write("Hello Slim with Carbon & Monolog!");
    return $response;
});

// 実行
$app->run();
