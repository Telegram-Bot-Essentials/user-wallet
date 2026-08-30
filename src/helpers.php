<?php

use TelegramBotEssentials\UserWallet\Services\Wallet;

if (! function_exists('wallet')) {
    function wallet(): Wallet
    {
        return app(Wallet::class);
    }
}
