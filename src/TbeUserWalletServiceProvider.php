<?php

namespace TelegramBotEssentials\UserWallet;

use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Announcements\DTOs\Alert;
use TelegramBotEssentials\Announcements\DTOs\AlertParam;
use TelegramBotEssentials\Announcements\DTOs\Audience;
use TelegramBotEssentials\Announcements\DTOs\Placeholder;
use TelegramBotEssentials\Billing\DTOs\Gateway;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Billing\Services\Gateways\Wallet;
use TelegramBotEssentials\Essence\Events\BotDeepLinkReceived;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Settings\DTOs\Setting;
use TelegramBotEssentials\Settings\Enums\SettingType;
use TelegramBotEssentials\UserManagement\DTOs\BotUserSort;
use TelegramBotEssentials\UserManagement\DTOs\SectionSummary;
use TelegramBotEssentials\UserManagement\DTOs\UserSection;
use TelegramBotEssentials\UserManagement\DTOs\UserStat;
use TelegramBotEssentials\UserManagement\Enums\SectionMode;
use TelegramBotEssentials\UserManagement\Services\BotUserSorts;
use TelegramBotEssentials\UserManagement\Services\UserManagementSections;
use TelegramBotEssentials\UserManagement\Services\UserManagementStats;
use TelegramBotEssentials\UserWallet\Listeners\OpenWalletFromDeepLink;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;
use TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Admin\AdminWalletQuery;
use TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Member\MyWalletQuery;
use TelegramBotEssentials\UserWallet\Telegram\Commands\Member\MyWalletCommand;
use TelegramBotEssentials\UserWallet\Telegram\StateAnswers\Admin\AdminWalletAnswer;
use TelegramBotEssentials\UserWallet\Telegram\StateAnswers\Member\MyWalletAnswer;
use TelegramBotEssentials\UserWallet\Web\Manage\WalletResource;
use TelegramBotEssentials\UserWallet\Web\UserWalletManagePlugin;

class TbeUserWalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Wallet::class, fn () => new Wallet);

        // Class names only: Filament is loaded only where the app installed it.
        webPanels()->manage(UserWalletManagePlugin::class);
    }

    /**
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->registerPublishing();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'tbe-user-wallet');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tbe-user-wallet');

        commandBus()->addCommands([
            MyWalletCommand::class,
        ]);

        Event::listen(BotDeepLinkReceived::class, OpenWalletFromDeepLink::class);

        callbackQueryBus()->addCallbackQueries([
            MyWalletQuery::class,
        ]);

        stateAnswerBus()->addStateAnswers([
            MyWalletAnswer::class,
        ]);

        BotUser::resolveRelationUsing('wallet', function (BotUser $model) {
            return $model->hasOne(BotUserWallet::class, 'bot_user_id')
                ->withDefault(function (BotUserWallet $wallet, BotUser $user) {
                    $wallet->bot_id = wHook()->bot()->id;
                    $wallet->bot_user_id = $user->id;
                    $wallet->balance = 0;
                    $wallet->save();
                });
        });
        $this->registerToBilling();

        $this->addSettings();

        $this->registerUserManagementSort();

        $this->registerAdminWalletSection();

        $this->registerAnnouncementPlaceholders();

        $this->registerAnnouncementAlerts();

        $this->registerUserManagementStat();
    }

    /**
     * The state of every wallet at once, for the header of the user list.
     * Registered only when user-management is installed, which this package
     * does not require.
     */
    private function registerUserManagementStat(): void
    {
        if (! class_exists(UserManagementStats::class)) {
            return;
        }

        app(UserManagementStats::class)->addStat(new UserStat(
            key: 'wallet',
            order: 10,
            content: function () {
                $wallets = BotUserWallet::query()
                    ->selectRaw('COALESCE(SUM(balance), 0) as total')
                    ->selectRaw('SUM(CASE WHEN balance > 0 THEN 1 ELSE 0 END) as holders')
                    ->toBase()
                    ->first();

                return __('tbe-user-wallet::bot_users.stats.wallet', [
                    'total' => currency()->priceFormat($wallets->total),
                    'holders' => number_format($wallets->holders ?? 0),
                ]);
            },
            active: fn () => (bool) settings()->get('billing.user_wallet.status'),
        ));
    }

    /**
     * `{$wallet.balance}` in announcement texts. Registered only when
     * announcements is installed, which this package does not require.
     */
    private function registerAnnouncementPlaceholders(): void
    {
        if (! class_exists(Placeholder::class)) {
            return;
        }

        announcementPlaceholders()->add(new Placeholder(
            key: 'wallet.balance',
            label: fn () => __('tbe-user-wallet::bot_users.placeholders.balance'),
            // Read straight from the table: the wallet relation creates a row
            // for every user it is asked about, and this runs for every recipient.
            resolve: function (BotUser $user): string {
                $balance = BotUserWallet::query()->where('bot_user_id', $user->id)->value('balance');

                return currency()->priceFormat(is_numeric($balance) ? (string) $balance : '0');
            },
            example: fn () => currency()->priceFormat('50000'),
            active: fn () => (bool) settings()->get('billing.user_wallet.status'),
        ));
    }

    /**
     * "You have credit to spend": an automatic alert for members whose
     * wallet has held a balance untouched for a while. Registered only when
     * announcements is installed, and sent only once an admin switches it on.
     */
    private function registerAnnouncementAlerts(): void
    {
        if (! class_exists(Alert::class)) {
            return;
        }

        $active = fn () => (bool) settings()->get('billing.user_wallet.status');
        $text = fn (string $key) => fn () => __('tbe-user-wallet::alerts.idle_credit.'.$key);

        announcementAudiences()->add(new Audience(
            key: 'wallet.idle_credit',
            label: $text('audience'),
            apply: fn (Builder $query) => $query->whereIn('bot_users.id', BotUserWallet::query()
                ->where('balance', '>=', max(1, announcementAlerts()->param('wallet.idle_credit', 'min_balance')))
                ->where('updated_at', '<=', now()->subDays(announcementAlerts()->param('wallet.idle_credit', 'idle_days')))
                ->select('bot_user_id')),
            active: $active,
        ));

        announcementAlerts()->register(new Alert(
            key: 'wallet.idle_credit',
            label: $text('label'),
            audience: 'wallet.idle_credit',
            text: $text('text'),
            params: [
                new AlertParam(
                    key: 'min_balance',
                    label: $text('min_balance'),
                    default: 10000,
                    description: $text('min_balance_description'),
                    format: fn (int $value) => currency()->priceFormat((string) $value),
                ),
                new AlertParam(
                    key: 'idle_days',
                    label: $text('idle_days'),
                    default: 7,
                    description: $text('idle_days_description'),
                ),
            ],
            repeatDays: 30,
            priority: 20,
            active: $active,
            description: $text('description'),
        ));
    }

    private function addSettings(): void
    {
        settings()->addSetting(new Setting(
            key: 'billing.user_wallet',
            label: fn () => __('tbe-user-wallet::settings.labels.user_wallet'),
            type: SettingType::DIRECTORY,
            description: fn () => __('tbe-user-wallet::settings.descriptions.user_wallet'),
        ));

        settings()->addSetting(new Setting(
            key: 'billing.user_wallet.status',
            label: fn () => __('tbe-user-wallet::settings.labels.status'),
            type: SettingType::CHECKBOX,
            default: false,
            description: fn () => __('tbe-user-wallet::settings.descriptions.status'),
        ));
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../lang' => resource_path('lang/vendor/tbe-user-wallet'),
            ], 'tbe-user-wallet-translations');
        }
    }

    private function registerUserManagementSort(): void
    {
        if (! class_exists(BotUserSorts::class)) {
            return;
        }

        app(BotUserSorts::class)->addSort(new BotUserSort(
            key: 'wallet_balance',
            label: __('tbe-user-wallet::bot_users.sorts.wallet_balance'),
            apply: function ($query, $direction) {
                $botId = wHook()->bot()->id;

                return $query
                    ->leftJoin('bot_user_wallets', function ($join) use ($botId) {
                        $join->on('bot_user_wallets.bot_user_id', '=', 'bot_users.id')
                            ->where('bot_user_wallets.bot_id', $botId);
                    })
                    ->select('bot_users.*', DB::raw('COALESCE(bot_user_wallets.balance, 0) as wallet_balance'))
                    ->orderBy('bot_user_wallets.balance', $direction);
            },
            display: fn (BotUser $user) => currency()->priceFormat($user->wallet_balance ?? 0),
            active: fn () => (bool) settings()->get('billing.user_wallet.status'),
        ));
    }

    private function registerAdminWalletSection(): void
    {
        if (! class_exists(UserManagementSections::class)) {
            return;
        }

        callbackQueryBus()->addCallbackQueries([
            AdminWalletQuery::class,
        ]);

        stateAnswerBus()->addStateAnswers([
            AdminWalletAnswer::class,
        ]);

        app(UserManagementSections::class)->addSection(new UserSection(
            key: 'wallet',
            order: 10,
            mode: SectionMode::BUTTON,
            target: fn (BotUser $user) => encodeCallback(AdminWalletQuery::TYPE, 'adjust', [$user->id]),
            summary: fn (BotUser $user): SectionSummary => new SectionSummary(
                title: __('tbe-user-wallet::admin_wallet.main.text.sectionTitle'),
                value: currency()->priceFormat($user->wallet->balance),
                emoji: '💰',
                icon: Heroicon::OutlinedWallet,
            ),
            webUrl: fn (BotUser $user): string => WalletResource::getUrl('view', ['record' => $user->wallet]),
        ));
    }

    private function registerToBilling(): void
    {
        gateways()->addGateway(new Gateway(
            key: 'wallet',
            label: 'Wallet',
            inlineButtonGenerator: function (Invoice $invoice) {
                if ($invoice->payable instanceof CreditOrder) {
                    return null;
                }
                if (! settings()->get('billing.user_wallet.status')) {
                    return null;
                }

                return Keyboard::inlineButton([
                    'text' => __('tbe-user-wallet::invoice.by_wallet.keys.pay', [
                        'price' => currency()->priceFormat($invoice->price),
                    ]),
                    'callback_data' => encodeCallback('MYWALLET', 'byWallet', [$invoice->id]),
                ]);
            }
        ));
    }
}
