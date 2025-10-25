<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);

use Tuupola\Middleware\CorsMiddleware;
use Dotenv\Exception\InvalidPathException;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Carbon\Carbon;
use App\Models\User;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\FlashCardController;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Database\Schema\Blueprint;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DI\Container;

require __DIR__ . '/vendor/autoload.php';

/* =========================
 * JWT: アクセストークン生成
 * ========================= */
function makeAccessToken(object $user): string
{
    $now = time();
    $ttl = (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900);
    $payload = [
        'iss'   => $_ENV['JWT_ISS'] ?? 'flashcards-api',
        'aud'   => $_ENV['JWT_AUD'] ?? 'flashcards-client',
        'iat'   => $now,
        'nbf'   => $now,
        'exp'   => $now + $ttl,
        'sub'   => (int)$user->id,
        'email' => (string)$user->email,
    ];
    return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
}

/* =========================
 * 1) .env 読み込み
 * ========================= */
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (InvalidPathException $e) {
    error_log('⚠️ .env file not found or not readable: ' . $e->getMessage());
    $_ENV['APP_ENV']   = $_ENV['APP_ENV'] ?? 'production';
    $_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? false;
    echo("please check dotenv\n");
    die();
}

JWT::$leeway = (int)($_ENV['JWT_LEEWAY'] ?? 60);

/* =========================
 * 2) ログ / 時刻
 * ========================= */
$appName = $_ENV['APP_NAME'] ?? 'NoName';
$nowText = Carbon::now()->toDateTimeString();

$log = new Logger('flashcard');
// $log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
// logs ディレクトリがなければ作成しておく
$log->pushHandler(new StreamHandler(__DIR__ . '/logs/debug.log', Logger::DEBUG));
$log->info('App loaded', ['app' => $appName, 'time' => $nowText]);

/* =========================
 * 2.5) DB接続 (Eloquent)
 * ========================= */
$capsule = new Capsule();
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

/* =========================
 * 2.6) バリデーション初期化
 * ========================= */
$filesystem = new Filesystem();
$loader = new FileLoader($filesystem, __DIR__ . '/resources/lang');
$translator = new Translator($loader, 'ja');
$translator->setFallback('en');
$validatorFactory = new ValidatorFactory($translator);
$validatorFactory->setPresenceVerifier(new DatabasePresenceVerifier($capsule->getDatabaseManager()));

/* =========================
 * 2.7) Schema: flash_cards 作成（なければ）
 * ========================= */
$hasUsersTable = Capsule::schema()->hasTable('users');
if (!Capsule::schema()->hasTable('flash_cards')) {
    Capsule::schema()->create('flash_cards', function (Blueprint $table) use ($hasUsersTable) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('user_id')->nullable()->index();
        $table->string('front', 500);
        $table->string('back', 500);
        $table->timestamps();
        $table->softDeletes();
        if ($hasUsersTable) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        }
    });
}

/* =========================
 * 3) Slim アプリ作成
 * ========================= */
// 追加（AppFactoryより前に）
$container = new Container();
$container->set(ValidatorFactory::class, fn() => $validatorFactory);
AppFactory::setContainer($container);
$app = AppFactory::create();

/* =========================
 * 4) CORS（tuupola）
 * ========================= */
$origins = array_values(array_filter(array_map('trim', explode(',', $_ENV['FRONTEND_ORIGINS'] ?? 'http://localhost:3000'))));
$app->add(new CorsMiddleware([
    'origin'         => $origins,
    'methods'        => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
    'headers.allow'  => ['Content-Type','Authorization','X-Requested-With','X-CSRF-Token'],
    'headers.expose' => ['Authorization','Content-Type'],
    'credentials'    => filter_var($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'true', FILTER_VALIDATE_BOOL),
    'cache'          => 86400,
]));
$app->options('/{routes:.+}', fn($req, $res) => $res->withStatus(204));

/* =========================
 * 5) ルーター & エラー
 * ========================= */
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    true,
    true
);

// ベースパス設定(XSERVER用)
$basePath = $_ENV['APP_BASE_PATH'] ?? '';

if (!empty($basePath)) {
    $app->setBasePath($basePath);
} elseif ($_ENV['APP_ENV'] === 'production') {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'ok'    => false,
        'error' => 'APP_BASE_PATH is not set in .env (production only)'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$app->setBasePath($basePath);


/* =========================
 * 5.5) 認証ミドルウェア
 * ========================= */
$jwtAuth = function (Request $req, $handler) {
    $auth = $req->getHeaderLine('Authorization');
    if (!preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
        $r = new \Slim\Psr7\Response(401);
        $r->getBody()->write(json_encode(['ok' => false,'error' => 'Missing bearer token'], JSON_UNESCAPED_UNICODE));
        return $r->withHeader('Content-Type', 'application/json');
    }
    try {
        $decoded = JWT::decode($m[1], new Key($_ENV['JWT_SECRET'], 'HS256'));
        if (($decoded->iss ?? null) !== ($_ENV['JWT_ISS'] ?? 'flashcards-api')) {
            throw new \RuntimeException('bad iss');
        }
        if (($decoded->aud ?? null) !== ($_ENV['JWT_AUD'] ?? 'flashcards-client')) {
            throw new \RuntimeException('bad aud');
        }
        $req = $req->withAttribute('user_id', (int)$decoded->sub);
        return $handler->handle($req);
    } catch (\Throwable $e) {
        $r = new \Slim\Psr7\Response(401);
        $r->getBody()->write(json_encode(['ok' => false,'error' => 'Invalid token'], JSON_UNESCAPED_UNICODE));
        return $r->withHeader('Content-Type', 'application/json');
    }
};

$adminOnly = function (Request $req, $handler) {
    $userId = (int)$req->getAttribute('user_id');
    $user = User::find($userId);

    if (!$user || !$user->is_admin) {
        $res = new \Slim\Psr7\Response(403);
        $res->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'Admin access only'
        ], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    return $handler->handle($req);
};


/* =========================
 * 6) ルート定義
 * ========================= */
$app->get('/', function (Request $request, Response $response) use ($log) {
    $log->info('Root route accessed');
    $response->getBody()->write('Hello Slim with Carbon & Monolog!');
    return $response;
});

$app->get('/test', function ($req, $res) use ($log) {
    $log->info('✅ /test reached!');
    $res->getBody()->write('Hello from Slim!');
    return $res;
});
$app->get('/auth/test', function ($req, $res) use ($log) {
    $log->info('✅ /auth/test reached!');
    $res->getBody()->write('Hello from Slim!');
    return $res;
});


/**
 * --- ユーザー登録 ---
 * POST /auth/register
    * {     "name": "ユーザー名",
    *       "email": "メールアドレス",
    *       "password": "パスワード" }
    * => 201 Created
    * { "ok": true,
    *   "access_token": "xxxx", "token_type": "Bearer", "expires_in": 900,
    *   "user": { "id": 1, "name": "ユーザー名", "email": "メールアドレス" } }
    * => 422 Unprocessable Entity
    * { "ok": false, "errors": { "email": ["The email has already been taken."] } }
 */
/* --- 認証系ルートをAuthControllerに委譲 --- */
$app->post('/auth/register', [AuthController::class, 'register']);

/* --- JWT 認証系 --- */
$app->post('/auth/login', [AuthController::class, 'login']);

/* --- ユーザー系ルートをUserControllerに委譲 --- */
$app->group('/api/users', function ($group) use ($adminOnly) {
    // 自分の情報
    $group->get('/me', [UserController::class, 'me']);
    $group->put('/me', [UserController::class, 'updateMe']);

    // 管理者専用一覧
    $group->get('', [UserController::class, 'index'])->add($adminOnly);
})->add($jwtAuth);

/* --- JWT版 FlashCards --- */
$app->group('/api/flash-cards', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('', FlashCardController::class . ':index');
    $group->post('', FlashCardController::class . ':store');
    $group->get('/{id}', FlashCardController::class . ':show');
    $group->put('/{id}', FlashCardController::class . ':update');
    $group->patch('/{id}', FlashCardController::class . ':update');
    $group->delete('/{id}', FlashCardController::class . ':delete');
    $group->post('/{id}/restore', FlashCardController::class . ':restore');
})->add($jwtAuth);

/* --- ヘルスチェック --- */
$app->get('/health', function (Request $req, Response $res) {
    try {
        $now = Capsule::connection()->selectOne('SELECT NOW() AS now');
        $res->getBody()->write(json_encode(['result' => true,'db_time' => $now->now], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $res->getBody()->write(json_encode(['result' => false,'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
});

/* =========================
 * 7) 実行
 * ========================= */
$app->run();
$routes = $app->getRouteCollector()->getRoutes();
foreach ($routes as $r) {
    $pattern = $r->getPattern();
    $methods = $r->getMethods();
    $log->info("[ROUTE] {$pattern} " . implode(',', $methods));
}
