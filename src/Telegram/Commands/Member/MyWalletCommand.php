<?php

namespace TelegramBotEssentials\UserWallet\Telegram\Commands\Member;

use TelegramBotEssentials\Essence\Enums\ChatScope;
use TelegramBotEssentials\Essence\Telegram\Commands\Command;
use TelegramBotEssentials\Essence\Telegram\TelegramResponse;
use TelegramBotEssentials\UserWallet\Listeners\OpenWalletFromDeepLink;
use TelegramBotEssentials\UserWallet\Telegram\Features\Member\MyWalletFeature;

class MyWalletCommand extends Command
{
    protected string $name = 'wallet';

    protected ChatScope $chatScope = ChatScope::Any;

    protected function description(): string
    {
        return __('tbe-user-wallet::my_wallet.command_description');
    }

    public function handle(): ?bool
    {
        dependsOn((bool) settings()->get('billing.user_wallet.status'));

        // A balance is nobody else's business.
        if (wHook()->inGroup()) {
            TelegramResponse::continueInPrivate(OpenWalletFromDeepLink::PAYLOAD)->send();

            return true;
        }

        MyWalletFeature::main()->send();

        return true;
    }
}
