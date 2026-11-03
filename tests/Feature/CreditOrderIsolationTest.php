<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Telegram\Bot\Objects\Update;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;

it('credits the wallet even when telegram cannot say so', function () {
    $bot = $this->makeBot();
    $user = $this->makeBotUser($bot, 4343);

    tenancy()->initialize($bot);
    wHook()->setBot($bot);
    wHook()->setUser($user);
    wHook()->setApi(telegramApi($bot->bot_token));
    wHook()->setUpdate(new Update(['update_id' => 1]));
    settings()->set('billing.user_wallet.status', true, $bot);

    $order = CreditOrder::create(['bot_id' => $bot->id, 'bot_user_id' => $user->id, 'amount' => '250']);

    $factory = new Factory;
    $factory->fake(['*' => Http::response(['ok' => false, 'error_code' => 500, 'description' => 'Internal Server Error'], 500)]);
    Http::swap($factory);

    $order->invoicePaidHook();

    expect(BigDecimal::of(DB::table('bot_user_wallets')->value('balance'))->toInt())->toBe(250)
        ->and(wHook()->user()->getKey())->toBe($user->getKey());
});
