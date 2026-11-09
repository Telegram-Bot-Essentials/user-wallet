<?php

namespace TelegramBotEssentials\UserWallet\Web\Manage;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

class ListWallets extends ListRecords
{
    use GuardsResourcePage;

    protected static string $resource = WalletResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        $totals = BotUserWallet::query()
            ->selectRaw('COALESCE(SUM(balance), 0) as total')
            ->selectRaw('SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) as holders')
            ->toBase()
            ->first();

        $total = $totals->total ?? 0;
        $holders = $totals->holders ?? 0;

        return (string) __('tbe-user-wallet::web.totals', [
            'total' => currency()->priceFormat(is_numeric($total) ? (string) $total : '0'),
            'holders' => number_format(is_numeric($holders) ? (int) $holders : 0),
        ]);
    }
}
