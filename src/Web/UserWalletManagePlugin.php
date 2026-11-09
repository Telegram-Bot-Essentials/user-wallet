<?php

namespace TelegramBotEssentials\UserWallet\Web;

use Filament\Contracts\Plugin;
use Filament\Panel;
use TelegramBotEssentials\UserWallet\Web\Manage\WalletResource;

class UserWalletManagePlugin implements Plugin
{
    public function getId(): string
    {
        return 'tbe-user-wallet-manage';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([WalletResource::class]);
    }

    public function boot(Panel $panel): void {}
}
