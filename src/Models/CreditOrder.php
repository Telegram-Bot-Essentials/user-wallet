<?php

namespace TelegramBotEssentials\UserWallet\Models;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Billing\Models\Abstract\Order;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\UserWallet\Telegram\Features\Member\MyWalletFeature;
use TelegramBotEssentials\Billing\Traits\HasInvoice;
use TelegramBotEssentials\Essence\Traits\HasMessageMeta;

class CreditOrder extends Order
{
    use BelongsToTenant;
    use HasMessageMeta;
    use HasInvoice;

    protected $appends = ['price', 'description', 'paid_at'];
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'price' => 'int',
    ];

    public function getPaidAtAttribute(): ?Carbon
    {
        return $this->invoice?->paid_at;
    }

    public function getAmountAttribute(): string
    {
        return $this->attributes['amount'] ?? 0;
    }

    public function getDescriptionAttribute(): string
    {
        return __('tbe-user-wallet::credit_order.main.text.description', [
            'price' => currency()->priceFormat($this->amount)
        ]);
    }

    /**
     * @throws BindingResolutionException
     * @throws TelegramSDKException
     * @throws LogicException
     */
    public function invoicePaidHook(): void
    {
        wHook()->runForUser($this->botUser, function () {
            wHook()->user()->balance += $this->amount;
            wHook()->user()->save();
            wHook()->api()->sendMessage([
                'chat_id' => wHook()->user()->telegramUser->peer_id,
                'text' => 'Your total credit increased by ' . currency()->priceFormat($this->amount) . ' 💸',
                'reply_markup' => wHook()->user()->getKeyboard(),
            ]);
            MyWalletFeature::main()->send();
        });
    }

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    public function cancelOrderHook(): void
    {
        wHook()->runForUser($this->botUser, function () {
            wHook()->user()->balance -= $this->amount;
            wHook()->user()->save();
            wHook()->api()->sendMessage([
                'chat_id' => wHook()->user()->telegramUser->peer_id,
                'text' => 'Your total credit decreased by ' . currency()->priceFormat($this->amount) . ' 💸' . ' due to order cancellation',
                'reply_markup' => wHook()->user()->getKeyboard(),
            ]);
            MyWalletFeature::main()->send();
        });
    }
}
