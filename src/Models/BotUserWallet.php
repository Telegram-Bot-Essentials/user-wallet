<?php

namespace TelegramBotEssentials\UserWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

/**
 * @property int $id
 * @property int|string $bot_id
 * @property int $bot_user_id
 * @property-read BotUser|null $user
 */
class BotUserWallet extends Model
{
    use BelongsToTenant;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /** @return BelongsTo<BotUser, $this> */
    public function user(): BelongsTo
    {
        // The column is bot_user_id; the method name alone would read user_id.
        return $this->belongsTo(BotUser::class, 'bot_user_id');
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
