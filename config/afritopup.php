<?php

/*
|--------------------------------------------------------------------------
| AfriTopup — brand, accounts and the services catalogue
|--------------------------------------------------------------------------
| One place for the names, logos and contact details the platform shows.
| The web layout, the sign-in page, e-mails and the mobile app
| (GET /api/v1/app/config) all read from here.
|
| White-label: every brand value below comes from .env, so another
| business can run its own copy of the platform under its own name.
*/

return [

    'name'          => env('AFRITOPUP_NAME', 'AfriTopup'),
    'company'       => env('AFRITOPUP_COMPANY', 'AfriTopup'),
    // Credit shown in the footer, on the sign-in page and in the app.
    'developer'     => env('AFRITOPUP_DEVELOPER', 'Qudroid Systems'),
    'developer_url' => env('AFRITOPUP_DEVELOPER_URL', 'https://qudroids.com'),
    'tagline'       => env('AFRITOPUP_TAGLINE', 'Your One-Stop Digital Payment and Recharge Platform.'),
    'website'       => env('AFRITOPUP_WEBSITE', 'https://afritopup.com'),
    'support_email' => env('AFRITOPUP_SUPPORT_EMAIL', 'support@afritopup.com'),
    'support_phone' => env('AFRITOPUP_SUPPORT_PHONE'),
    'address'       => env('AFRITOPUP_ADDRESS'),          // shown on receipts

    // Brand colour for the layout accents and the mobile app theme.
    // Website footer / app download buttons (leave blank to hide a social link).
    'app_links' => [
        'android' => env('AFRITOPUP_ANDROID_URL'),
        'ios'     => env('AFRITOPUP_IOS_URL'),
    ],
    'social_links' => [
        'facebook'  => env('AFRITOPUP_FACEBOOK_URL'),
        'x'         => env('AFRITOPUP_X_URL'),
        'instagram' => env('AFRITOPUP_INSTAGRAM_URL'),
        'linkedin'  => env('AFRITOPUP_LINKEDIN_URL'),
        'youtube'   => env('AFRITOPUP_YOUTUBE_URL'),
        'tiktok'    => env('AFRITOPUP_TIKTOK_URL'),
        'whatsapp'  => env('AFRITOPUP_WHATSAPP_URL'),
    ],

    'primary_color' => env('AFRITOPUP_PRIMARY_COLOR') ?: '#0B8457',
    'accent_color'  => env('AFRITOPUP_ACCENT_COLOR') ?: '#F5A623',

    // Paths under public/.
    'logo'       => env('AFRITOPUP_LOGO', 'images/brand/afritopup-logo-dark.svg'),
    'logo_light' => env('AFRITOPUP_LOGO_LIGHT', 'images/brand/afritopup-logo-light.svg'),
    'logo_small' => env('AFRITOPUP_LOGO_SMALL', 'images/brand/afritopup-mark.svg'),
    'favicon'    => env('AFRITOPUP_FAVICON', 'images/brand/afritopup-mark.svg'),

    // Customers create their own accounts (web and app). Staff are invited.
    // Customers confirm their e-mail (6-digit code or link) before they can buy.
    'require_email_verification' => (bool) env('AFRITOPUP_REQUIRE_EMAIL_VERIFICATION', true),

    'allow_registration' => (bool) env('AFRITOPUP_ALLOW_REGISTRATION', true),

    /*
    | Sign in with Google / Apple (web and app). A provider is offered once it
    | has at least one client ID. Tokens are accepted for any listed ID.
    |   google: the Web client ID (also the app's serverClientId), plus the
    |           Android and iOS client IDs from Google Cloud → Credentials.
    |   apple:  the app's bundle ID (com.afritopup.app) and, for the website,
    |           the Services ID; `web_client_id` is the Services ID.
    */
    'social' => [
        'google' => [
            'client_ids'    => array_filter(explode(',', (string) env('GOOGLE_CLIENT_IDS', env('GOOGLE_WEB_CLIENT_ID', '')))),
            'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
            'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID'),
        ],
        'apple' => [
            'client_ids'    => array_filter(explode(',', (string) env('APPLE_CLIENT_IDS', 'com.afritopup.app'))),
            'web_client_id' => env('APPLE_SERVICES_ID'),
        ],
    ],

    /*
    | The first administrator, created by `php artisan db:seed`.
    | Change the password after the first sign-in.
    */
    'super_admin' => [
        'name'     => env('AFRITOPUP_ADMIN_NAME', 'Super Administrator'),
        'email'    => env('AFRITOPUP_ADMIN_EMAIL', 'admin@afritopup.com'),
        'password' => env('AFRITOPUP_ADMIN_PASSWORD', 'ChangeMe@123'),
    ],

    // The role that bypasses every permission check.
    'super_admin_role' => 'Super Administrator',

    /*
    | What kind of account a user is. `staff` run the platform; everyone else
    | buys. Agents and resellers buy at a discount (their price tier) and
    | resellers may also use the reseller API.
    */
    'user_types' => [
        'customer' => 'Customer',
        'agent'    => 'Agent',
        'reseller' => 'Reseller',
        'staff'    => 'Staff',
    ],

    // The country and currency new accounts start in.
    'default_country'  => env('AFRITOPUP_DEFAULT_COUNTRY', 'NG'),
    'default_currency' => env('AFRITOPUP_DEFAULT_CURRENCY', 'NGN'),

    // Transaction references look like AFT-20261111-8F3K2Q9X.
    'reference_prefix' => env('AFRITOPUP_REFERENCE_PREFIX', 'AFT'),

    /*
    | Wallet funding: smallest and largest single top-up, and Paystack's fee
    | passed on to the customer (percent + flat, capped). Set the percent to
    | 0 to absorb the fee.
    */
    'funding' => [
        'min'         => (float) env('AFRITOPUP_FUND_MIN', 100),
        'max'         => (float) env('AFRITOPUP_FUND_MAX', 1000000),
        'fee_percent' => (float) env('AFRITOPUP_FUND_FEE_PERCENT', 1.5),
        'fee_flat'    => (float) env('AFRITOPUP_FUND_FEE_FLAT', 0),
        'fee_cap'     => (float) env('AFRITOPUP_FUND_FEE_CAP', 2000),

        // Bank transfer into the customer's own account number (Paystack dedicated account):
        // the fee is taken from the amount received (Paystack charges 1%, capped at ₦300).
        'transfer_fee_percent' => (float) env('AFRITOPUP_TRANSFER_FEE_PERCENT', 1),
        'transfer_fee_flat'    => (float) env('AFRITOPUP_TRANSFER_FEE_FLAT', 0),
        'transfer_fee_cap'     => (float) env('AFRITOPUP_TRANSFER_FEE_CAP', 300),
        'transfer_bank'        => env('AFRITOPUP_TRANSFER_BANK', 'wema-bank'),   // wema-bank | titan-paystack
    ],

    /*
    | A purchase still "processing" at the provider after this many minutes
    | is re-checked by `php artisan topup:requery` (scheduled every 5 minutes).
    */
    'requery_after_minutes' => (int) env('AFRITOPUP_REQUERY_AFTER', 2),

    // SMS: characters per page (plain GSM text) and the largest number of recipients per send.
    'sms' => [
        'page_length'    => 160,
        'max_recipients' => (int) env('AFRITOPUP_SMS_MAX_RECIPIENTS', 10000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service categories
    |--------------------------------------------------------------------------
    | The six things a customer can buy. Billers within each (MTN, DStv,
    | Ikeja Electric …) are rows in the `services` table, per country, and
    | are managed under Services & Pricing.
    |
    | `needs` lists what the purchase form asks for:
    |   recipient_label – the phone number / smartcard / meter / profile ID
    |   product         – a plan or bouquet must be chosen (data, cable, exam pins)
    |   amount          – the customer types the amount (airtime, electricity)
    |   verify          – the recipient is checked with the provider first
    */
    'categories' => [
        'airtime'     => ['name' => 'Airtime',            'icon' => 'ri-smartphone-line',      'color' => '#0B8457', 'recipient_label' => 'Phone number',      'product' => false, 'amount' => true,  'verify' => false],
        'data'        => ['name' => 'Internet Data',      'icon' => 'ri-wifi-line',            'color' => '#2563EB', 'recipient_label' => 'Phone number',      'product' => true,  'amount' => false, 'verify' => false],
        'cable'       => ['name' => 'Cable TV',           'icon' => 'ri-tv-2-line',            'color' => '#7C3AED', 'recipient_label' => 'Smartcard / IUC number', 'product' => true, 'amount' => false, 'verify' => true],
        'electricity' => ['name' => 'Electricity',        'icon' => 'ri-flashlight-line',      'color' => '#F59E0B', 'recipient_label' => 'Meter number',      'product' => false, 'amount' => true,  'verify' => true],
        'sms'         => ['name' => 'Bulk SMS',           'icon' => 'ri-message-3-line',       'color' => '#EC4899', 'recipient_label' => 'Recipients',        'product' => false, 'amount' => false, 'verify' => false],
        'education'   => ['name' => 'Education',          'icon' => 'ri-graduation-cap-line',  'color' => '#0EA5E9', 'recipient_label' => 'Phone number / Profile ID', 'product' => true, 'amount' => false, 'verify' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers (VTU and payment aggregators)
    |--------------------------------------------------------------------------
    | Each service names the provider that fulfils it. Keys are entered under
    | Settings → Providers (saved encrypted) or here from .env.
    |
    |   sandbox – built in, no network: every purchase succeeds unless the
    |             recipient ends in 0000 (fails) or 1111 (stays processing).
    |             For development and demos only.
    |   vtpass  – VTpass (airtime, data, cable, electricity, exam pins).
    |   termii  – Termii (bulk and business SMS).
    |   reloadly – Reloadly (airtime and data in Ghana, Kenya and 150+ countries).
    |   manual  – fulfilled by staff (e.g. school fees paid out to a school);
    |             stays processing until marked delivered.
    */
    'providers' => [
        'vtpass' => [
            'name'       => 'VTpass',
            'mode'       => env('VTPASS_MODE', 'sandbox'),          // sandbox | live
            'api_key'    => env('VTPASS_API_KEY'),
            'public_key' => env('VTPASS_PUBLIC_KEY'),
            'secret_key' => env('VTPASS_SECRET_KEY'),
        ],
        'termii' => [
            'name'      => 'Termii',
            'api_key'   => env('TERMII_API_KEY'),
            'base_url'  => env('TERMII_BASE_URL', 'https://api.ng.termii.com'),
            'sender_id' => env('TERMII_SENDER_ID', 'AfriTopup'),
            'channel'   => env('TERMII_CHANNEL', 'generic'),        // generic | dnd
        ],
        'reloadly' => [
            'name'          => 'Reloadly',
            'mode'          => env('RELOADLY_MODE', 'sandbox'),      // sandbox | live
            'client_id'     => env('RELOADLY_CLIENT_ID'),
            'client_secret' => env('RELOADLY_CLIENT_SECRET'),
        ],
        'sandbox' => ['name' => 'Sandbox (test)'],
        'manual'  => ['name' => 'Manual (staff)'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules (the app launcher on the dashboard and the sidebar)
    |--------------------------------------------------------------------------
    | Each module appears once its `route` exists and the user holds its
    | `permission`. Until then the launcher shows it as "Coming soon".
    */
    'modules' => [
        'buy'          => ['name' => 'Buy & Pay',           'icon' => 'ri-shopping-bag-3-line',   'route' => 'topup.buy',              'permission' => 'Buy services',        'phase' => 1, 'color' => '#0B8457', 'description' => 'Airtime, data, cable TV, electricity, bulk SMS and education payments.'],
        'wallet'       => ['name' => 'My Wallet',           'icon' => 'ri-wallet-3-line',         'route' => 'topup.wallet',           'permission' => 'Buy services',        'phase' => 1, 'color' => '#F5A623', 'description' => 'Fund your wallet, see your balance and statement.'],
        'support'      => ['name' => 'Support Chat',        'icon' => 'ri-customer-service-2-line','route' => 'admin.support.index',   'permission' => 'Manage support',      'phase' => 1, 'color' => '#0EA5E9', 'description' => 'Chat with customers: answer, assign and solve.'],
        'transactions' => ['name' => 'Transactions',        'icon' => 'ri-exchange-funds-line',   'route' => 'admin.transactions.index','permission' => 'View transactions',  'phase' => 1, 'color' => '#2563EB', 'description' => 'Every purchase on the platform: status, requery, refunds.'],
        'fundings'     => ['name' => 'Wallet Fundings',     'icon' => 'ri-bank-card-line',        'route' => 'admin.fundings.index',   'permission' => 'View wallets',        'phase' => 1, 'color' => '#10B981', 'description' => 'Card and transfer top-ups, manual credits and debits.'],
        'catalogue'    => ['name' => 'Services & Pricing',  'icon' => 'ri-price-tag-3-line',      'route' => 'admin.services.index',   'permission' => 'Manage services',     'phase' => 1, 'color' => '#7C3AED', 'description' => 'Billers, plans, providers, price tiers and countries.'],
        'resellers'    => ['name' => 'Resellers & API',     'icon' => 'ri-code-box-line',         'route' => 'topup.developer',        'permission' => 'Use reseller API',    'phase' => 2, 'color' => '#0F172A', 'description' => 'API keys, webhooks and documentation for resellers and businesses.'],
        'sender_ids'   => ['name' => 'SMS Sender IDs',      'icon' => 'ri-message-2-line',        'route' => 'admin.sender-ids.index', 'permission' => 'Manage services',     'phase' => 1, 'color' => '#EC4899', 'description' => 'Approve business SMS sender names.'],
    ],

];
