<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use TelegramBotEssentials\UserWallet\Services\Wallet;

// adjustBalance() is the system-initiated ledger path: no feature-toggle
// check, no outbound message. It works off the current webhook user's
// `wallet` relation, so stand up a bot + user and point the context at
// them; the relation's withDefault() creates the backing row on first read.
beforeEach(function () {
    $bot = $this->makeBot();
    $peerId = 4242;
    $user = $this->makeBotUser($bot, $peerId);

    wHook()->setBot($bot);
    wHook()->setUser($user);
});

it('credits the wallet', function () {
    app(Wallet::class)->adjustBalance('1500');

    expect(BigDecimal::of(wHook()->user()->wallet->balance)->toInt())->toBe(1500);
});

it('debits the wallet', function () {
    app(Wallet::class)->adjustBalance('2000');
    app(Wallet::class)->adjustBalance('-750');

    expect(BigDecimal::of(wHook()->user()->wallet->balance)->toInt())->toBe(1250);
});

it('refuses a debit that would overdraw the wallet', function () {
    app(Wallet::class)->adjustBalance('100');

    expect(fn () => app(Wallet::class)->adjustBalance('-500'))
        ->toThrow(InsufficientBalanceException::class);

    expect(BigDecimal::of(wHook()->user()->wallet->balance)->toInt())->toBe(100);
});

it('allows an overdraw when explicitly permitted', function () {
    app(Wallet::class)->adjustBalance('-500', allowNegative: true);

    expect(BigDecimal::of(wHook()->user()->wallet->balance)->isNegative())->toBeTrue();
});
