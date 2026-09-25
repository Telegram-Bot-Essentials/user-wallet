<?php

namespace TelegramBotEssentials\UserWallet\Telegram\CallbackQueries\Member;

use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicException;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\CallbackQueries\CallbackQuery;

class MyWalletQuery extends CallbackQuery
{
    protected string $type = 'MYWALLET';

    protected int $perm = Roles::MEMBER->value;

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     * @throws FeatureIsDisabled
     */
    public function addCredit(): void
    {
        dependsOn(settings()->get('billing.user_wallet.status'));
        $messageMeta = MessageMeta::makeWithCurrentMessage();
        $messageMeta->deleteMessage();
        wHook()->user()->changeState(encodeAnswerState($this->type, 'add_credit', [
            'message_meta' => $messageMeta->id,
        ]));
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.enterCreditAmount'),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }

    /**
     * @throws BindingResolutionException
     * @throws FeatureIsDisabled
     * @throws LogicException
     * @throws TelegramSDKException
     * @throws TbeLogicException
     */
    public function byWallet(Invoice $invoice): void
    {
        dependsOn(settings()->get('billing.user_wallet.status'));
        // The debit and the attempt commit together; only then is the invoice marked paid.
        $byWalletAttempt = wallet()->payInvoice($invoice);

        $byWalletAttempt->attemptSucceed();
        $invoice->messageMeta->lockAction(__('tbe-user-wallet::invoice.locks.user_payment.accepted'), customEmoji: '✅');
    }
}
