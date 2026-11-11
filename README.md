# AfriTopup

**Your One-Stop Digital Payment and Recharge Platform.**

Customers fund a wallet once, then buy airtime, data, cable TV, electricity,
bulk SMS and education payments (exam PINs, school fees) on the web, in the
mobile app (`StudioProjects/AFRITOPUP-app`, Flutter) or through the reseller API.

Built on the Qudroid portal foundation: the master layout and CB UI kit,
authentication, users, roles and permissions, profiles, notifications, the
activity log and the mobile API (`/api/v1`).

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| UI | Blade + Velzon theme (`public/theme`), CB UI kit (`public/css/cb-ui.css`, `x-cb.*` components) |
| Database | MySQL 8 (SQLite works for tests) |
| Permissions | spatie/laravel-permission |
| APIs | Sanctum tokens at `/api/v1` (mobile app); API keys at `/api/reseller/v1` (resellers) |
| Providers | VTpass (airtime, data, cable, electricity, exam PINs), Termii (SMS), Paystack (wallet funding) |

## First run (XAMPP)

```bash
cp .env.example .env          # set DB_DATABASE=afritopup and the AFRITOPUP_ADMIN_* values
php artisan key:generate
php artisan migrate --seed    # roles, permissions, admin, countries, Nigerian catalogue
php artisan storage:link
```

Open `http://localhost/AFRITOPUP/public` and sign in as `AFRITOPUP_ADMIN_EMAIL`
(you must choose a new password). Every service starts on the **sandbox**
provider, so you can buy end to end without real money:

| Recipient ends in | Sandbox result |
|---|---|
| `0000` | fails (wallet refunded) |
| `1111` | stays *processing* until `php artisan topup:requery` |
| anything else | delivered |

Cron (production): `* * * * * cd /path/to/afritopup && php artisan schedule:run`.
This runs the queue (reseller webhooks, push) and re-checks stuck purchases every five minutes.

## Going live

1. **Settings → VTU & SMS providers**: VTpass live keys (mode *Live*), Termii API key. Fund both provider wallets.
2. **Services & Pricing**: switch each service's provider from *Sandbox* to *VTpass* (Bulk SMS → *Termii*).
   On data, cable and exam services press **Load plans from VTpass** — real plans replace the sample ones. Set your selling prices.
3. **Settings → Payment gateways**: Paystack live keys; webhook `https://<domain>/webhook/paystack`.
4. **Price tiers**: set agent and reseller discounts per service.

## How it fits together

```
app/
  Models/                       Country, Wallet, WalletEntry, Service, Product, PriceTier,
                                ServiceTierDiscount, Transaction, WalletFunding, ApiKey,
                                SmsSenderId, School, User
  Services/Topup/
    TopupService.php            purchase → debit wallet → provider → settle (auto-refund on failure)
    WalletService.php           the only place balances change (row lock + statement line)
    PricingService.php          price, tier discount, fee
    FundingService.php          Paystack top-ups (verified, idempotent), manual credits
    Providers/                  Provider interface; Sandbox, Vtpass, Termii, Manual; ProviderManager
  Http/Controllers/
    Topup/                      buying, wallet, receipts, developer (API keys), Paystack webhook
    Admin/                      transactions, wallets, services & pricing, sender IDs, schools
    Api/V1/Topup/               mobile app endpoints
    Api/Reseller/               reseller API
routes/
  web.php                       foundation (auth, users, roles, profile, notifications)
  modules/topup.php, admin.php, settings.php
  api_v1.php + api_v1/topup.php mobile app
  reseller.php                  /api/reseller/v1
config/afritopup.php            brand (white-label via .env), categories, providers, funding fees
```

**Money** is stored as `decimal(18,2)` and calculated in minor units
(`App\Support\Money`), never floats. Every wallet change is a `wallet_entries`
row written in the same database transaction as the balance update.

**Adding a provider** (e.g. for Ghana or Kenya): implement
`App\Services\Topup\Providers\Provider`, add it to `ProviderManager::DRIVERS`,
add the country's services under Services & Pricing and open the country.

## Roles

| Role | Can |
|---|---|
| Super Administrator | everything |
| Administrator | transactions, wallets, catalogue, users |
| Support Officer | view and fix transactions (requery, refund, deliver) |
| Finance Officer | wallets, manual credits/debits |
| Customer / Agent | buy (agents at their tier's prices) |
| Reseller | buy, plus API keys |

## Tests

```bash
php artisan test
```

`tests/Feature/TopupTest.php` covers purchases, refunds, requery, tier
discounts, SMS pricing, school fees, Paystack funding and webhooks, the mobile
API, the reseller API and the VTpass driver.
