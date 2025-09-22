<?php

namespace TelegramBotEssentials\UserWallet\Telegram\ReplyKeys\Member;

use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\UserWallet\Telegram\Features\Member\MyWalletFeature;
use TelegramBotEssentials\Essence\Telegram\ReplyKeys\ReplyKey;
use Telegram\Bot\Exceptions\TelegramSDKException;

class MyWalletKey extends ReplyKey
{
    protected string $text = 'My Wallet';
    protected int $perm = Roles::MEMBER->value;
    protected string $response = 'My Wallet executed successfully.';

    public function __construct()
    {
        // Multilingual translations
         $this->text = __('tbe-user-wallet::my_wallet.reply_key');
        // $this->response = __('');
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(): void
    {
        MyWalletFeature::main()->send();
    }
}
