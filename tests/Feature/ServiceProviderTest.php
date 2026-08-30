<?php

declare(strict_types=1);

use TelegramBotEssentials\Billing\Services\Gateways;
use TelegramBotEssentials\Settings\Services\Settings;
use TelegramBotEssentials\UserWallet\Services\Wallet;

it('registers the wallet service on the container', function () {
    expect(app(Wallet::class))->toBeInstanceOf(Wallet::class);
});

it('registers the user-wallet settings under the billing tree', function () {
    $keys = app(Settings::class)->getSettings()->keys();

    expect($keys)->toContain('billing.user_wallet', 'billing.user_wallet.status');
});

it('exposes a wallet payment gateway to billing', function () {
    $keys = app(Gateways::class)->getGateways()->pluck('key');

    expect($keys)->toContain('wallet');
});
