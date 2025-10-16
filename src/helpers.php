<?php

use TelegramBotEssentials\Billing\Services\Gateways\Wallet;

if(!function_exists('wallet')){
    function wallet(): Wallet
    {
        return app(Wallet::class);
    }
}
