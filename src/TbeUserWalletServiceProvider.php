<?php

namespace TelegramBotEssentials\UserWallet;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Telegram\Bot\Keyboard\Keyboard;
use TelegramBotEssentials\Billing\DTOs\Gateway;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Billing\Services\Gateways\Wallet;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
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

        $this->registerToBilling();
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
                return Keyboard::inlineButton([
                    'text' => 'Pay with wallet',
                    'callback_data' => encodeCallback('MYWALLET', 'byWallet', [$invoice->id])
                ]);
            }
        ));
    }
}
