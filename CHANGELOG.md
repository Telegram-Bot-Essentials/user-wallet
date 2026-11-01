# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Until the API
stabilizes at 1.0 a `0.0.x` bump may carry breaking changes.

## [Unreleased]

## [0.0.35] - 2026-11-01

### Added

- `/wallet`, for groups too. Groups have no reply keyboard, so it stands in for
  the wallet key there, and answers with a link to the private chat rather
  than show a balance. Needs essence's `continueInPrivate()`.

## [0.0.34] - 2026-11-01

### Added

- `lang/<locale>/abilities.php` labels this package's Gate abilities for
  essence's `abilities()` catalog, so a roles screen (tbe-access) shows
  them by name.

## [0.0.33] - 2026-10-31

### Fixed

- Requires essence 0.19.0-beta.2 or later; 0.19.0-beta.1 still had the
  permission API.

## [0.0.32] - 2026-10-31

### Changed

- Requires essence 0.19. The admin handlers name a Laravel Gate ability
  (`user-wallet.wallets.manage`) instead of a permission, and nothing is registered with essence
  any more: the app's Gate decides who holds it. Essence no longer lets the
  bot owner through by default, so an app grants these with `Gate::before`
  or `Gate::define`.

### Removed

- The permission labels (`lang/*/permissions.php`).

## [0.0.31] - 2026-10-12

### Changed

- Requires essence 0.18. A flow's state is read and written on the webhook's
  conversation (`wHook()->conversation()`) instead of the user, so it stays
  in the chat it started in.

## [0.0.30] - 2026-10-12

Requires essence 0.17, which replaces power levels with named permissions.

### Changed

- The admin screens ask for `user-wallet.wallets.manage` instead of the admin power level.
  The names are registered with essence's permission registry, so a role
  package can grant them separately.

## [0.0.29] - 2026-10-01

### Changed

- Reworded the user-facing English and Persian strings to read more naturally; no keys or placeholders changed.

## [0.0.28] - 2026-09-28

### Changed

- Accepts essence 0.16 alongside 0.15.

## [0.0.27] - 2026-09-27

### Changed

- Requires essence `^0.15`, which fills the `{placeholders}` in these
  messages.
- Wallet log messages carry the amount and resulting balance
  (`Wallet credited 50000, balance now 120000`) instead of a bare
  `Wallet credited`.

## [0.0.26] - 2026-09-25

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
