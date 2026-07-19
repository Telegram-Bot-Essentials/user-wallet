<?php

namespace TelegramBotEssentials\UserWallet\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

class Wallet
{
    /**
     * @param BigDecimal|string $amount
     * @throws FeatureIsDisabled
     * @throws TbeLogicException
     * @throws TelegramSDKException
     * @throws LogicException
     * @throws BindingResolutionException
     * @throws InsufficientBalanceException
     */
    public function takeAmount(BigDecimal|string $amount): void
    {
        $this->validateAmount($amount);
        $this->validateMethodAllowed();

        DB::transaction(function () use ($amount) {
            $wallet = $this->lockedWallet();
            $this->validateBalanceIsSufficient($wallet, $amount);

            $wallet->balance = BigDecimal::of($wallet->balance)->minus($amount);
            $wallet->save();
        });

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.takeAmountSuccess', [
                'amount' => currency()->priceFormat($amount),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }

    private function validateAmount(BigDecimal|string &$amount): void
    {
        if (!($amount instanceof BigDecimal)) {
            $amount = BigDecimal::of($amount);
        }
    }

    /**
     * @throws FeatureIsDisabled
     */
    public function validateMethodAllowed(): void
    {
        dependsOn(settings()->get('billing.user_wallet.status'), __('tbe::general.alerts.disabledFeature', ['feature' => __('tbe::bot_settings.wallet.name')]));
    }

    /**
     * Ensures the row backing $wallet exists, then re-fetches it with a row lock
     * so concurrent balance mutations for the same user serialize instead of
     * racing on a stale read.
     */
    private function lockedWallet(): BotUserWallet
    {
        $wallet = wHook()->user()->wallet;

        return BotUserWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @throws InsufficientBalanceException
     */
    private function validateBalanceIsSufficient(BotUserWallet $wallet, BigDecimal|string $amount): void
    {
        if (BigDecimal::of($amount)->compareTo($wallet->balance) > 0) {
            throw new InsufficientBalanceException(__('tbe-user-wallet::invoice.by_wallet.answers.creditIsNotEnough', [
                'credit' => currency()->priceFormat($wallet->balance),
                'neededCredit' => currency()->priceFormat($amount),
            ]));
        }
    }

    public function currentUserWalletBalance(): BigDecimal
    {
        return BigDecimal::of(wHook()->user()->wallet->balance);
    }

    /**
     * @param BigDecimal|string $amount
     * @throws FeatureIsDisabled
     * @throws TbeLogicException
     * @throws TelegramSDKException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function addAmount(BigDecimal|string $amount): void
    {
        $this->validateAmount($amount);
        $this->validateMethodAllowed();

        DB::transaction(function () use ($amount) {
            $wallet = $this->lockedWallet();

            $wallet->balance = BigDecimal::of($wallet->balance)->plus($amount);
            $wallet->save();
        });

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.addAmountSuccess', [
                'amount' => currency()->priceFormat($amount),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }

    public function setAmount(BigDecimal|string $amount): void
    {
        $this->validateAmount($amount);
        $this->validateMethodAllowed();

        DB::transaction(function () use ($amount) {
            $wallet = $this->lockedWallet();

            $wallet->balance = $amount;
            $wallet->save();
        });

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.setAmountSuccess', [
                'amount' => currency()->priceFormat($amount),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }
}
