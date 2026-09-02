# Telegram Bot Essentials — User Wallet

[![Latest Version](https://img.shields.io/packagist/v/telegram-bot-essentials/user-wallet.svg)](https://packagist.org/packages/telegram-bot-essentials/user-wallet)
[![tests](https://github.com/Telegram-Bot-Essentials/user-wallet/actions/workflows/tests.yml/badge.svg)](https://github.com/Telegram-Bot-Essentials/user-wallet/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Gives every bot user a per-bot balance/credit wallet: top up via
[Billing](https://github.com/Telegram-Bot-Essentials/billing) invoices, spend as a payment
gateway on other invoices, and admin-adjust manually.

It's also the reference implementation for extending
[`user-management`](https://github.com/Telegram-Bot-Essentials/user-management) and for
Billing's gateway registry.

## Installation

```bash
composer require telegram-bot-essentials/user-wallet
php artisan migrate
```

Enable it per-bot via the `billing.user_wallet.status` setting (a `CHECKBOX`, default
`false`) — every user-facing wallet action is gated behind it.

## Usage

A `wallet` relation is attached to essence's `BotUser` at runtime — no need to modify
`BotUser`. Accessing `$botUser->wallet` always returns a real, saved zero-balance row on
first use.

All balance mutations go through the `wallet()` service, which row-locks inside a
transaction:

```php
wallet()->currentUserWalletBalance();          // read

wallet()->addAmount('10.00');                  // top up + notify the user
wallet()->takeAmount('4.50');                  // spend + notify; throws InsufficientBalanceException if short
wallet()->setAmount('0');                      // hard-set + notify

wallet()->adjustBalance('2.00', allowNegative: true); // system-initiated: no feature-flag check, no notification
```

`addAmount` / `takeAmount` / `setAmount` are the user-facing entry points and respect the
feature toggle. `adjustBalance` is for system mutations (affiliate commissions, refunds)
that must keep working even when the wallet *feature* is off. Amounts are
`Brick\Math\BigDecimal|string` — pass strings for precision.

The wallet registers itself as a Billing gateway (pay any invoice from your balance) and,
when User Management is installed, adds a `wallet_balance` sort column and a per-user
section — both wrapped in `class_exists()` guards so the package works standalone.

## Documentation

Full documentation — `CreditOrder` top-ups, the wallet-as-gateway flow, admin adjustment,
and the User Management integration — lives on the Telegram Bot Essentials documentation
site under **Modules → User Wallet**.

## License

[MIT](LICENSE).
