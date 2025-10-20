<?php

namespace TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Member;

use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Billing\Services\CurrencyFather;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;
use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\UserWallet\Models\ByWalletAttempt;

class MyWalletQuery extends CallbackQuery
{
    protected string $type = 'MYWALLET';
    protected int $perm = Roles::MEMBER->value;

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     */
    function addCredit(): void
    {
        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->deleteMessage();
        wHook()->user()->changeState(encodeAnswerState($this->type, "add_credit", [
            'message_meta' => $messageMeta->id
        ]));
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.enterCreditAmount'),
            'reply_markup' => wHook()->user()->getKeyboard()
        ]);
    }

    /**
     * @throws TelegramSDKException
     */
    function byWallet(Invoice $invoice): void
    {
        wallet()->takeAmount($invoice->price);

        $byWalletAttempt = ByWalletAttempt::create([
            'amount' => $invoice->price
        ]);

        billing()->attemptPayment($invoice, $byWalletAttempt);

        $byWalletAttempt->attemptSucceed();
        $invoice->messageMeta->lockAction(__('tbe-user-wallet::invoice.to_card.lock-keys.user-payment_accepted'), customEmoji: "✅");
    }
}
