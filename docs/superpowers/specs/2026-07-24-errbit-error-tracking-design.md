# Errbit error tracking

**Date:** 2026-07-24
**Status:** Implemented

## Goal

Report unhandled exceptions to the self-hosted Errbit instance at
https://errors.catlab.eu using the Airbrake v3 notice API.

## Approach

Use the official `airbrake/phpbrake` package (chosen over a hand-rolled
notifier for its battle-tested backtrace formatting and filter support).

## Components

- **`config/services.php` → `errbit`** — `host`, `project_id`, `project_key`
  from `ERRBIT_*` env vars. `enabled` defaults to `APP_ENV === 'production'`
  and can be overridden with `ERRBIT_ENABLED`. The project key lives only in
  the server's `.env`, never in the repo.
- **`App\Providers\ErrbitServiceProvider`** — registers `Airbrake\Notifier`
  as a lazy singleton. `remoteConfig` is disabled: phpbrake would otherwise
  fetch its configuration from airbrake.io, which fails for an Errbit host
  and silently disables all notifications.
- **`App\Exceptions\Handler::report()`** — sends the exception to Errbit when
  reporting is enabled and the exception passes `shouldReport()` (the
  existing `$dontReport` list is respected). Notifier failures are swallowed:
  Errbit being unreachable must never break error handling itself.

## Testing

`tests/Unit/Errbit/ErrbitReportingTest` covers: reported exceptions reach the
notifier, `$dontReport` exceptions don't, nothing is sent when disabled,
notifier failures don't propagate, and the notifier resolves from config.
A live smoke test against errors.catlab.eu was accepted (notice id returned).
