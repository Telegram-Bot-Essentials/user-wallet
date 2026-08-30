<?php

namespace TelegramBotEssentials\UserWallet\Telegram\StateAnswers\Admin;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Validator;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Enums\AllowableFields;
use TelegramBotEssentials\Essence\Enums\Roles;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use TelegramBotEssentials\Essence\Models\BotUser;
use TelegramBotEssentials\Essence\Telegram\StateAnswers\StateAnswer;
use TelegramBotEssentials\UserManagement\Models\BotUserAction;
use TelegramBotEssentials\UserManagement\Telegram\Features\Admin\BotUsersFeature;

class AdminWalletAnswer extends StateAnswer
{
    protected string $type = 'ADMINWALLET';

    protected int $perm = Roles::ADMIN->value;

    protected array $allowedFields = [
        AllowableFields::TEXT->value,
    ];

    /**
     * @throws TelegramSDKException
     * @throws InsufficientBalanceException
     */
    public function adjust(int $bot_user_id): void
    {
        $amount = wHook()->update()->message->text;

        Validator::validate(
            ['amount' => $amount],
            ['amount' => 'required|numeric|not_in:0|min:-100000000|max:100000000'],
        );

        $target = BotUser::query()->findOrFail($bot_user_id);
        $amount = BigDecimal::of($amount);
        $admin = wHook()->user();

        wHook()->runForUser($target, fn () => wallet()->adjustBalance($amount, allowNegative: true));

        BotUserAction::create([
            'bot_user_id' => $target->id,
            'update_type' => 'admin_wallet_adjustment',
            'action' => __('tbe-user-wallet::admin_wallet.main.text.auditEntry', [
                'admin' => $admin->telegramUser->username ? '@'.$admin->telegramUser->username : $admin->telegramUser->full_name,
                'amount' => currency()->priceFormat($amount),
            ]),
        ]);

        $data = BotUsersFeature::show($target);

        wHook()->user()->changeState();
        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::admin_wallet.main.text.adjustSuccess', [
                'amount' => currency()->priceFormat($amount),
                'balance' => currency()->priceFormat($target->wallet->balance),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);

        $this->requireMessageMeta()->updateAndContinueAction($data);
    }
}
