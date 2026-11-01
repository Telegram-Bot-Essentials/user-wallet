<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use TelegramBotEssentials\Essence\Support\BotCommandMenu;
use TelegramBotEssentials\Essence\Telegram\Commands\Command;

const WALLET_GROUP = -100444;

class WalletTestStartCommand extends Command
{
    protected string $name = 'start';

    public function handle(): ?bool
    {
        return true;
    }
}

beforeEach(function () {
    config(['tbe-essence.groups.enabled' => true, 'tbe-essence.groups.require_approval' => false]);

    $this->bot = $this->makeBot();
    $this->user = $this->makeBotUser($this->bot, 3001);
    Cache::put('tbe:bot-username:'.$this->bot->getKey().':'.md5((string) $this->bot->bot_token), 'shop_bot', 3600);
    settings()->set('billing.user_wallet.status', true, $this->bot);
});

it('sends a group member to their private chat, showing no balance', function () {
    $this->postWebhookUpdate($this->bot, $this->inGroupChat($this->makeMessageUpdate('/wallet', 3001), WALLET_GROUP))->assertOk();

    $this->assertTelegramSent(function ($request) {
        if (! str_ends_with($request->url(), '/sendMessage') || (int) $request['chat_id'] !== WALLET_GROUP) {
            return false;
        }

        $markup = json_decode((string) $request['reply_markup'], true);

        return $request['text'] === __('tbe::general.continue_in_private.text')
            && $markup['inline_keyboard'][0][0]['url'] === 'https://t.me/shop_bot?start=open_wallet';
    });
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/sendMessage') && (int) $request['chat_id'] === 3001);
});

it('shows the wallet in private', function () {
    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('/wallet', 3001))->assertOk();

    $this->assertTelegramSent(fn ($request) => str_ends_with($request->url(), '/sendMessage') && (int) $request['chat_id'] === 3001);
});

it('opens the wallet from the deep link', function () {
    commandBus()->addCommand(WalletTestStartCommand::class);

    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('/start open_wallet', 3001))->assertOk();

    $this->assertTelegramSent(fn ($request) => str_ends_with($request->url(), '/sendMessage') && (int) $request['chat_id'] === 3001);
});

it('ignores other deep links', function () {
    commandBus()->addCommand(WalletTestStartCommand::class);

    $this->postWebhookUpdate($this->bot, $this->makeMessageUpdate('/start CODE123', 3001))->assertOk();

    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/sendMessage'));
});

it('publishes the menu outside a webhook', function () {
    wHook()->clear();
    app(BotCommandMenu::class)->register($this->bot);

    $this->assertTelegramSent(function ($request) {
        return str_ends_with($request->url(), '/setMyCommands')
            && in_array('wallet', array_column(json_decode((string) $request['commands'], true), 'command'), true);
    });
});
