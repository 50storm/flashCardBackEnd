<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlashCard extends Model
{
    use SoftDeletes;

    protected $table = 'flash_cards';

    protected $fillable = [
        'user_id', 'front', 'back',
    ];
}
