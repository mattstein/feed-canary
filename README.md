# Feed Canary

![Tests](https://github.com/mattstein/feed-canary/actions/workflows/ci.yaml/badge.svg)

![The anxious canary](./public/social-card.png)

This is the Laravel app behind [feedcanary.com](https://feedcanary.com), a free little service for making sure an RSS or JSON feed is up and valid. It sends an email if the feed looks broken, and again if it seems to be fixed.

## Overview

Feed Canary is a glorified scheduler and queue, checking response status and attempting to pass changed feed content only off to the [W3C validator](https://validator.w3.org/feed) or [feedvalidator.org](https://www.feedvalidator.org) in order to see if the content is valid.

## Installation & Setup

This is a pretty straightforward Laravel app that relies heavily on the scheduler and queue.

1. Establish a modern PHP hosting environment with MySQL or MariaDB and ideally redis. (You might be able to use PostgreSQL, I just haven’t tested with it!)
2. Check out this project, or your fork of it, with Composer.
3. Install Composer dependencies with `composer install`.
4. Install npm dependencies with `npm install`. (Run `npm run build` to have Vite bundle fresh CSS.)
5. Set up a cron job to run the scheduler.
6. Set up a queue worker.
7. Customize `.env` for your environment, being sure to set up at least a working mailer. (Optionally backups, Sentry monitoring, etc.)
8. Run `php artisan migrate` to initialize the database.

## Local Development

This project includes a [DDEV](https://ddev.com) configuration for local development, so once DDEV and a Docker provider are installed you can run the following:

1. `cp .env.example.local .env`
2. `ddev start`
3. `ddev composer install`
4. `ddev npm install`
5. `ddev php artisan key:generate`
6. `ddev php artisan migrate`

The project should then be available at https://feedcanary.ddev.site/.

## Production

What I’m using:

- [Ploi](https://ploi.io) for provisioning
- A 2-core, 2GB memory VPS for running the app
- [Laravel Horizon](https://laravel.com/docs/11.x/horizon) for swanky queue monitoring
- Spatie’s [Laravel-Backup package](https://github.com/spatie/laravel-backup)
- [Resend](https://resend.com), [Mailgun](https://www.mailgun.com), *and* [Mailtrap](https://mailtrap.io) for email! (because it’s nice to have [failovers](https://laravel.com/docs/11.x/mail#failover-configuration))
- [Sentry](http://sentry.io) for a little bit of profiling and mostly catching mistakes and trying to fix them before anybody notices.

The most important part of the production setup is a stable queue. I’ve used the Redis driver, a cron job for the scheduler [like the Laravel docs recommend](https://laravel.com/docs/11.x/scheduling#running-the-scheduler), and a queue task with a single worker.

Be sure to restart the queue after pushing any changes relevant to the scheduler:

```
php8.3 artisan queue:restart
```

I added [Laravel Horizon](https://laravel.com/docs/11.x/horizon) to the project to keep closer watch over the queue and its performance. (Okay also curiosity.) You’ll need to add your IP address to the allow list in order to visit `/horizon` and have a look for yourself.

The supervisor config I’m using:

```
command=/usr/bin/php /path/to/artisan queue:work redis --timeout=60 --sleep=10 --tries=3 --queue="default" --memory=128 --backoff=5 --env="production"
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=ploi
redirect_stderr=true
numprocs=1
```

My Ploi deployment script looks like this:

```
cd /path/to/project/root
git pull origin main
{SITE_COMPOSER} install --no-interaction --prefer-dist --optimize-autoloader --no-dev
{RELOAD_PHP_FPM}

{SITE_PHP} artisan route:cache
{SITE_PHP} artisan view:clear
{SITE_PHP} artisan optimize:clear
{SITE_PHP} artisan migrate --force
{SITE_PHP} artisan horizon:terminate

echo "🚀 Application deployed!"
```

Lastly, I added the [spatie/laravel-backup](https://github.com/spatie/laravel-backup) package to easily take database-only offsite backups.

### System Resources

The VPS I’m currently running is dedicated to this app, and it does alright despite its frighteningly slow networking. It chugs along just fine using about half its available resources checking a hundred or so feeds. YMMV.

### Maintenance

I built in a few commands for checking on things and tidying up:

- `php artisan app:check-feed {id}` lets you run a check on a single feed, passing its ID.
- `php artisan app:prune-checks` deletes rows in the `checks` table—by far the busiest in the database—that are older than thirty days.
- `php artisan app:audit-feeds` identifies feeds that were added more than once and email addresses associated with multiple feeds. I didn’t put any hard limits around these things, so for now it just helps to take inventory.

## Contributing

I welcome any thoughtful PRs that might improve the efficiency, design, or user experience of this little project! I’m sure there’s plenty of room for improvement.

My intent with this repository is mostly to share the source code behind the site, not so much to formally release and maintain an app for broader use. As such, I’ll try and be helpful with issues but you may need to embrace the adventure of running your own instance in your favorite environment.

If you’ve found a bug, done some refactoring, or added a feature you’d like to share, please open an [issue](https://github.com/mattstein/feed-canary/issues) or [PR](https://github.com/mattstein/feed-canary/pulls) on this repository and I’ll respond to it.
