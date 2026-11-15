<?php

declare(strict_types=1);

use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

beforeEach(function () {
    $this->bot = $this->makeBot();
    $this->user = $this->makeBotUser($this->bot, 4242);
    wHook()->setBot($this->bot);

    settings()->set('billing.user_wallet.status', true, $this->bot);
});

it('fills in the recipient\'s balance in an announcement text', function () {
    BotUserWallet::create(['bot_id' => $this->bot->id, 'bot_user_id' => $this->user->id, 'balance' => 50000]);

    expect(announcementPlaceholders()->render('{$wallet.balance}', $this->user))
        ->toBe(currency()->priceFormat('50000'));
});

it('does not open a wallet for a recipient who has none', function () {
    expect(announcementPlaceholders()->render('{$wallet.balance}', $this->user))->toBe(currency()->priceFormat('0'))
        ->and(BotUserWallet::query()->where('bot_user_id', $this->user->id)->exists())->toBeFalse();
});

it('offers the balance only while the wallet is switched on', function () {
    expect(announcementPlaceholders()->all())->toHaveKey('wallet.balance');

    settings()->set('billing.user_wallet.status', false, $this->bot);

    expect(announcementPlaceholders()->unknownIn('{$wallet.balance}'))->toBe(['wallet.balance']);
});
