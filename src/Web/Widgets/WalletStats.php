<?php

namespace TelegramBotEssentials\UserWallet\Web\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsWidget;
use TelegramBotEssentials\Essence\Web\Filament\DailySeries;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;

/** What members hold and what they topped up this month. */
class WalletStats extends StatsOverviewWidget
{
    use GuardsWidget;

    protected static ?string $ability = 'user-wallet.wallets.manage';

    protected static ?int $sort = 5;

    // Drawn with the page above the wallet list.
    protected static bool $isLazy = false;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $money = fn (mixed $amount): string => currency()->priceFormat(is_numeric($amount) ? (string) $amount : '0');
        $topUps = DailySeries::of(
            Invoice::query()->where('status', 'paid')->where('payable_type', CreditOrder::class),
            'updated_at', 30, 'price',
        );

        return [
            Stat::make((string) __('tbe-user-wallet::web.stats.total'), $money(BotUserWallet::query()->sum('balance')))
                ->description((string) __('tbe-user-wallet::web.stats.holders', ['count' => number_format(BotUserWallet::query()->where('balance', '>', 0)->count())]))
                ->color('primary'),
            Stat::make((string) __('tbe-user-wallet::web.stats.top_ups'), $money(array_sum($topUps)))
                ->description((string) __('tbe-user-wallet::web.stats.last_30'))
                ->chart(array_values($topUps))
                ->color('success'),
            Stat::make((string) __('tbe-user-wallet::web.stats.negative'), number_format(BotUserWallet::query()->where('balance', '<', 0)->count()))
                ->color('gray'),
        ];
    }
}
