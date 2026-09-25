<?php

declare(strict_types=1);

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use TelegramBotEssentials\UserWallet\Models\ByWalletAttempt;
use TelegramBotEssentials\UserWallet\Services\Wallet;

beforeEach(function () {
    $this->bot = $this->makeBot();
    $this->user = $this->makeBotUser($this->bot, 4242);

    tenancy()->initialize($this->bot);
    wHook()->setBot($this->bot);
    wHook()->setUser($this->user);
    wHook()->setApi(telegramApi($this->bot->bot_token));

    settings()->set('billing.user_wallet.status', true, $this->bot);
    app(Wallet::class)->adjustBalance('1000');

    $this->invoice = Invoice::query()->create([
        'bot_id' => $this->bot->id,
        'bot_user_id' => $this->user->id,
        'payable_type' => 'test-order',
        'payable_id' => 1,
        'price' => '500',
        'original_price' => '500',
    ]);
});

function debitedMessages(): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_contains($pair[0]->url(), '/sendMessage'))
        ->count();
}

function balance(): int
{
    return BigDecimal::of(DB::table('bot_user_wallets')->value('balance'))->toInt();
}

it('debits the wallet and records the attempt against the invoice', function () {
    $attempt = app(Wallet::class)->payInvoice($this->invoice);

    expect(balance())->toBe(500)
        ->and($attempt)->toBeInstanceOf(ByWalletAttempt::class)
        ->and($attempt->bot_id)->toBe($this->bot->id)
        ->and($this->invoice->fresh()->payment_attempt_id)->toBe($attempt->id)
        ->and(debitedMessages())->toBe(1);
});

it('leaves the balance alone and says nothing when recording the attempt fails', function () {
    // Whatever goes wrong after the money is taken: here the attempt cannot be saved.
    ByWalletAttempt::creating(fn () => throw new RuntimeException('cannot record attempt'));

    expect(fn () => app(Wallet::class)->payInvoice($this->invoice))
        ->toThrow(RuntimeException::class, 'cannot record attempt');

    expect(balance())->toBe(1000)
        ->and(DB::table('by_wallet_attempts')->count())->toBe(0)
        ->and($this->invoice->fresh()->payment_attempt_id)->toBeNull()
        // The member is not told they paid something they did not pay.
        ->and(debitedMessages())->toBe(0);
});

it('records nothing when the balance does not cover the invoice', function () {
    DB::table('bot_user_wallets')->update(['balance' => 100]);
    wHook()->user()->unsetRelation('wallet');

    expect(fn () => app(Wallet::class)->payInvoice($this->invoice))
        ->toThrow(InsufficientBalanceException::class);

    expect(balance())->toBe(100)
        ->and(DB::table('by_wallet_attempts')->count())->toBe(0);
});

it('still debits and messages through takeAmount as before', function () {
    app(Wallet::class)->takeAmount('300');

    expect(balance())->toBe(700)
        ->and(debitedMessages())->toBe(1);
});
