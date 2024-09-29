# Feed Canary

![Tests](https://github.com/mattstein/feed-canary/actions/workflows/ci.yaml/badge.svg)

![The anxious canary](./public/social-card.png)

This is the Laravel app behind [feedcanary.com](https://feedcanary.com), a free little service for making sure an RSS or JSON feed is up and valid. It sends an email if the feed looks broken, and again if it seems to be fixed.

## Overview

Feed Canary checks a URL’s response status and passes changed feed content off to the [W3C validator](https://validator.w3.org/feed) or [feedvalidator.org](https://www.feedvalidator.org) in order to see if the it’s valid.

## Installation & Setup

This is a pretty straightforward Laravel app that relies heavily on the scheduler and queue.

1. Establish a modern PHP hosting environment with MySQL or MariaDB and ideally Redis. (PostgreSQL is probably fine, I just haven’t tested with it!)
2. Check out this project, or your fork of it.
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

The project should then be available at https://feedcanary.ddev.site/, where you can safely poke at it.

Email will go straight to [Mailpit](https://mailpit.axllent.org), which you can launch in a browser with `ddev mailpit`.

## Production

What I’m using:

- [Coolify](https://coolify.io) for hosting
- A 2-core, 2GB memory VPS for running the app
- [Laravel Horizon](https://laravel.com/docs/11.x/horizon) for swanky queue monitoring
- [Resend](https://resend.com), [Mailgun](https://www.mailgun.com), *and* [Mailtrap](https://mailtrap.io) for email! (because it’s nice to have [failovers](https://laravel.com/docs/11.x/mail#failover-configuration))
- [Sentry](http://sentry.io) for profiling and mostly catching mistakes and trying to fix them before anybody notices

It’s important to set this up with a stable queue! You can check out the project’s [Dockerfile](https://github.com/mattstein/feed-canary/blob/main/Dockerfile) that’s used as the basis for each app container in the [docker-compose.yaml](https://github.com/mattstein/feed-canary/blob/main/docker-compose.yaml) file I use with Coolify. I manually created PostgreSQL and Redis services separately. (I [mentioned this](https://mattstein.com/thoughts/diving-into-coolify/#feedcanary) in a blog post about transitioning from Ploi to Coolify.)

I added [Laravel Horizon](https://laravel.com/docs/11.x/horizon) to the project to keep closer watch over the queue and its performance. (Okay also curiosity.) You’ll need to add your IP address to the allow list in order to visit `/horizon` and have a look for yourself.

### System Resources

The VPS I’m currently running is dedicated to this app. It chugs along fine using about half its available resources checking a hundred or so feeds. YMMV.

### Maintenance

I built in a few commands for checking on things and tidying up:

- `php artisan app:check-feed {id}` lets you run a check on a single feed, passing its ID.
- `php artisan app:prune-checks` deletes rows in the `checks` table—by far the busiest in the database—that are older than thirty days.
- `php artisan app:audit-feeds` identifies feeds that were added more than once and email addresses associated with multiple feeds. I didn’t put any hard limits around these things, so for now it just helps to take inventory.

## Contributing

I welcome any thoughtful PRs that might improve the efficiency, design, or user experience of this little project! I’m sure there’s plenty of room for improvement.

My intent with this repository is to share the source code behind the site, not so much to formally release and maintain an app for broader use. As such, I’ll try and be helpful with issues but you may need to embrace the adventure of running your own instance in your favorite environment.

If you’ve found a bug, done some refactoring, or added a feature you’d like to share, please open an [issue](https://github.com/mattstein/feed-canary/issues) or [PR](https://github.com/mattstein/feed-canary/pulls) on this repository and I’ll respond to it.
