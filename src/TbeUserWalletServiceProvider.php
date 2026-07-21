<?php

namespace TelegramBotEssentials\UserWallet;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Billing\DTOs\Gateway;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Billing\Services\Gateways\Wallet;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Settings\DTOs\Setting;
use TelegramBotEssentials\Settings\Enums\SettingType;
use TelegramBotEssentials\UserManagement\DTOs\BotUserSort;
use TelegramBotEssentials\UserManagement\Services\BotUserSorts;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;
use TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Member\MyWalletQuery;
use TelegramBotEssentials\UserWallet\Telegram\StateAnswers\Member\MyWalletAnswer;

class TbeUserWalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Wallet::class, fn() => new Wallet());

    }

    /**
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->registerPublishing();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'tbe-user-wallet');

        callbackQueryBus()->addCallbackQueries([
            MyWalletQuery::class
        ]);

        stateAnswerBus()->addStateAnswers([
            MyWalletAnswer::class
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
    }

    private function addSettings(): void
    {
        settings()->addSetting(new Setting(
            key: 'billing.user_wallet',
            label: fn () => __('tbe-user-wallet::settings.labels.user_wallet'),
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.user_wallet.status',
            label: fn () => __('tbe-user-wallet::settings.labels.status'),
            type: SettingType::CHECKBOX,
            default: false
        ));
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../lang' => resource_path('lang/vendor/tbe-user-wallet'),
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

    private function registerToBilling(): void
    {
        gateways()->addGateway(new Gateway(
            key: 'wallet',
            label: 'Wallet',
            inlineButtonGenerator: function (Invoice $invoice) {
                if($invoice->payable instanceof CreditOrder){
                    return null;
                }
                if(!settings()->get('billing.user_wallet.status')){
                    return null;
                }
                return Keyboard::inlineButton([
                    'text' => __('tbe-user-wallet::invoice.by_wallet.keys.pay', [
                        'price' => currency()->priceFormat($invoice->price),
                    ]),
                    'callback_data' => encodeCallback('MYWALLET', 'byWallet', [$invoice->id])
                ]);
            }
        ));
    }
}
