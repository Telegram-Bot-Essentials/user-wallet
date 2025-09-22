<?php

namespace TelegramBotEssentials\UserWallet\Telegram\StateAnswers\Member;

use App\Models\Order;
use App\Telegram\Features\Member\BuyServiceFeature;
use Illuminate\Support\Facades\Validator;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\AllowableFields;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Models\CreditOrder;
use TelegramBotEssentials\Essence\Models\MessageMeta;
use TelegramBotEssentials\Essence\Telegram\Features\InvoiceFeature;
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
     */
    public function handle(string $method): void
    {
        switch (strtolower($method)) {
            case "add_credit":
                $this->addCredit();
                break;
        }
    }

    /**
     * @throws TelegramSDKException
     */
    private function addCredit(): void
    {
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
            'text' => "Creating invoice for amount of " . currency()->priceFormat($amount) . " 💸", // TODO: Localize this message
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);

        InvoiceFeature::invoice($invoice)->send();
    }

    /**
     * @throws TelegramSDKException
     */
    function cancel(): void
    {
        $messageMeta = MessageMeta::find($this->params['message_meta_id']);
        if ($messageMeta) {
            $messageMeta->continueAction();
        }
    }
}
