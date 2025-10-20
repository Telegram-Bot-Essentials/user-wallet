<?php

namespace TelegramBotEssentials\UserWallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Stancl\Tenancy\Database\TenantScope;
use TelegramBotEssentials\Essence\Models\Bot;
use TelegramBotEssentials\Essence\Models\BotUser;

class BotUserWallet extends Model
{
    use BelongsToTenant;
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->currency = settings()->get('billing.currency');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
