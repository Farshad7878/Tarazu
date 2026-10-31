# ترازو (Tarazu)

> 🌐 Persian version: [README.md](README.md)

A general-purpose point-of-sale and back-office system built with **Laravel 12** (API + Sanctum tokens) and a **Vue 3** single-page app. It covers product catalogue management, reception-side order entry, customer membership, discounts, live notifications, and admin reporting. Categories and branding are configurable, so the system is not tied to one type of business.

## Features

- **Two roles**
  - `admin`: dashboard, customers, product categories, products, discounts, reports (including CSV export), and user management.
  - `reception`: new order entry, order history, customers, and a daily dashboard.
- **Orders**: real-time stock checks, rollback on any failure (no partial orders), and per-order discount breakdown.
- **Discounts**: percentage, fixed-amount, and free-product rewards. They can be auto-applied by condition (total purchases, monthly purchases, birthday) or assigned manually by an admin. Discounts marked *auto-disable after use* are never applied twice.
- **Customers**: unique 6-digit membership numbers, lookup by membership number, search, pagination, and per-customer order history.
- **Live notifications** (Laravel Reverb):
  - admins receive new-order, new-customer, and birthday events on the `admin.dashboard` private channel;
  - reception receives discount-applied events on its own `user.{id}` private channel.
- **Stored notifications**: admins see a bell in the navbar with an unread count. It lists their last 30 notifications, lets them mark one or all as read, and refreshes every minute. Each user sees only their own notifications.
- **Birthdays**: `php artisan check:birthdays` runs daily at 08:00 (`routes/console.php`). For each customer whose birthday is today it sends a congratulation SMS, assigns the birthday discount, and stores a notification for every active admin. `--notify` adds one summary notification listing all of today's birthdays.
- **SMS**: one `SmsService` sends all messages. The default driver only logs them. The `kavenegar` driver sends real SMS once an API key is set (see *Environment variables*). Failures are logged and never break an order or a customer save.
- **Reports**: revenue, orders, customers, and discount totals with period-over-period change, daily/hourly breakdowns, reception performance, and CSV export (UTF-8 with BOM, formula-injection safe for Excel).

## Requirements

- PHP 8.2+ with the `intl`, `pdo_sqlite` or `pdo_mysql`, `mbstring`, and `openssl` extensions
- Composer 2
- Node.js 20+ and npm
- A database: SQLite (default in `.env.example`) or MySQL/PostgreSQL

## Setup

```bash
git clone <repo-url> business-management-system
cd business-management-system

# installs PHP and JS dependencies, creates .env, generates APP_KEY, runs migrations, builds the frontend
composer run setup
```

`composer run setup` runs `migrate` but does not create any user accounts. Create the first admin and reception accounts with:

```bash
php artisan db:seed --class=CategorySeeder    # default product categories (optional, also run by db:seed)
php artisan db:seed --class=AdminUserSeeder   # creates admin@example.com and reception@example.com
```

> **Change the default passwords (`password`) immediately** after the first login, or delete those accounts in production. `AdminUserSeeder` is idempotent (`firstOrCreate`), so it is safe to run again.

### Local development

```bash
composer run dev
```

This starts five processes concurrently:

| Process | Purpose |
| --- | --- |
| `php artisan serve` | HTTP server (default http://127.0.0.1:8000) |
| `php artisan queue:listen` | Queue worker (required for queued listeners and broadcasts) |
| `php artisan reverb:start` | WebSocket server for live notifications |
| `php artisan pail` | Log tail |
| `npm run dev` | Vite dev server for the frontend |

In production, run the queue worker and Reverb under a process manager such as Supervisor or systemd, and build the frontend with `npm run build`:

```bash
php artisan queue:work --tries=3
php artisan reverb:start --host=0.0.0.0 --port=8080
```

## Environment variables

Copy `.env.example` to `.env` (done automatically by `composer run setup`). The keys that matter:

| Key | Notes |
| --- | --- |
| `APP_DEBUG` | `false` in `.env.example`. Set `true` only on a development machine: it exposes stack traces. |
| `APP_URL`, `APP_TIMEZONE` | `APP_TIMEZONE` defaults to `Asia/Tehran`; daily and monthly reports use it. |
| `DB_CONNECTION`, `DB_*` | SQLite by default. |
| `QUEUE_CONNECTION=database` | Queued listeners (welcome and birthday SMS, thank-you SMS) and broadcasts need a running `queue:work` / `queue:listen`. Run the migrations first so the `jobs` table exists. |
| `VAT_RATE` | VAT percentage (for example `9`). Default `0` = off. Prices are treated as VAT-inclusive: the tax is `amount × rate ÷ (100 + rate)` and the customer still pays the same total. Each order stores the rate used when it was placed, so changing this later does not change past orders. Only `0`–`100` is accepted; anything else counts as off. |
| `SANCTUM_TOKEN_EXPIRATION` | Minutes before an API token expires. Default `720` (12 hours). |
| `SMS_DRIVER` | `log` (default: messages are only written to the log) or `kavenegar`. |
| `SMS_KAVENEGAR_API_KEY`, `SMS_SENDER` | Required when `SMS_DRIVER=kavenegar`. Without a key the driver logs a warning and sends nothing. |
| `SMS_ORDER_THANK_YOU` | `false` by default. Set `true` to send a thank-you SMS after each order (costs money per message). |
| `BROADCAST_CONNECTION=reverb` | Set to `log` only if you do not need live notifications (the broadcast auth endpoint then accepts every channel, so do not use `log` in production). |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Change `REVERB_APP_SECRET` in production. |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | Where the WebSocket server is reachable from the server side. |
| `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | Values the browser uses to connect. They are compiled into the frontend, so run `npm run build` again after changing them. These default to the `REVERB_*` values. |

Private-channel authorization runs at `POST /api/broadcasting/auth` and requires a Sanctum bearer token. Channels are defined in `routes/channels.php`.

Set `SMS_DRIVER` in production and keep `APP_DEBUG=false`. Run `php artisan schedule:work` (development) or a cron entry `* * * * * php /path/to/artisan schedule:run` (production) so the daily birthday check runs.

## Roles and pages

| Role | Frontend routes | API prefix |
| --- | --- | --- |
| admin | `/admin/dashboard`, `/admin/customers`, `/admin/categories`, `/admin/products`, `/admin/discounts`, `/admin/reports`, `/admin/users` | `/api/admin/*` |
| reception | `/reception/dashboard`, `/reception/new-order`, `/reception/orders`, `/reception/customers` | `/api/reception/*` |

Each role is enforced server-side by the `admin` and `reception` middleware. Frontend route guards only improve the user experience. Admins cannot deactivate, demote, or delete their own account (the API returns 422).

## Authentication

- `POST /api/login` returns a Sanctum token. The SPA stores it and sends it as `Authorization: Bearer <token>`.
- Tokens expire after 12 hours (`SANCTUM_TOKEN_EXPIRATION`). Any API call that returns 401 clears the session and sends the user to the login page with an "expired" notice.
- Brute-force protection on login:
  - 5 failed attempts for the same email and IP lock that pair for 60 seconds; the next attempt returns `429` with a `Retry-After` header. A correct password does not bypass the lock.
  - The whole `/api/login` route is also limited to 30 requests per minute per IP.
- `GET /api/user` returns the current user and is used to restore the session on page load.
- `POST /api/logout` revokes the current token.
- The token is kept in `localStorage`. Moving it to an HttpOnly cookie is a larger change (it needs cookie-based auth and CSRF handling) and is not done yet.

## Useful commands

```bash
php artisan migrate                 # apply migrations
php artisan migrate:fresh --seed    # reset the database (destroys data)
php artisan db:seed --class=AdminUserSeeder
npm run build                       # production frontend build
php artisan route:list --path=api   # list API routes
php artisan optimize                # cache config, routes, and views for production
php artisan check:birthdays --notify  # run the birthday check now (also runs daily at 08:00)
npm run lint                        # ESLint for the Vue/JS frontend (correctness rules)
```

## Testing

The PHPUnit tests live in `tests/Feature` and cover login lockout, discount validation, the product-delete guard, order-number retry, SMS drivers, and notifications.

PHPUnit is listed in `composer.json`, but `composer.lock` was not regenerated with it. Run this once to refresh the lock file (it also drops the unused `laravel/breeze` package):

```bash
composer require --dev phpunit/phpunit:^11.5.3
php artisan test
```

Frontend and build checks:

```bash
npm run lint
npm run build
```

## Known gaps

- **SMS is not live until configured.** The default `log` driver sends nothing to customers. Set `SMS_DRIVER=kavenegar` and the API key to send real messages. The Kavenegar driver is written against the provider's documented `sms/send.json` endpoint but has not been tested against a live account.
- **Birthday email is not implemented.** Customers have no email field.
- **Tokens are in `localStorage`** (see *Authentication*). HttpOnly cookies are deferred.
- **Server validation messages are in English** (Laravel defaults) when a field-level message is not provided. Discount rules return Persian messages.
- **Large Vue screens are still large.** `NewOrder.vue` and `Orders.vue` keep their logic in one component. Their CSS now lives in sibling `.css` files, and shared formatting is in `resources/js/utils/format.js`. Splitting the logic into composables is a planned follow-up.

## Project layout

```
app/
  Events/            broadcast events (NewOrderCreated, NewCustomerRegistered, DiscountApplied, ...)
  Http/Controllers/  Admin/ and Reception/ controllers
  Http/Middleware/   AdminMiddleware, ReceptionMiddleware
  Listeners/         customer, order, and birthday listeners (SMS listeners are queued)
  Models/            Order, Customer, Product, Discount, User, Notification, ...
  Services/          DiscountService (discount rules), SmsService (SMS drivers), AdminNotifier (stored admin notifications)
database/
  migrations/        schema
  seeders/           CategorySeeder, AdminUserSeeder, DemoProductSeeder (optional), ...
resources/js/
  views/Admin/       admin pages (each large page has a sibling .css file)
  views/Reception/   reception pages
  components/Layout/ navbar (with the notification bell) and role layouts
  utils/format.js    shared number and date formatting
  realtime.js        Echo subscriptions per role (after login or session restore)
routes/
  api.php            API routes
  channels.php       broadcast channel authorization
  console.php        scheduled commands (daily birthday check)
tests/
  Feature/           PHPUnit feature tests
  Concerns/          shared test data helpers
```
