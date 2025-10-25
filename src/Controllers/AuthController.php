<?php

namespace App\Controllers;

use App\Models\User;
use Illuminate\Validation\Factory as ValidatorFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * 🔐 AuthController
 *
 * ユーザー登録とログイン認証を担当するコントローラ。
 * JWT(JSON Web Token) を利用してアクセス制御を行う。
 *
 * 使用技術:
 * - Eloquent ORM（Userモデル）
 * - Illuminate\Validation（入力バリデーション）
 * - Firebase\JWT（トークン生成）
 */
class AuthController
{
    /** @var ValidatorFactory バリデーション用ファクトリ */
    protected ValidatorFactory $validator;

    /** コンストラクタ: バリデータをDI経由で受け取る */
    public function __construct(ValidatorFactory $validator)
    {
        $this->validator = $validator;
    }

    /**
     * 🔑 makeAccessToken()
     *
     * 指定ユーザーに対してJWTアクセストークンを生成する。
     *
     * @param User $user 対象ユーザー
     * @return string 署名済みJWTトークン
     */
    protected function makeAccessToken(User $user): string
    {
        $now = time();
        $ttl = (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900); // デフォルト15分
        $payload = [
            'iss'   => $_ENV['JWT_ISS'] ?? 'flashcards-api',   // 発行者
            'aud'   => $_ENV['JWT_AUD'] ?? 'flashcards-client', // 対象オーディエンス
            'iat'   => $now,     // 発行時刻
            'nbf'   => $now,     // 有効開始時刻
            'exp'   => $now + $ttl, // 有効期限
            'sub'   => $user->id,   // サブジェクト (ユーザーID)
            'email' => $user->email,
        ];

        // HS256で署名されたJWTを生成
        return \Firebase\JWT\JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    /**
     * 🧾 register()
     *
     * POST /api/register
     * ユーザーを新規登録し、すぐにJWTアクセストークンを返す。
     */
    public function register(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        // 入力バリデーション
        $v = $this->validator->make($data, [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|max:255',
        ]);

        if ($v->fails()) {
            // 422 Unprocessable Entity
            $response->getBody()->write(json_encode([
                'ok' => false,
                'errors' => $v->errors()->toArray()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // ユーザー作成（パスワードはハッシュ化）
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        // JWTトークン発行
        $token = $this->makeAccessToken($user);

        // 成功レスポンス: 201 Created
        $response->getBody()->write(json_encode([
            'ok' => true,
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
            'user'         => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    /**
     * 🔓 login()
     *
     * POST /api/login
     * メールアドレスとパスワードで認証し、JWTアクセストークンを発行する。
     */
    public function login(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // 入力チェック
        if (!$email || !$password) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'メールとパスワード必須'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // ユーザー検索
        $user = User::where('email', $email)->first();

        // 認証失敗
        if (!$user || !password_verify($password, $user->password)) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => '認証に失敗しました'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        // 認証成功 → トークン発行
        $token = $this->makeAccessToken($user);

        $response->getBody()->write(json_encode([
            'ok' => true,
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
            'user'         => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
