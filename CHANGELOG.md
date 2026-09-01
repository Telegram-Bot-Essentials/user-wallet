# Changelog

All notable changes to this project are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/). Until the API
stabilizes at 1.0 a `0.0.x` bump may carry breaking changes.

## [Unreleased]

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
