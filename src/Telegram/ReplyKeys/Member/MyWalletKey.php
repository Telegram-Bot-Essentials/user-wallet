<?php

namespace TelegramBotEssentials\UserWallet\Telegram\ReplyKeys\Member;

use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Telegram\ReplyKeys\ReplyKey;
use TelegramBotEssentials\UserWallet\Telegram\Features\Member\MyWalletFeature;

class MyWalletKey extends ReplyKey
{
    protected int $perm = Roles::MEMBER->value;

    protected function text(): string
    {
        return __('tbe-user-wallet::my_wallet.reply_key');
    }

    protected function response(): string
    {
        return __('tbe-user-wallet::my_wallet.main.answers.executedSuccess');
    }

    /**
     * @throws TelegramSDKException
     * @throws FeatureIsDisabled
     */
    public function handle(): void
    {
        dependsOn(settings()->get('billing.user_wallet.status'));

        MyWalletFeature::main()->send();
    }

    public function isEnabled(): bool
    {
        return settings()->get('billing.user_wallet.status');
    }
}
