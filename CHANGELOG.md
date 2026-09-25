# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Until the API
stabilizes at 1.0 a `0.0.x` bump may carry breaking changes.

## [Unreleased]

### Fixed

- Paying an invoice from the wallet is now atomic. The debit and the payment
  attempt are committed together (`Wallet::payInvoice()`), and the "wallet
  debited" message is sent only after that commit. Before, the wallet was
  debited first and the attempt recorded afterwards, so any failure in between
  left the member charged with nothing paid.
- Databases created before `by_wallet_attempts` gained `bot_id` and
  `bot_user_id` (that migration was edited in place) get the columns from a new
  migration; paying from the wallet failed on them with
  `Unknown column 'bot_id'`. Existing attempts are filled in from their invoice.

## [0.0.25] - 2026-09-22

### Changed

- Accepts `telegram-bot-essentials/essence` `^0.14` as well as `^0.13`:
  0.14.0 only removed `DoneLimited`/`CannotSetItAsDone`/`HidesDone`, none of
  which this package uses.

## [0.0.24] - 2026-09-20

### Fixed

- A wallet top-up could be discounted with an offer code, letting a customer
  buy credit for less than its face value. `CreditOrder::offersAllowed()`
  overrides `tbe-billing`'s new hook to refuse one.

## [0.0.22] - 2026-09-01

### Changed

- **BREAKING:** requires `telegram-bot-essentials/essence` `^0.12`. Handlers
  are locale-lazy, and the admin balance-adjust flow resumes through
  `StateAnswer::requireMessageMeta()` so an abandoned prompt gets a "step
  expired" notice instead of crashing the worker.

### Added

- List-header block (when `telegram-bot-essentials/user-management` is
  installed and the wallet feature is on): summed balance across every wallet
  and how many users hold one with money in it. `CreditOrder::statsLabel()`
  names top-ups in Billing's per-type invoice breakdown (0.0.21).
- Pest test suite, Laravel Pint, Larastan (level max), GitHub Actions CI,
  Laravel Workbench, `LICENSE` (MIT) and this changelog.
