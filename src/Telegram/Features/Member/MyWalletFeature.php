<?php

namespace TelegramBotEssentials\UserWallet\Telegram\Features\Member;

use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Essence\Telegram\TelegramResponse;

class MyWalletFeature
{
    static string $type = 'MYWALLET';

    public static function main(): TelegramResponse
    {
        $text = __('tbe-user-wallet::my_wallet.main.text.totalCredit', [
            'price' => currency()->priceFormat(
                wHook()->user()->wallet->balance,
                currency: wHook()->user()->wallet->currency
            )
        ]);

        $replyMarkup = Keyboard::make()
            ->inline();

        $replyMarkup->row([
            Keyboard::inlineButton([
                'text' => __('tbe-user-wallet::my_wallet.main.keys.addCredit'),
                'callback_data' => encodeCallback(self::$type, 'add_credit')
            ])
        ]);

        return new TelegramResponse(
            text: $text,
            replyMarkup: $replyMarkup,
            parseMode: 'HTML'
        );
    }
}
