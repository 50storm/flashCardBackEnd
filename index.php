<?php
declare(strict_types=1);

ini_set('display_errors', 1);

use Dotenv\Exception\InvalidPathException;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Carbon\Carbon;
use App\Models\User;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Validation\DatabasePresenceVerifier;

require __DIR__ . '/vendor/autoload.php';
// require __DIR__ . '/src/vendor/autoload.php';

/**
 * =========================
 * 1) .env 読み込み
 * =========================
 */
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (InvalidPathException $e) {
    dd($e->getMessage());
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

// =========================
// 2.6) バリデーション初期化
// =========================
$filesystem = new Filesystem();
$loader = new FileLoader($filesystem, __DIR__ . '/resources/lang'); // 言語ファイルのパス
$translator = new Translator($loader, 'ja');
$translator->setFallback('en');
$validatorFactory = new ValidatorFactory($translator);
// DB接続をバリデータにセット
$validatorFactory->setPresenceVerifier(
    new DatabasePresenceVerifier($capsule->getDatabaseManager())
);

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

    // Coresultie 等を使う場合は true（デフォルトtrue）
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

// ★ ここで BodyParsing を追加
$app->addBodyParsingMiddleware();

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

// ユーザー一覧（Modelなし）
// $app->get('/users', function (Request $request, Response $response) {
//     try {
//         $rows = Capsule::table('users')
//             ->select('id', 'name', 'email', 'created_at')
//             ->orderBy('id', 'asc')
//             ->get();

//         $response->getBody()->write($rows->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
//         return $response->withHeader('Content-Type', 'application/json');
//     } catch (\Throwable $e) {
//         $payload = ['result' => false, 'error' => $e->getMessage()];
//         $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
//         return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
//     }
// });

// TODO 
// ユーザー作成のサンプルも追加する
// 作成（サンプル）
// 作成
$app->post('/user', function (Request $request, Response $response) use ($validatorFactory) {
    $data = (array)$request->getParsedBody();

    // バリデーションルール
    $rules = [
        'name'  => 'required|string|max:100',
        'email' => 'required|email|unique:users,email',
    ];

    // 実行
    $validator = $validatorFactory->make($data, $rules);

    if ($validator->fails()) {
        $errors = $validator->errors()->all();
        $response->getBody()->write(json_encode([
            'ok' => false,
            'errors' => $errors
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // OK → 登録
    $user = User::create([
        'name'  => $data['name'],
        'email' => $data['email'],
    ]);

    $response->getBody()->write(json_encode([
        'ok' => true,
        'user' => $user
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});


// 一覧（モデル版）
$app->get('/users', function (Request $request, Response $response) {
    $users = User::orderBy('id')->get();
        // dd("here");

    $response->getBody()->write(
        $users->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    return $response->withHeader('Content-Type', 'application/json');
});



// $app->get('/users', function (Request $request, Response $response) {
//     try {
//         $rows = Capsule::table('users')
//             ->select('id','name','email','created_at')
//             ->orderBy('id','asc')
//             ->get();

//         $response->getBody()->write($rows->toJson(JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
//         return $response->withHeader('Content-Type','application/json');
//     } catch (Throwable $e) {
//         $payload = ['result'=>false, 'error'=>$e->getMessage()];
//         $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
//         return $response->withStatus(500)->withHeader('Content-Type','application/json');
//     }
// });

$app->get('/health', function (Request $req, Response $res) {
    try {
        $now = Capsule::connection()->selectOne('SELECT NOW() AS now');
        $res->getBody()->write(json_encode(['result'=>true,'db_time'=>$now->now], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    } catch (Throwable $e) {
        $res->getBody()->write(json_encode(['result'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(500)->withHeader('Content-Type','application/json');
    }
});


/**
 * =========================
 * 7) 実行
 * =========================
 */
$app->run();
