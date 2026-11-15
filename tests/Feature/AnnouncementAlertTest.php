<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Announcements\Services\AlertSweep;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

beforeEach(function () {
    // The base test case answers every request with an empty body, and the first fake wins.
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1, 'date' => 0, 'chat' => ['id' => 1, 'type' => 'private']]])]);
    config(['queue.default' => 'sync']);
    Carbon::setTestNow('2026-11-15 12:00:00');

    $this->bot = $this->makeBot();
    wHook()->setBot($this->bot);
    settings()->set('billing.user_wallet.status', true, $this->bot);

    $this->alert = announcementAlerts()->get('wallet.idle_credit');

    // A member in a private chat with the bot, whose wallet last changed $daysAgo days ago.
    $this->holder = function (int $peerId, int $balance, int $daysAgo) {
        $user = $this->makeBotUser($this->bot, $peerId, ['has_private_chat' => true]);
        BotUserWallet::create(['bot_id' => $this->bot->id, 'bot_user_id' => $user->id, 'balance' => $balance])
            ->forceFill(['updated_at' => now()->subDays($daysAgo)])->saveQuietly();

        return $user;
    };

    $this->sweep = fn (): int => wHook()->runInContext($this->bot, null, null, null, fn () => app(AlertSweep::class)->run($this->bot));
    $this->texts = fn (): array => collect(Http::recorded())
        ->map(fn (array $pair) => $pair[0])
        ->filter(fn ($request) => str_contains((string) $request->url(), '/sendMessage'))
        ->map(fn ($request) => [$request['chat_id'], $request['text']])
        ->values()->all();
});

it('offers the alert only while the wallet is switched on, and off until an admin says so', function () {
    expect($this->alert)->not->toBeNull()
        ->and(announcementAlerts()->enabled($this->alert))->toBeFalse();

    settings()->set('billing.user_wallet.status', false, $this->bot);

    expect(announcementAlerts()->get('wallet.idle_credit'))->toBeNull();
});

it('reminds members whose credit has sat untouched, and nobody else', function () {
    ($this->holder)(4001, 50000, 10);
    ($this->holder)(4002, 50000, 1);
    ($this->holder)(4003, 500, 10);
    $this->makeBotUser($this->bot, 4004, ['has_private_chat' => true]);

    settings()->set(announcementAlerts()->settingKey($this->alert, 'status'), true, $this->bot);

    expect(($this->sweep)())->toBe(1);

    [[$chat, $text]] = ($this->texts)();
    expect($chat)->toBe(4001)
        ->and($text)->toContain(currency()->priceFormat('50000'));
});

it('follows the balance and the days an admin set', function () {
    ($this->holder)(4003, 500, 2);

    settings()->set(announcementAlerts()->settingKey($this->alert, 'status'), true, $this->bot);
    settings()->set(announcementAlerts()->settingKey($this->alert, 'min_balance'), 100, $this->bot);
    settings()->set(announcementAlerts()->settingKey($this->alert, 'idle_days'), 1, $this->bot);

    expect(($this->sweep)())->toBe(1);
});
