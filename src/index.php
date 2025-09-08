<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

// Composer autoload を必ず先頭で読み込み
require __DIR__ . '/vendor/autoload.php';

// .env 読み込み
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

dd($_ENV);

$appName = $_ENV['APP_NAME'] ?? 'NoName';
error_log("App loaded: {$appName}");

// Slim アプリ作成
$app = AppFactory::create();

// ルート定義
$app->get(
    '/',
    function ($request, $response) {
        $response->getBody()->write("Hello Slim!");
        return $response;
    }
);

// 実行
$app->run();
