<?php
namespace App\Controllers;

use App\Models\User;
use Illuminate\Validation\Factory as ValidatorFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    protected ValidatorFactory $validator;

    public function __construct(ValidatorFactory $validator)
    {
        $this->validator = $validator;
    }

    protected function makeAccessToken(User $user): string
    {
        $now = time();
        $ttl = (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900);
        $payload = [
            'iss'   => $_ENV['JWT_ISS'] ?? 'flashcards-api',
            'aud'   => $_ENV['JWT_AUD'] ?? 'flashcards-client',
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $now + $ttl,
            'sub'   => $user->id,
            'email' => $user->email,
        ];
        return \Firebase\JWT\JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
    }

    public function register(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        $v = $this->validator->make($data, [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|max:255',
        ]);

        if ($v->fails()) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'errors' => $v->errors()->toArray()
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);

        $token = $this->makeAccessToken($user);

        $response->getBody()->write(json_encode([
            'ok' => true,
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
            'user'         => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => 'メールとパスワード必須'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $user = User::where('email', $email)->first();
        if (!$user || !password_verify($password, $user->password)) {
            $response->getBody()->write(json_encode([
                'ok' => false,
                'error' => '認証に失敗しました'
            ], JSON_UNESCAPED_UNICODE));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $token = $this->makeAccessToken($user);

        $response->getBody()->write(json_encode([
            'ok' => true,
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => (int)($_ENV['ACCESS_TOKEN_TTL'] ?? 900),
            'user'         => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]
        ], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
