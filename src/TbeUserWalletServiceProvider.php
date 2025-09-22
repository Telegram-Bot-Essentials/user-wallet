<?php

namespace TelegramBotEssentials\UserWallet;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use TelegramBotEssentials\Essence\Exceptions\LogicException;

class TbeUserWalletServiceProvider extends ServiceProvider
{
    /**
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        replyKeyBus()->addReplyKeys([

        ]);

        callbackQueryBus()->addCallbackQueries([

        ]);

        stateAnswerBus()->addStateAnswers([

        ]);
    }
}
