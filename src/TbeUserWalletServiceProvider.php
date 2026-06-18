<?php

namespace TelegramBotEssentials\UserWallet;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Billing\DTOs\Gateway;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Billing\Services\CurrencyFather;
use TelegramBotEssentials\Billing\Services\Gateways\Wallet;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Settings\DTOs\Setting;
use TelegramBotEssentials\Settings\Enums\SettingType;
use TelegramBotEssentials\UserWallet\Integrations\BotUserManagementIntegration;
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
                    $wallet->save();
                });
        });
        $this->registerToBilling();

        $this->addSettings();

        $this->app->booted(fn () => BotUserManagementIntegration::register());
    }

    private function addSettings(): void
    {
        settings()->addSetting(new Setting(
            key: 'billing.user_wallet',
            label: 'User Wallet',
            type: SettingType::DIRECTORY,
        ));

        settings()->addSetting(new Setting(
            key: 'billing.user_wallet.status',
            label: 'User Wallet Status',
            type: SettingType::CHECKBOX,
            default: false
        ));

        settings()->addSetting(new Setting(
            key: 'billing.user_wallet.currency',
            label: 'Wallet Currency',
            type: SettingType::ENUM,
            default: 'USD',
            options: collect(config('tbe-billing.supported_currencies', []))->pluck('name')->toArray()
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
                        'price' => currency()->priceFormat(
                            CurrencyFather::from(settings()->get('billing.currency'))
                                ->amount($invoice->price)
                                ->to($invoice->botUser->wallet->currency),
                            currency: $invoice->botUser->wallet->currency
                        ),
                    ]),
                    'callback_data' => encodeCallback('MYWALLET', 'byWallet', [$invoice->id])
                ]);
            }
        ));
    }
}
