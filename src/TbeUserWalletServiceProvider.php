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
    /**
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function register(): void
    {
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

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'tbe-user-wallet');
    }
}
