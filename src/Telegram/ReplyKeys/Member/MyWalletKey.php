<?php

namespace TelegramBotEssentials\UserWallet\Telegram\ReplyKeys\Member;

use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\UserWallet\Telegram\Features\Member\MyWalletFeature;
use TelegramBotEssentials\Essence\Telegram\ReplyKeys\ReplyKey;
use Telegram\Bot\Exceptions\TelegramSDKException;

class MyWalletKey extends ReplyKey
{
    protected string $textKey = 'tbe-user-wallet::my_wallet.reply_key';
    protected int $perm = Roles::MEMBER->value;
    protected string $responseKey = 'tbe-user-wallet::my_wallet.main.answers.executedSuccess';


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
