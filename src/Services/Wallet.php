<?php

namespace TelegramBotEssentials\UserWallet\Services;


use Brick\Math\BigDecimal;
use TelegramBotEssentials\Billing\Services\CurrencyFather;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Telegram\Bot\Exceptions\TelegramSDKException;

class Wallet
{
    /**
     * @param BigDecimal|string $amount
     * @throws FeatureIsDisabled
     * @throws TbeLogicException
     * @throws TelegramSDKException
     * @throws LogicException
     * @throws BindingResolutionException
     */
    public function takeAmount(BigDecimal|string $amount): void
    {
        $this->validateAmount($amount);
        $amountForUser = $this->convertAmountToUserCurrency($amount);
        $this->validateMethodAllowed();
        $this->validateUserBalanceIsSufficient($amount);

        wHook()->user()->wallet->balance = $this->currentUserWalletBalance()->minus($amountForUser);
        wHook()->user()->wallet->save();

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.takeAmountSuccess', [
                'amount' => currency()->priceFormat($amountForUser, currency: $this->currentUserWalletCurrency()),
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
     * @throws InsufficientBalanceException
     */
    private function validateUserBalanceIsSufficient(BigDecimal|string $amount): void
    {
        $this->validateAmount($amount);
        $amountForUser = $this->convertAmountToUserCurrency($amount);
        if (BigDecimal::of($amountForUser)->compareTo($this->currentUserWalletBalance()) > 0) {
            throw new InsufficientBalanceException(__('tbe-user-wallet::invoice.by_wallet.answers.creditIsNotEnough', [
                'credit' => currency()->priceFormat(
                    $this->currentUserWalletBalance(),
                    currency: $this->currentUserWalletCurrency()
                ),
                'neededCredit' => currency()->priceFormat(
                    $amountForUser,
                    currency: $this->currentUserWalletCurrency()
                )
            ]));
        }
    }

    public function currentUserWalletBalance(): BigDecimal
    {
        return BigDecimal::of(wHook()->user()->wallet->balance);
    }

    public function currentUserWalletCurrency(): string
    {
        return wHook()->user()->wallet->currency;
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
        $this->validateUserBalanceIsSufficient($amount);

        wHook()->user()->wallet->balance = $this->currentUserWalletBalance()->plus($amount);
        wHook()->user()->wallet->save();

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

        wHook()->user()->wallet->balance = $amount;
        wHook()->user()->wallet->save();

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.setAmountSuccess', [
                'amount' => currency()->priceFormat($amount),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }

    private function convertAmountToUserCurrency(BigDecimal|string $amount): BigDecimal|string
    {
        return BigDecimal::of(CurrencyFather::from(settings()->get('billing.currency'))
            ->amount($amount)
            ->to($this->currentUserWalletCurrency()));
    }
}
