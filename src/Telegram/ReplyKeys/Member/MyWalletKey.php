<?php

namespace TelegramBotEssentials\UserWallet\Telegram\ReplyKeys\Member;

use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\UserWallet\Telegram\Features\Member\MyWalletFeature;
use TelegramBotEssentials\Essence\Telegram\ReplyKeys\ReplyKey;
use Telegram\Bot\Exceptions\TelegramSDKException;

class MyWalletKey extends ReplyKey
{
    protected string $text = '';
    protected int $perm = Roles::MEMBER->value;
    protected string $response = '';

    public function __construct()
    {
        $this->text = __('tbe-user-wallet::my_wallet.reply_key');
        $this->response = __('tbe-user-wallet::my_wallet.main.answers.executedSuccess');
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(): void
    {
        MyWalletFeature::main()->send();
    }
}
