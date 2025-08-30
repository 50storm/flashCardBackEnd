<?php
declare(strict_types=1);

// エラー表示（開発用）
ini_set('display_errors', '1');
error_reporting(E_ALL);

// オートロード（もし Composer を使うなら）
// require __DIR__.'/../vendor/autoload.php';

// 簡易リクエスト情報取得
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// 簡易ルーティング定義
$routes = [
    ['GET', '/', fn() => ['message' => 'Hello from root']],
    ['GET', '/users', fn() => ['users' => ['iga', 'mori', 'okada']]],
    ['GET', '/about', fn() => ['app' => 'Sample PHP API']],
];

// ルートマッチング
foreach ($routes as [$m, $path, $handler]) {
    if ($m === $method && $path === $uri) {
        $response = $handler();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 404
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Not Found', 'path' => $uri], JSON_UNESCAPED_UNICODE);
