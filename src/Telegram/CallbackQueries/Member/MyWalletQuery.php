<?php

namespace TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Member;

use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;
use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;

class MyWalletQuery extends CallbackQuery
{
    protected string $type = 'MYWALLET';
    protected int $perm = Roles::MEMBER->value;

    /**
     * @throws BindingResolutionException
     * @throws TelegramSDKException
     * @throws LogicException
     */
    public function handle(array $params): void
    {
        $this->params = $params;
        switch (strtolower($params[0])) {
            case "add_credit":
                $this->addCredit();
                break;
        }
    }

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    public function addCredit(): void
    {
        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->deleteMessage();
        wHook()->user()->changeState(encodeAnswerState($this->type, "add_credit", [
            'message_meta_id' => $messageMeta->id
        ]));
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.enterCreditAmount'),
            'reply_markup' => wHook()->user()->getKeyboard()
        ]);
    }
}
