# feed-canary

[feedcanary.com](https://feedcanary.com): free service for making sure an RSS or JSON feed is up and valid, and emailing you when it’s not.

## Stack

- PHP 8.5 · Laravel 13.0 · Livewire 4.0 · Horizon 5.26 · Octane (FrankenPHP)
- Local: DDEV (PHP 8.5, PostgreSQL)
- Production: MySQL and Redis are separate Coolify resources; compose runs scheduler and Horizon workers only
- Mail: `usesend-laravel`, plus resend, mailtrap, mailgun, laminas-feed

## CI gates (`.github/workflows/ci.yaml`)

```bash
composer install --no-interaction --no-ansi --no-progress
./vendor/bin/phpstan analyse --memory-limit=2G
./vendor/bin/pint --test
cp .github/.env.ci .env && php artisan key:generate && php artisan migrate
php artisan test
```

## Local commands

```bash
ddev exec ./vendor/bin/phpstan analyse --memory-limit=2G
ddev exec ./vendor/bin/pint --test
ddev exec ./vendor/bin/pest
```

## Notes
- Tests run against sqlite `:memory:` with sync queue and array cache/mail — a green suite says nothing about Horizon or Redis behaviour.
- Feed parsing is the product. `laminas-feed` edge cases are worth a regression test.

## Ground rules

- **Everything runs through DDEV.** Host PHP, composer, pint and phpstan are deliberately not installed. `ddev composer`, `ddev exec ./vendor/bin/...`, `ddev php artisan ...`.
- **Never commit, push, amend, rebase or reset.** Matt handles all git operations. Leave changes in the working tree and report what changed.
- **Match CI exactly before claiming a change passes.** Running a narrower command than the workflow does is how rollouts break. The commands below are copied from the workflow files, not from habit.
- Prefer `patch` over shell `sed`/`awk` for edits.
- Additive migrations only, and `ENV AUTORUN_ENABLED=true` plus `ENV AUTORUN_LARAVEL_MIGRATION=true` must both be present in the Coolify Dockerfile — the migration flag alone silently no-ops.
