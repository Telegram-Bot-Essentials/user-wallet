<?php

namespace TelegramBotEssentials\UserWallet;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Member\MyWalletQuery;
use TelegramBotEssentials\UserWallet\Telegram\ReplyKeys\Member\MyWalletKey;
use TelegramBotEssentials\UserWallet\Telegram\StateAnswers\Member\MyWalletAnswer;

class TbeUserWalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    /**
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $path = __DIR__ . '/../lang';
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'tbe-user-wallet');
        app()->booted(function () use ($path) {
            logger()->error('Translations loaded from: ' . $path);
            logger()->error(__('tbe-user-wallet::my_wallet.reply_key'));
        });

        replyKeyBus()->addReplyKeys([
            MyWalletKey::class,
        ]);

        callbackQueryBus()->addCallbackQueries([
            MyWalletQuery::class
        ]);

        stateAnswerBus()->addStateAnswers([
            MyWalletAnswer::class
        ]);
    }
}
