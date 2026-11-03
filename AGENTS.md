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
cp .github/.env.ci .env
php artisan key:generate
php artisan migrate
composer check
```

## Notes

- Tests run against sqlite `:memory:` with sync queue and array cache/mail — a green suite says nothing about Horizon or Redis behaviour.
- Feed parsing is the product. `laminas-feed` edge cases are worth a regression test.

## Local commands

```bash
ddev composer test
ddev composer lint
ddev composer lint:fix
ddev composer phpstan
ddev composer check
```


