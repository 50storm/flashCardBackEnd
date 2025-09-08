<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Carbon\Carbon;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require __DIR__ . '/vendor/autoload.php';

// .env 読み込み
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$appName = $_ENV['APP_NAME'] ?? 'NoName';

// === Carbon で時刻処理 ===
$now = Carbon::now()->toDateTimeString();

// === Monolog 設定 ===
$log = new Logger('flashcard');
// Docker の標準出力へ
// docker compose logs -f app でログ確認可能
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$log->info("App loaded", ['app' => $appName, 'time' => $now]);

// Slim アプリ作成
$app = AppFactory::create();

// ルート定義
$app->get('/', function ($request, $response) use ($log) {
    $log->info("Root route accessed");
    $response->getBody()->write("Hello Slim with Carbon & Monolog!");
    return $response;
});

// 実行
$app->run();
