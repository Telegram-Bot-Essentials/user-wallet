<?php

namespace TelegramBotEssentials\UserWallet\Services;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;

class Wallet
{
    /**
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
            wHook()->user()->setRelation('wallet', $wallet);
        });

        tbeLog('user-wallet')->info('Wallet debited', [
            'wallet_id' => wHook()->user()->wallet->getKey(),
            'amount' => (string) $amount,
            'balance_after' => (string) wHook()->user()->wallet->balance,
        ]);

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
        if (! ($amount instanceof BigDecimal)) {
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
            wHook()->user()->setRelation('wallet', $wallet);
        });

        tbeLog('user-wallet')->info('Wallet credited', [
            'wallet_id' => wHook()->user()->wallet->getKey(),
            'amount' => (string) $amount,
            'balance_after' => (string) wHook()->user()->wallet->balance,
        ]);

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
            wHook()->user()->setRelation('wallet', $wallet);
        });

        tbeLog('user-wallet')->info('Wallet balance set', [
            'wallet_id' => wHook()->user()->wallet->getKey(),
            'balance_after' => (string) wHook()->user()->wallet->balance,
        ]);

        wHook()->api()->sendMessage([
            'chat_id' => wHook()->user()->telegramUser->peer_id,
            'text' => __('tbe-user-wallet::my_wallet.main.text.setAmountSuccess', [
                'amount' => currency()->priceFormat($amount),
            ]),
            'reply_markup' => wHook()->user()->getKeyboard(),
        ]);
    }

    /**
     * System-initiated balance mutation (affiliate commissions/bonuses, refund
     * reversals, etc). Unlike addAmount()/takeAmount() this does NOT check
     * validateMethodAllowed() — the wallet-feature toggle only governs whether
     * a user can manually top up or spend their wallet, not whether the
     * underlying ledger keeps working for automated system credits/debits.
     * It also never sends a message; callers own their own notification copy.
     *
     * @throws InsufficientBalanceException
     */
    public function adjustBalance(BigDecimal|string $amount, bool $allowNegative = false): void
    {
        $this->validateAmount($amount);

        DB::transaction(function () use ($amount, $allowNegative) {
            $wallet = $this->lockedWallet();
            $newBalance = BigDecimal::of($wallet->balance)->plus($amount);

            if (! $allowNegative && $newBalance->isNegative()) {
                throw new InsufficientBalanceException(__('tbe-user-wallet::invoice.by_wallet.answers.creditIsNotEnough', [
                    'credit' => currency()->priceFormat($wallet->balance),
                    'neededCredit' => currency()->priceFormat($amount->abs()),
                ]));
            }

            $wallet->balance = $newBalance;
            $wallet->save();
            wHook()->user()->setRelation('wallet', $wallet);
        });

        tbeLog('user-wallet')->info('Wallet balance adjusted by system', [
            'wallet_id' => wHook()->user()->wallet->getKey(),
            'amount' => (string) $amount,
            'balance_after' => (string) wHook()->user()->wallet->balance,
            'allow_negative' => $allowNegative,
        ]);
    }
}
