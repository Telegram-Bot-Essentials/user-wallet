<?php

namespace TelegramBotEssentials\UserWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

class BotUserWallet extends Model
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
