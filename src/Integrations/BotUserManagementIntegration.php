<?php

namespace TelegramBotEssentials\UserWallet\Integrations;

use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\UserManagement\DTOs\BotUserSort;

class BotUserManagementIntegration
{
    public static function register(): void
    {
        if (! function_exists('botUserSorts')) {
            return;
        }

        botUserSorts()->addSort(new BotUserSort(
            key: 'wallet_balance',
            label: __('tbe-user-wallet::bot_users.sorts.wallet_balance'),
            apply: fn ($query, $direction) => $query
                ->leftJoin('bot_user_wallets', function ($join) {
                    $join->on('bot_users.id', '=', 'bot_user_wallets.bot_user_id')
                        ->on('bot_users.bot_id', '=', 'bot_user_wallets.bot_id');
                })
                ->orderByRaw('COALESCE(bot_user_wallets.balance, 0) '.$direction)
                ->select(
                    'bot_users.*',
                    'bot_user_wallets.balance as wallet_balance',
                    'bot_user_wallets.currency as wallet_currency'
                ),
            display: fn (BotUser $user) => currency()->priceFormat(
                $user->wallet_balance ?? 0,
                currency: $user->wallet_currency ?? settings()->get('billing.user_wallet.currency', 'USD')
            ),
        ));
    }
}
