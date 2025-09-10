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
use Illuminate\Database\Capsule\Manager as Capsule;

require __DIR__ . '/vendor/autoload.php';

/**
 * =========================
 * 1) .env 読み込み
 * =========================
 */
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

/**
 * =========================
 * 2) ログ / 時刻
 * =========================
 */
$appName = $_ENV['APP_NAME'] ?? 'NoName';
$now = Carbon::now()->toDateTimeString();

$log = new Logger('flashcard');
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$log->info("App loaded", ['app' => $appName, 'time' => $now]);

// =========================
// 2.5) DB接続 (Eloquent)
// =========================
$capsule = new Capsule;

$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? 'db',
    'database'  => $_ENV['DB_DATABASE'] ?? 'flashcard_db',
    'username'  => $_ENV['DB_USERNAME'] ?? 'admin_user',
    'password'  => $_ENV['DB_PASSWORD'] ?? 'admin_pass',
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();
/**
 * =========================
 * 3) Slim アプリ作成
 * =========================
 */
$app = AppFactory::create();

/**
 * =========================
 * 4) CORS ミドルウェア
 *    - プリフライト(OPTIONS)はここで200を返す
 *    - 全レスポンスにCORSヘッダを付与
 * =========================
 */
$app->add(function (Request $request, $handler): Response {
    // .env: FRONTEND_ORIGINS="https://example.com,http://localhost:5173"
    $originsEnv = $_ENV['FRONTEND_ORIGINS'] ?? 'http://localhost:5173';
    $whitelist = array_values(array_filter(array_map('trim', explode(',', $originsEnv))));
    if (empty($whitelist)) {
        $whitelist = ['http://localhost:5173'];
    }

    $origin = $request->getHeaderLine('Origin');
    $allowOrigin = in_array($origin, $whitelist, true) ? $origin : $whitelist[0];

    // Cookie 等を使う場合は true（デフォルトtrue）
    $allowCredentials = filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'true', FILTER_VALIDATE_BOOL);

    // プリフライト(OPTIONS) はここで即時 200 を返す（どのパスでも有効）
    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        $response = new \Slim\Psr7\Response(200);
        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Credentials', $allowCredentials ? 'true' : 'false')
            ->withHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Vary', 'Origin');
    }

    // 通常リクエスト：次へ
    $response = $handler->handle($request);

    // 全レスポンスにCORSヘッダ付与
    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
        ->withHeader('Access-Control-Allow-Credentials', $allowCredentials ? 'true' : 'false')
        ->withHeader('Access-Control-Expose-Headers', 'Authorization, Content-Type')
        ->withHeader('Vary', 'Origin');
});

/**
 * =========================
 * 5) ルーティング / エラーハンドラ
 * =========================
 */
$app->addRoutingMiddleware();

$app->addErrorMiddleware(
    filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL), // displayErrorDetails
    true,  // logErrors
    true   // logErrorDetails
);

/**
 * =========================
 * 6) ルート定義
 * =========================
 */
$app->get('/', function (Request $request, Response $response) use ($log) {
    $log->info("Root route accessed");
    $response->getBody()->write("Hello Slim with Carbon & Monolog!");
    return $response;
});

$app->get('/users', function (Request $request, Response $response) {
    $users = \App\Models\User::all();
    $response->getBody()->write($users->toJson(JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/health', function (Request $req, Response $res) {
    try {
        $now = Capsule::connection()->selectOne('SELECT NOW() AS now');
        $res->getBody()->write(json_encode(['ok'=>true,'db_time'=>$now->now], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(500)->withHeader('Content-Type','application/json');
    }
});


/**
 * =========================
 * 7) 実行
 * =========================
 */
$app->run();
