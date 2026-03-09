<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatIntent extends Model
{
    protected $table = 'chat_intents';

    protected $fillable = [
        'token',
        'product_id',
        'seller_id',
        'qty',
        'message',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}