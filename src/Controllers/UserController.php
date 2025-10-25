<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController
{
    /** 自分のプロフィールを取得 */
    public function me(Request $req, Response $res): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $user = User::find($userId);

        if (!$user) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $res->getBody()->write(json_encode(['ok' => true, 'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    /** 自分のプロフィールを更新 */
    public function updateMe(Request $req, Response $res): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $data = (array)$req->getParsedBody();

        $user = User::find($userId);
        if (!$user) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $user->fill(array_intersect_key($data, ['name' => '', 'email' => '']));
        $user->save();

        $res->getBody()->write(json_encode(['ok' => true, 'user' => $user], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    /** 管理者専用：全ユーザー一覧 */
    public function index(Request $req, Response $res): Response
    {
        $users = User::orderBy('id')->get();
        $res->getBody()->write($users->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
