<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

// Slim アプリ作成
$app = AppFactory::create();

// ルート定義
$app->get(
    '/', function ($request, $response) {
        $response->getBody()->write("Hello Slim!");
        return $response;
    }
);

// 実行
$app->run();
