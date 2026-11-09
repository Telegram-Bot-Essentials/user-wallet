<?php

namespace TelegramBotEssentials\UserWallet\Services;

use Brick\Math\BigDecimal;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\TelegramUser;
use TelegramBotEssentials\UserManagement\Models\BotUserAction;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

/** An admin moving a member's balance by hand, from the bot or the web panel. */
class WalletAdjustments
{
    /**
     * Adds $amount (negative to deduct; the balance may go below zero) and
     * notes it in the member's history when user-management keeps one.
     *
     * @return BigDecimal the new balance
     *
     * @throws InsufficientBalanceException
     */
    public function adjust(BotUser $member, BigDecimal $amount, BotUser $admin): BigDecimal
    {
        wHook()->runForUser($member, fn () => wallet()->adjustBalance($amount, allowNegative: true));

        if (class_exists(BotUserAction::class)) {
            BotUserAction::query()->create([
                'bot_id' => $member->getAttribute('bot_id'),
                'bot_user_id' => $member->getKey(),
                'update_type' => 'admin_wallet_adjustment',
                'action' => __('tbe-user-wallet::admin_wallet.main.text.auditEntry', [
                    'admin' => self::name($admin),
                    'amount' => currency()->priceFormat($amount),
                ]),
            ]);
        }

        $member->unsetRelation('wallet');
        $balance = BotUserWallet::query()->where('bot_user_id', $member->getKey())->value('balance');

        return BigDecimal::of(is_numeric($balance) ? (string) $balance : '0');
    }

    private static function name(BotUser $user): string
    {
        $telegramUser = $user->telegramUser;
        if (! $telegramUser instanceof TelegramUser) {
            return '?';
        }

        return $telegramUser->username ? '@'.$telegramUser->username : (string) $telegramUser->full_name;
    }
}
