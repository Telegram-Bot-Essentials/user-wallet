<?php

namespace TelegramBotEssentials\UserWallet\Telegram\StateAnswers\Member;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Validator;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Billing\Telegram\Features\Member\InvoiceFeature;
use TelegramBotEssentials\Essence\Enums\AllowableFields;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\UserWallet\Models\CreditOrder;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;

class MyWalletAnswer extends StateAnswer
{
    protected string $type = 'MYWALLET';
    protected int $perm = Roles::MEMBER->value;
    protected array $allowedFields = [
        AllowableFields::TEXT->value
    ];

    /**
     * @throws TelegramSDKException
     * @throws BindingResolutionException
     * @throws LogicException
     * @throws FeatureIsDisabled
     */
    function addCredit(): void
    {
        dependsOn(settings()->get('billing.user_wallet.status'));
        $amount = wHook()->update()->message->text;
        Validator::validate(
            ['amount' => $amount],
            ['amount' => "required|numeric|min:0.01|max:100000000"]
        );

        $creditOrder = CreditOrder::create([
            'bot_user_id' => wHook()->user()->id,
            'amount' => $amount
        ]);

        $invoice = billing()->createInvoice($creditOrder);

        wHook()->user()->changeState();

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::credit_order.main.text.creatingInvoice', [
                'amount' => currency()->priceFormat($amount),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);

        InvoiceFeature::invoice($invoice)->send();
    }

    /**
     * @throws TelegramSDKException
     */
    function cancel(): void
    {
        $messageMeta = MessageMeta::find($this->params['message_meta']);
        if ($messageMeta) {
            $messageMeta->continueAction();
        }
    }
}
