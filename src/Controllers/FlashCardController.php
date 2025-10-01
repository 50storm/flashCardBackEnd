<?php
namespace App\Controllers;

use App\Models\FlashCard;
use Illuminate\Validation\Factory as ValidatorFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FlashCardController
{
    protected ValidatorFactory $validator;

    public function __construct(ValidatorFactory $validator)
    {
        $this->validator = $validator;
    }

    public function index(Request $req, Response $res): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $cards = FlashCard::where('user_id', $userId)->orderByDesc('id')->get();

        $res->getBody()->write($cards->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    public function store(Request $req, Response $res): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $data = (array)$req->getParsedBody();

        $v = $this->validator->make($data, [
            'front' => 'required|string|max:500',
            'back'  => 'required|string|max:500',
        ]);

        if ($v->fails()) {
            $res->getBody()->write(json_encode(['ok' => false, 'errors' => $v->errors()->toArray()], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $card = FlashCard::create([
            'user_id' => $userId,
            'front'   => $data['front'],
            'back'    => $data['back'],
        ]);

        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withStatus(201)
                   ->withHeader('Content-Type', 'application/json')
                   ->withHeader('Location', "/api/flash-cards/{$card->id}");
    }

    public function show(Request $req, Response $res, array $args): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];
        $card   = FlashCard::where('user_id', $userId)->find($id);

        if (!$card) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

    public function update(Request $req, Response $res, array $args): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];
        $data   = (array)$req->getParsedBody();

        $v = $this->validator->make($data, [
            'front' => 'sometimes|required|string|max:500',
            'back'  => 'sometimes|required|string|max:500',
        ]);

        if ($v->fails()) {
            $res->getBody()->write(json_encode(['ok' => false, 'errors' => $v->errors()->toArray()], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(422)->withHeader('Content-Type', 'application/json');
        }

        $card = FlashCard::where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $card->fill(array_intersect_key($data, ['front' => '', 'back' => '']));
        $card->save();

        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }

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
        return $res->withStatus(204);
    }

    public function restore(Request $req, Response $res, array $args): Response
    {
        $userId = (int)$req->getAttribute('user_id');
        $id     = (int)$args['id'];

        $card = FlashCard::withTrashed()->where('user_id', $userId)->find($id);
        if (!$card) {
            $res->getBody()->write(json_encode(['ok' => false, 'error' => 'Not found'], JSON_UNESCAPED_UNICODE));
            return $res->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if ($card->trashed()) {
            $card->restore();
        }

        $res->getBody()->write(json_encode(['ok' => true, 'card' => $card], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
