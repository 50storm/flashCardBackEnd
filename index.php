<?php
declare(strict_types=1);

ini_set('display_errors', 1);

use Tuupola\Middleware\CorsMiddleware;
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
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Validation\DatabasePresenceVerifier;


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

// =========================
// 2.6) バリデーション初期化
// =========================
$filesystem = new Filesystem();
$loader = new FileLoader($filesystem, __DIR__ . '/resources/lang'); // 言語ファイルのパス
$translator = new Translator($loader, 'ja');
$translator->setFallback('en');

$validatorFactory = new ValidatorFactory($translator);
// unique:users,email を動かす
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
 * 3.5) セッション（★追加：Cookie方式）
 * =========================
 */
session_name('flashcard_sid');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'domain' => '',           // 同一ホストなら空でOK
  'secure' => false,        // 本番は true（HTTPS必須）
  'httponly' => true,
  'samesite' => 'Lax',      // クロスオリジンでCookie送るなら 'None' + secure=true
]);
// セッションを各リクエストで確実に開始
$app->add(function (Request $req, $handler): Response {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    return $handler->handle($req);
});

/**
 * =========================
 * 4) CORS ミドルウェア（tuupola 版）
 * =========================
 */
// .env から許可オリジンを配列化
$origins = array_values(array_filter(array_map("trim",
    explode(",", $_ENV["FRONTEND_ORIGINS"] ?? "http://localhost:3000")
)));

$app->add(new CorsMiddleware([
    // リストに入っている Origin のみ許可（リクエストの Origin が一致した場合にそのまま反映）
    "origin"         => $origins,                                  // ★動的
    "methods"        => ["GET","POST","PUT","PATCH","DELETE","OPTIONS"],
    "headers.allow"  => ["Content-Type","Authorization","X-Requested-With","X-CSRF-Token"],
    "headers.expose" => ["Authorization","Content-Type"],
    "credentials"    => filter_var($_ENV["CORS_ALLOW_CREDENTIALS"] ?? "true", FILTER_VALIDATE_BOOL),
    "cache"          => 86400,
]));

// OPTIONS は 204 を返すだけ（tuupola が CORS ヘッダ付与）
$app->options('/{routes:.+}', fn($req, $res) => $res->withStatus(204));

/**
 * =========================
 * 5) ルーティング / エラーハンドラ
 * =========================
 */
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$app->addErrorMiddleware(
    filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL), // displayErrorDetails
    true,  // logErrors
    true   // logErrorDetails
);

/**
 * =========================
 * 5.5) セッション認証ミドルウェア（★追加）
 * =========================
 */
$sessionAuth = function (Request $req, $handler) {
    if (empty($_SESSION['user_id'])) {
        $r = new \Slim\Psr7\Response(401);
        $r->getBody()->write(json_encode(['ok'=>false,'error'=>'Unauthorized'], JSON_UNESCAPED_UNICODE));
        return $r->withHeader('Content-Type','application/json');
    }
    // 後続で使えるように user_id を属性に乗せる
    $req = $req->withAttribute('user_id', (int)$_SESSION['user_id']);
    return $handler->handle($req);
};

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

// ログイン（セッション開始）
$app->post('/login', function (Request $request, Response $response) use ($log) {
    // 生ボディを文字列で
    $rawBody = (string) $request->getBody();
    $log->debug("Raw body", ['raw' => $rawBody]);

    // パースされたボディ
    $parsed = $request->getParsedBody();
    $log->debug("Parsed body", ['parsed' => $parsed]);

    // ヘッダー一覧
    $log->debug("Headers", ['headers' => $request->getHeaders()]);

    $data = (array)$parsed;

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (!$email || !$password) {
        $log->warning("Login missing fields", ['email' => $email, 'password' => $password]);
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => 'メールアドレスとパスワードを入力してください。',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $user = User::where('email', $email)->first();

    if (!$user) {
        $log->warning("User not found", ['email' => $email]);
    }

    if (!$user || !password_verify($password, $user->password)) {
        $log->warning("Login failed", ['email' => $email]);
        $response->getBody()->write(json_encode([
            'ok' => false,
            'error' => '認証に失敗しました。',
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    // ★ セッションに保存
    $_SESSION['user_id'] = (int)$user->id;

    $log->info("Login success", ['user_id' => $user->id, 'email' => $user->email]);

    $response->getBody()->write(json_encode([
        'ok' => true,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// 作成（ユーザー登録：password 必須 & ハッシュ保存）
$app->post('/user', function (Request $request, Response $response) use ($validatorFactory) {
    $data = (array)$request->getParsedBody();

    $rules = [
        'name'     => 'required|string|max:100',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|max:255',
    ];

    $validator = $validatorFactory->make($data, $rules);

    if ($validator->fails()) {
        $payload = [
            'ok' => false,
            'errors' => $validator->errors()->toArray(),
        ];
        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response
            ->withStatus(422)
            ->withHeader('Content-Type', 'application/json');
    }

    $user = User::create([
        'name'     => $data['name'],
        'email'    => $data['email'],
        'password' => password_hash($data['password'], PASSWORD_BCRYPT),
    ]);

    $response->getBody()->write(json_encode([
        'ok' => true,
        'user' => [
            'id'=>$user->id, 'name'=>$user->name, 'email'=>$user->email,
            'created_at'=>$user->created_at, 'updated_at'=>$user->updated_at
        ]
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// 保護ルート（要ログイン）
$app->get('/me', function (Request $req, Response $res) {
    $userId = (int)$req->getAttribute('user_id');
    $u = User::find($userId);
    if (!$u) {
        $res->getBody()->write(json_encode(['ok'=>false,'error'=>'Not found'], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(404)->withHeader('Content-Type','application/json');
    }
    $res->getBody()->write(json_encode(['ok'=>true,'user'=>[
        'id'=>$u->id,'name'=>$u->name,'email'=>$u->email
    ]], JSON_UNESCAPED_UNICODE));
    return $res->withHeader('Content-Type','application/json');
})->add($sessionAuth);

// ログアウト（セッション破棄）
$app->post('/logout', function (Request $req, Response $res) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    $res->getBody()->write(json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE));
    return $res->withHeader('Content-Type','application/json');
});

// 一覧（モデル版）
$app->get('/users', function (Request $request, Response $response) {
    $users = User::orderBy('id')->get();
    $response->getBody()->write(
        $users->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
    return $response->withHeader('Content-Type', 'application/json');
});

// ヘルスチェック
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
