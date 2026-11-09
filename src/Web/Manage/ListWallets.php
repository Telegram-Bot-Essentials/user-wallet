<?php

namespace TelegramBotEssentials\UserWallet\Web\Manage;

use Filament\Resources\Pages\ListRecords;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;
use TelegramBotEssentials\UserWallet\Web\Widgets\WalletStats;

class ListWallets extends ListRecords
{
    use GuardsResourcePage;

    protected static string $resource = WalletResource::class;

    protected function getHeaderWidgets(): array
    {
        return [WalletStats::class];
    }
}
