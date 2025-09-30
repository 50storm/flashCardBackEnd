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
use App\Models\FlashCard;
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

require __DIR__ . '/vendor/autoload.php';

/* =========================
 * JWT: アクセストークン生成
 * ========================= */
function makeAccessToken(object $user): string {
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
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$log->info('App loaded', ['app' => $appName, 'time' => $nowText]);

/* =========================
 * 2.5) DB接続 (Eloquent)
 * ========================= */
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

if (empty($basePath)) {
    header('Content-Type: application/json', true, 500);
    echo json_encode([
        'ok'    => false,
        'error' => 'APP_BASE_PATH is not set in .env'
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
        $r->getBody()->write(json_encode(['ok'=>false,'error'=>'Missing bearer token'], JSON_UNESCAPED_UNICODE));
        return $r->withHeader('Content-Type','application/json');
    }
    try {
        $decoded = JWT::decode($m[1], new Key($_ENV['JWT_SECRET'], 'HS256'));
        if (($decoded->iss ?? null) !== ($_ENV['JWT_ISS'] ?? 'flashcards-api')) throw new \RuntimeException('bad iss');
        if (($decoded->aud ?? null) !== ($_ENV['JWT_AUD'] ?? 'flashcards-client')) throw new \RuntimeException('bad aud');
        $req = $req->withAttribute('user_id', (int)$decoded->sub);
        return $handler->handle($req);
    } catch (\Throwable $e) {
        $r = new \Slim\Psr7\Response(401);
        $r->getBody()->write(json_encode(['ok'=>false,'error'=>'Invalid token'], JSON_UNESCAPED_UNICODE));
        return $r->withHeader('Content-Type','application/json');
    }
};

/* =========================
 * 6) ルート定義
 * ========================= */
$app->get('/', function (Request $request, Response $response) use ($log) {
    $log->info('Root route accessed');
    $response->getBody()->write('Hello Slim with Carbon & Monolog!');
    return $response;
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
$app->post('/auth/register', function (Request $request, Response $response) use ($validatorFactory) {
    $data = (array)$request->getParsedBody();

    // バリデーション定義
    $v = $validatorFactory->make($data, [
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|max:255',
    ]);

    if ($v->fails()) {
        $response->getBody()->write(json_encode(['ok' => false, 'errors' => $v->errors()->toArray()], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
    }

    // パスワードをハッシュしてユーザー作成
    $user = User::create([
        'name'     => $data['name'],
        'email'    => $data['email'],
        'password' => password_hash($data['password'], PASSWORD_DEFAULT),
    ]);

    $token = makeAccessToken($user);

    $response->getBody()->write(json_encode([
        'ok'           => true,
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'expires_in'   => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
        'user'         => ['id'=>$user->id, 'name'=>$user->name, 'email'=>$user->email],
    ], JSON_UNESCAPED_UNICODE));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});


/* --- JWT 認証系 --- */
$app->post('/auth/login', function (Request $request, Response $response) {
    $data = (array)$request->getParsedBody();
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (!$email || !$password) {
        $response->getBody()->write(json_encode(['ok'=>false,'error'=>'メールとパスワード必須'], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(400)->withHeader('Content-Type','application/json');
    }

    $user = User::where('email', $email)->first();
    if (!$user || !password_verify($password, $user->password)) {
        $response->getBody()->write(json_encode(['ok'=>false,'error'=>'認証に失敗しました'], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(401)->withHeader('Content-Type','application/json');
    }

    $token = makeAccessToken($user);

    $response->getBody()->write(json_encode([
        'ok'           => true,
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'expires_in'   => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
        'user'         => ['id'=>$user->id,'name'=>$user->name,'email'=>$user->email],
    ], JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type','application/json');
});

$app->get('/api/me', function (Request $req, Response $res) {
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
})->add($jwtAuth);

/* --- JWT版 FlashCards --- */
$app->group('/api/flash-cards', function (\Slim\Routing\RouteCollectorProxy $group) use ($validatorFactory) {
    $group->get('', function (Request $req, Response $res) {
        $userId = (int)$req->getAttribute('user_id');
        $cards = FlashCard::where('user_id', $userId)->orderByDesc('id')->get();
        $res->getBody()->write($cards->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    });

    $group->post('', function (Request $req, Response $res) use ($validatorFactory) {
        $userId = (int)$req->getAttribute('user_id');
        $data   = (array)$req->getParsedBody();

        $v = $validatorFactory->make($data, [
            'front' => 'required|string|max:500',
            'back'  => 'required|string|max:500',
        ]);
        if ($v->fails()) {
            $res->getBody()->write(json_encode(['ok'=>false,'errors'=>$v->errors()->toArray()], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(422)->withHeader('Content-Type','application/json');
        }

        $card = FlashCard::create([
            'user_id' => $userId,
            'front'   => $data['front'],
            'back'    => $data['back'],
        ]);

        $res->getBody()->write(json_encode(['ok'=>true,'card'=>$card], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(201)
                ->withHeader('Content-Type','application/json')
                ->withHeader('Location', "/api/flash-cards/{$card->id}");
    });

    $group->get('/{id}', function (Request $req, Response $res, array $args) {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];
        $card = FlashCard::where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok'=>false,'error'=>'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type','application/json');
        }
        $res->getBody()->write(json_encode(['ok'=>true,'card'=>$card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    });

    $group->put('/{id}', function (Request $req, Response $res, array $args) use ($validatorFactory) {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];
        $data   = (array)$req->getParsedBody();

        $v = $validatorFactory->make($data, [
            'front' => 'sometimes|required|string|max:500',
            'back'  => 'sometimes|required|string|max:500',
        ]);
        if ($v->fails()) {
            $res->getBody()->write(json_encode(['ok'=>false,'errors'=>$v->errors()->toArray()], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(422)->withHeader('Content-Type','application/json');
        }

        $card = FlashCard::where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok'=>false,'error'=>'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type','application/json');
        }

        $card->fill(array_intersect_key($data, array_flip(['front','back'])));
        $card->save();

        $res->getBody()->write(json_encode(['ok'=>true,'card'=>$card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    });

    $group->patch('/{id}', function (Request $req, Response $res, array $args) use ($validatorFactory) {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];
        $data   = (array)$req->getParsedBody();

        $v = $validatorFactory->make($data, [
            'front' => 'sometimes|required|string|max:500',
            'back'  => 'sometimes|required|string|max:500',
        ]);
        if ($v->fails()) {
            $res->getBody()->write(json_encode(['ok'=>false,'errors'=>$v->errors()->toArray()], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(422)->withHeader('Content-Type','application/json');
        }

        $card = FlashCard::where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok'=>false,'error'=>'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type','application/json');
        }

        $card->fill(array_intersect_key($data, array_flip(['front','back'])));
        $card->save();

        $res->getBody()->write(json_encode(['ok'=>true,'card'=>$card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    });

    $group->delete('/{id}', function (Request $req, Response $res, array $args) {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];

        $card = FlashCard::where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok'=>false,'error'=>'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type','application/json');
        }

        $card->delete();
        return $res->withStatus(204);
    });

    $group->post('/{id}/restore', function (Request $req, Response $res, array $args) {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];

        $card = FlashCard::withTrashed()->where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok'=>false,'error'=>'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type','application/json');
        }

        if ($card->trashed()) { $card->restore(); }

        $res->getBody()->write(json_encode(['ok'=>true,'card'=>$card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    });
})->add($jwtAuth); // グループ全体にミドルウェアを適用

$app->get('/users', function (Request $request, Response $response) {
    $users = User::orderBy('id')->get();
    $response->getBody()->write($users->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type','application/json');
});

/* --- ヘルスチェック --- */
$app->get('/health', function (Request $req, Response $res) {
    try {
        $now = Capsule::connection()->selectOne('SELECT NOW() AS now');
        $res->getBody()->write(json_encode(['result'=>true,'db_time'=>$now->now], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type','application/json');
    } catch (\Throwable $e) {
        $res->getBody()->write(json_encode(['result'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(500)->withHeader('Content-Type','application/json');
    }
});

/* =========================
 * 7) 実行
 * ========================= */
$app->run();