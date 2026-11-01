<?php

namespace TelegramBotEssentials\UserWallet\Listeners;

use TelegramBotEssentials\Essence\Events\BotDeepLinkReceived;
use TelegramBotEssentials\UserWallet\Telegram\Features\Member\MyWalletFeature;

/** Opens the wallet in the private chat, from the link /wallet sends in a group. */
class OpenWalletFromDeepLink
{
    public const PAYLOAD = 'open_wallet';

    public function handle(BotDeepLinkReceived $event): void
    {
        if ($event->payload !== self::PAYLOAD || ! settings()->get('billing.user_wallet.status')) {
            return;
        }

        MyWalletFeature::main()->send();
    }
}
