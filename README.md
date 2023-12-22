# Feed Canary

- [x] feed confirmation flow
- [x] feed broken email
- [x] home view
- [x] manage feed view
- [x] queue checking job
- [x] initial feed add action
- [x] garbage collect unconfirmed feeds
- [x] production logging
- [x] Mailgun setup
- [x] feed fixed notification
- [ ] re-send confirmation flow
- [x] prep link to W3C validator
- [ ] measure feed processing performance somehow
- [ ] prevent duplicate additions

## Management

Restart the production queue after pushing any changes relevant to the scheduler:

```
php8.2 artisan queue:restart
```