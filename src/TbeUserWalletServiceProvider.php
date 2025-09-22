<?php

namespace TelegramBotEssentials\UserWallet;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\Member\MyWalletAnswer;
use TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Member\MyWalletQuery;
use TelegramBotEssentials\UserWallet\Telegram\ReplyKeys\Member\MyWalletKey;

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
}
