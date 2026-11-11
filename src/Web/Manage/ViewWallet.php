<?php

namespace TelegramBotEssentials\UserWallet\Web\Manage;

use Brick\Math\BigDecimal;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Web\Filament\Concerns\GuardsResourcePage;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;
use TelegramBotEssentials\UserWallet\Models\ByWalletAttempt;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;
use TelegramBotEssentials\UserWallet\Services\WalletAdjustments;

/** One wallet: its balance, how it was topped up and spent, and a hand adjustment. */
class ViewWallet extends Page
{
    use GuardsResourcePage;
    use InteractsWithRecord;

    protected static string $resource = WalletResource::class;

    protected string $view = 'tbe-user-wallet::web.view';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function wallet(): BotUserWallet
    {
        return BotUserWallet::query()->with('user.telegramUser')->whereKey($this->getRecord()->getKey())->firstOrFail();
    }

    public function getTitle(): string
    {
        return WalletResource::memberName($this->wallet()->user);
    }

    public function balance(): string
    {
        $balance = $this->wallet()->getAttribute('balance');

        return currency()->priceFormat(is_numeric($balance) ? (string) $balance : '0');
    }

    /** @return Collection<int, CreditOrder> top-ups, newest first */
    public function topUps(): Collection
    {
        return CreditOrder::query()->where('bot_user_id', $this->wallet()->getAttribute('bot_user_id'))->with('invoice')->latest('id')->limit(50)->get();
    }

    /** @return Collection<int, ByWalletAttempt> invoices paid from the wallet, newest first */
    public function payments(): Collection
    {
        return ByWalletAttempt::query()->where('bot_user_id', $this->wallet()->getAttribute('bot_user_id'))->latest('id')->limit(50)->get();
    }

    public function money(mixed $amount): string
    {
        return currency()->priceFormat(is_numeric($amount) ? (string) $amount : '0');
    }

    /** An invoice's or a payment's state in words, instead of its stored value. */
    public function statusLabel(?string $status): string
    {
        return (string) __('tbe-user-wallet::web.statuses.'.($status ?? 'pending'));
    }

    public function statusColor(?string $status): string
    {
        return match ($status) {
            'paid', 'succeed' => 'success',
            'failed' => 'danger',
            default => 'warning',
        };
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjust')
                ->label((string) __('tbe-user-wallet::web.adjust'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->modalDescription(fn (): string => (string) __('tbe-user-wallet::web.adjust_help', ['balance' => $this->balance()]))
                ->schema([
                    // The bot's own limits for the same adjustment.
                    TextInput::make('amount')
                        ->label((string) __('tbe-user-wallet::web.amount'))
                        ->required()
                        ->numeric()
                        ->notIn(['0'])
                        ->minValue(-100000000)
                        ->maxValue(100000000),
                ])
                ->action(function (array $data, WalletAdjustments $adjustments): void {
                    $member = $this->wallet()->user;
                    $amount = $data['amount'] ?? null;
                    if (! $member instanceof BotUser || ! is_numeric($amount)) {
                        return;
                    }

                    $balance = $adjustments->adjust($member, BigDecimal::of((string) $amount), wHook()->user());

                    Notification::make()
                        ->title((string) __('tbe-user-wallet::web.adjusted', ['balance' => currency()->priceFormat($balance)]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
