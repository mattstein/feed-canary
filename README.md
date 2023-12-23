# Feed Canary

## Management

MailHog: https://feedcanary.ddev.site:8026

Restart the production queue after pushing any changes relevant to the scheduler:

```
php8.2 artisan queue:restart
```