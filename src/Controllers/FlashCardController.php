<?php

namespace App\Controllers;

use App\Models\FlashCard;
use Illuminate\Validation\Factory as ValidatorFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * 🎴 FlashCardController
 *
 * ユーザーごとのフラッシュカード（単語帳）を操作するためのRESTfulコントローラ。
 *
 * 利用ライブラリ:
 * - Eloquent ORM (App\Models\FlashCard)
 * - Illuminate\Validation\Validator
 * - PSR-7 Request/Response (Slim Framework)
 */
class FlashCardController
{
    /** @var ValidatorFactory バリデーションファクトリ */
    protected ValidatorFactory $validator;

    /** コンストラクタ: バリデーション用ファクトリをDIで受け取る */
    public function __construct(ValidatorFactory $validator)
    {
        $this->validator = $validator;
    }

    /**
     * 📄 index()
     * GET /api/flash-cards
     *
     * ログイン中ユーザーのカード一覧を新しい順に返す。
     */
    public function index(Request $req, Response $res): Response
    {
        $userId = (int)$req->getAttribute('user_id');  // 認証ミドルウェアからユーザーID取得
        $cards = FlashCard::where('user_id', $userId)
                          ->orderByDesc('id')
                          ->get();

        // JSONで整形して返す
        $res->getBody()->write($cards->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    /**
     * ➕ store()
     * POST /api/flash-cards
     *
     * 新しいカードを登録。
     * 必須フィールド: front, back
     */
    public function store(Request $req, Response $res): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $data = (array)$req->getParsedBody();

        // 入力バリデーション
        $v = $this->validator->make($data, [
            'front' => 'required|string|max:500',
            'back'  => 'required|string|max:500',
        ]);

        if ($v->fails()) {
            // バリデーションエラー時: 422 Unprocessable Entity
            $res->getBody()->write(json_encode([
                'ok' => false,
                'errors' => $v->errors()->toArray()
            ], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        // データ作成
        $card = FlashCard::create([
            'user_id' => $userId,
            'front'   => $data['front'],
            'back'    => $data['back'],
        ]);

        // 成功時: 201 Created + Locationヘッダ
        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(201)
                   ->withHeader('Content-Type', 'application/json')
                   ->withHeader('Location', "/api/flash-cards/{$card->id}");
    }

    /**
     * 👁 show()
     * GET /api/flash-cards/{id}
     *
     * 特定のカードを取得。
     */
    public function show(Request $req, Response $res, array $args): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];

        $card = FlashCard::where('user_id', $userId)->find($id);

        if (!$card) {
            // 存在しない場合: 404 Not Found
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    /**
     * ✏️ update()
     * PUT /api/flash-cards/{id}
     *
     * カード内容を部分的に更新。
     */
    public function update(Request $req, Response $res, array $args): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];
        $data   = (array)$req->getParsedBody();

        // 「sometimes」= 渡された項目だけ検証
        $v = $this->validator->make($data, [
            'front' => 'sometimes|required|string|max:500',
            'back'  => 'sometimes|required|string|max:500',
        ]);

        if ($v->fails()) {
            $res->getBody()->write(json_encode(
                [
                    'ok' => false,
                    'errors' => $v->errors()->toArray()
                ],
                JSON_UNESCAPED_UNICODE
            ));
            return $res->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $card = FlashCard::where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // 渡されたデータのみ更新
        $card->fill(array_intersect_key($data, ['front' => '', 'back' => '']));
        $card->save();

        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    /**
     * ❌ delete()
     * DELETE /api/flash-cards/{id}
     *
     * ソフトデリート（論理削除）を実行。
     */
    public function delete(Request $req, Response $res, array $args): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];

        $card = FlashCard::where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $card->delete();
        return $res->withStatus(204); // No Content
    }

    /**
     * ♻️ restore()
     * PUT /api/flash-cards/{id}/restore
     *
     * ソフトデリートされたカードを復元。
     */
    public function restore(Request $req, Response $res, array $args): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];

        $card = FlashCard::withTrashed()->where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // 削除済みなら復元
        if ($card->trashed()) {
            $card->restore();
        }

        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
