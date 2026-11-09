<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Web\WebAccounts;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;
use TelegramBotEssentials\UserWallet\Web\Manage\ViewWallet;
use TelegramBotEssentials\UserWallet\Web\Widgets\WalletStats;

function walletOf(BotUser $user, string $balance): BotUserWallet
{
    return BotUserWallet::query()->create(['bot_id' => $user->bot_id, 'bot_user_id' => $user->id, 'balance' => $balance]);
}

beforeEach(function () {
    $this->bot = $this->makeBot(['bot_owner_peer_id' => 9601]);
    $this->admin = $this->grant($this->makeBotUser($this->bot, 9601), 'user-wallet.wallets.manage', 'web.manage');
    $this->member = $this->makeBotUser($this->bot, 9602);
    $this->member->telegramUser->update(['username' => 'saver']);
    $this->account = $this->panelAccount($this->admin);
    Auth::guard(WebAccounts::guard())->login($this->account);
    $this->url = '/manage/'.$this->bot->unique_id.'/wallets';
});

it('lists this bot\'s wallets with a balance', function () {
    walletOf($this->member, '25000');
    $otherBot = $this->makeBot(['unique_id' => 'other-bot']);
    $outsider = $this->makeBotUser($otherBot, 9700);
    $outsider->telegramUser->update(['username' => 'outsider']);
    walletOf($outsider, '999');

    $this->get($this->url)
        ->assertOk()
        ->assertSee('@saver')
        ->assertSee(currency()->priceFormat('25000'))
        ->assertSee(__('tbe-user-wallet::web.stats.total'))
        ->assertDontSee('@outsider');
});

it('keeps the screens from anyone without the ability', function () {
    $limited = $this->grant($this->makeBotUser($this->bot, 9603), 'web.manage');
    Auth::guard(WebAccounts::guard())->login($this->panelAccount($limited));

    $this->get($this->url)->assertForbidden();
});

it('adjusts a balance both ways, below zero too', function () {
    $wallet = walletOf($this->member, '1000');
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewWallet::class, ['record' => $wallet->id])->callAction('adjust', ['amount' => '500'])->assertHasNoActionErrors();
    expect((float) $wallet->fresh()->balance)->toBe(1500.0);

    Livewire::test(ViewWallet::class, ['record' => $wallet->id])->callAction('adjust', ['amount' => '-2000'])->assertHasNoActionErrors();
    expect((float) $wallet->fresh()->balance)->toBe(-500.0);
});

it('refuses a zero or absurd amount', function () {
    $wallet = walletOf($this->member, '1000');
    $this->enterPanel('manage', $this->bot, $this->account);

    Livewire::test(ViewWallet::class, ['record' => $wallet->id])
        ->callAction('adjust', ['amount' => '0'])->assertHasActionErrors(['amount']);
    Livewire::test(ViewWallet::class, ['record' => $wallet->id])
        ->callAction('adjust', ['amount' => '1000000000'])->assertHasActionErrors(['amount']);

    expect((float) $wallet->fresh()->balance)->toBe(1000.0);
});

it('does not open another bot\'s wallet', function () {
    $otherBot = $this->makeBot(['unique_id' => 'other-bot']);

    $this->get($this->url.'/'.walletOf($this->makeBotUser($otherBot, 9701), '5')->id)->assertNotFound();
});

// One Livewire update per test: Livewire skips persistent middleware it already ran for the route.
it('opens the adjustment over a real request', function () {
    $wallet = walletOf($this->member, '10');

    $this->livewireRequest($this->url.'/'.$wallet->id, 'ViewWallet', 'mountAction', ['adjust'])->assertOk();
});

it('refuses wallet updates once signed out', function () {
    $wallet = walletOf($this->member, '10');

    $this->livewireRequest($this->url.'/'.$wallet->id, 'ViewWallet', 'mountAction', ['adjust'],
        fn () => Auth::guard(WebAccounts::guard())->logout())->assertUnauthorized();
});

it('hides the wallet cards from who may not manage wallets', function () {
    $limited = $this->grant($this->makeBotUser($this->bot, 9604), 'web.manage');
    $this->enterPanel('manage', $this->bot, $this->panelAccount($limited));

    expect(WalletStats::canView())->toBeFalse();
});
