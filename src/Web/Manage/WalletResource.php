<?php

namespace TelegramBotEssentials\UserWallet\Web\Manage;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\TelegramUser;
use TelegramBotEssentials\Essence\Web\Filament\PanelResource;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

/** Members' wallets: what each holds, adjusted by hand as in the bot's profile. */
class WalletResource extends PanelResource
{
    protected static ?string $model = BotUserWallet::class;

    protected static ?string $ability = 'user-wallet.wallets.manage';

    protected static ?string $slug = 'wallets';

    protected static ?string $tenantOwnershipRelationshipName = 'bot';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    public static function getNavigationLabel(): string
    {
        return (string) __('tbe-user-wallet::web.nav');
    }

    public static function getModelLabel(): string
    {
        return (string) __('tbe-user-wallet::web.wallet');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('tbe-user-wallet::web.nav');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user.telegramUser'))
            ->columns([
                TextColumn::make('member')
                    ->label((string) __('tbe-user-wallet::web.member'))
                    ->state(fn (BotUserWallet $record): string => self::memberName($record->user))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user.telegramUser', fn (Builder $q): Builder => $q
                        ->where('username', 'like', '%'.ltrim($search, '@').'%')
                        ->orWhere('first_name', 'like', "%$search%")
                        ->orWhere('peer_id', $search))),
                TextColumn::make('balance')
                    ->label((string) __('tbe-user-wallet::web.balance'))
                    ->formatStateUsing(fn (mixed $state): string => currency()->priceFormat(is_numeric($state) ? (string) $state : '0'))
                    ->color(fn (BotUserWallet $record): ?string => self::isNegative($record) ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('updated_at')->label((string) __('tbe-user-wallet::web.changed'))->since()->sortable(),
            ])
            ->filters([
                Filter::make('holders')
                    ->label((string) __('tbe-user-wallet::web.with_balance'))
                    ->query(fn (Builder $query): Builder => $query->where('balance', '!=', 0))
                    ->default(),
            ])
            ->defaultSort('balance', 'desc')
            ->recordUrl(fn (BotUserWallet $record): string => ViewWallet::getUrl(['record' => $record]));
    }

    public static function memberName(mixed $user): string
    {
        $telegramUser = $user instanceof BotUser ? $user->telegramUser : null;
        if (! $telegramUser instanceof TelegramUser) {
            return '?';
        }

        return $telegramUser->username ? '@'.$telegramUser->username : ((string) $telegramUser->full_name ?: (string) $telegramUser->peer_id);
    }

    private static function isNegative(BotUserWallet $wallet): bool
    {
        $balance = $wallet->getAttribute('balance');

        return is_numeric($balance) && (float) $balance < 0;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWallets::route('/'),
            'view' => ViewWallet::route('/{record}'),
        ];
    }
}
