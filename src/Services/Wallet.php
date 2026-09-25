<?php

namespace TelegramBotEssentials\UserWallet\Services;

use Brick\Math\BigDecimal;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Exceptions\TelegramSDKException;
use TelegramBotEssentials\Billing\Models\Invoice;
use TelegramBotEssentials\Essence\Exceptions\FeatureIsDisabled;
use TelegramBotEssentials\Essence\Exceptions\LogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicException;
use TelegramBotEssentials\Essence\Exceptions\TbeLogicExceptions\InsufficientBalanceException;
use TelegramBotEssentials\UserWallet\Models\BotUserWallet;
use TelegramBotEssentials\UserWallet\Models\ByWalletAttempt;

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

        $amount = BigDecimal::of($amount);
        $this->debit($amount);
        $this->notifyDebited($amount);
    }

    /**
     * Pays an invoice from the wallet as one unit: the debit and the payment attempt
     * are committed together or not at all, so a failure while recording the attempt
     * can never leave the member debited with nothing paid. The "wallet debited"
     * message is sent only after that commit, for the same reason.
     *
     * The caller marks the attempt succeeded (which pays the invoice) afterwards.
     *
     * @throws FeatureIsDisabled
     * @throws InsufficientBalanceException
     */
    public function payInvoice(Invoice $invoice): ByWalletAttempt
    {
        $price = (string) $invoice->getAttribute('price');
        $amount = BigDecimal::of($price);
        $this->validateMethodAllowed();

        $attempt = DB::transaction(function () use ($invoice, $amount, $price) {
            $this->debit($amount);

            $attempt = ByWalletAttempt::create(['amount' => $price]);
            billing()->attemptPayment($invoice, $attempt);

            return $attempt;
        });

        $this->notifyDebited($amount);

        return $attempt;
    }

    /** The ledger part of a debit: checks the balance under a row lock and lowers it. */
    private function debit(BigDecimal $amount): void
    {
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
    }

    private function notifyDebited(BigDecimal $amount): void
    {
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
