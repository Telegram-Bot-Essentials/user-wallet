<?php

namespace TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;

class AdminWalletQuery extends CallbackQuery
{
    public const TYPE = 'ADMINWALLET';

    protected string $type = self::TYPE;

    protected int $perm = Roles::ADMIN->value;

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    public function adjust(BotUser $botUser): void
    {
        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->lockAction(__('tbe-user-wallet::admin_wallet.main.text.waitingAmount'));
        wHook()->user()->changeState(encodeAnswerState($this->type, 'adjust', [
            'message_meta_id' => $messageMeta->id,
            'bot_user_id' => $botUser->id,
        ]));
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::admin_wallet.main.text.enterAmount', [
                'balance' => currency()->priceFormat($botUser->wallet->balance),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }
}
