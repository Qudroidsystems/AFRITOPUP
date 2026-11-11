<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AfriTopup: countries, wallets and their ledger, the services catalogue
 * (billers and their plans), price tiers, purchases (transactions), wallet
 * fundings, reseller API keys, SMS sender IDs and partner schools.
 *
 * Money is stored as decimal(18,2) and calculated in minor units
 * (App\Support\Money) — never in floats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $t) {
            $t->id();
            $t->string('code', 2)->unique();             // ISO 3166-1 alpha-2: NG, GH, KE
            $t->string('name', 80);
            $t->string('currency', 3);                    // NGN, GHS, KES
            $t->string('dial_code', 6);                   // 234
            $t->string('phone_pattern', 120)->nullable(); // regex for a local mobile number
            $t->string('flag', 8)->nullable();
            $t->boolean('is_active')->default(false);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        // Discount levels: Customer, Agent, Reseller … Each user may have one; none = the default tier.
        Schema::create('price_tiers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name', 60);
            $t->string('description', 255)->nullable();
            $t->boolean('is_default')->default(false);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('country_code', 2)->nullable()->after('user_type');
            $t->foreignId('price_tier_id')->nullable()->after('country_code')->constrained('price_tiers')->nullOnDelete();
            $t->string('transaction_pin')->nullable()->after('password');   // hashed 4-digit PIN for purchases
            $t->foreignId('referred_by')->nullable()->after('transaction_pin')->constrained('users')->nullOnDelete();
            $t->string('referral_code', 20)->nullable()->unique()->after('referred_by');
            $t->string('business_name', 150)->nullable()->after('job_title');
        });

        Schema::create('wallets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('currency', 3);
            $t->decimal('balance', 18, 2)->default(0);
            $t->boolean('is_locked')->default(false);    // frozen by an administrator
            $t->timestamps();
            $t->unique(['user_id', 'currency']);
        });

        // The wallet's statement. Every change to a balance is one row here, written in the same database transaction.
        Schema::create('wallet_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $t->string('type', 6);                        // credit | debit
            $t->decimal('amount', 18, 2);
            $t->decimal('balance_before', 18, 2);
            $t->decimal('balance_after', 18, 2);
            $t->string('reference', 60)->index();
            $t->string('description', 255);
            $t->nullableMorphs('source');                 // Transaction, WalletFunding …
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['wallet_id', 'id']);
        });

        // A biller: MTN airtime, MTN data, DStv, Ikeja Electric, WAEC, Bulk SMS, a school …
        Schema::create('services', function (Blueprint $t) {
            $t->id();
            $t->string('category', 20)->index();          // airtime | data | cable | electricity | sms | education
            $t->string('country_code', 2)->index();
            $t->string('code', 60);                       // our code: mtn, mtn-data, dstv, ikeja-electric
            $t->string('name', 100);
            $t->string('short_name', 40)->nullable();     // "MTN"
            $t->string('logo')->nullable();               // path under public/ or a URL
            $t->string('color', 9)->nullable();
            $t->string('provider', 30)->default('sandbox');    // vtpass | termii | sandbox | manual
            $t->string('provider_code', 80)->nullable();       // the provider's id, e.g. VTpass serviceID
            $t->decimal('min_amount', 18, 2)->nullable();
            $t->decimal('max_amount', 18, 2)->nullable();
            $t->decimal('fee', 18, 2)->default(0);        // flat convenience fee added to each purchase
            $t->json('options')->nullable();              // e.g. electricity: {"meter_types": ["prepaid","postpaid"]}
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
            $t->unique(['country_code', 'code']);
        });

        // A plan or variation: 1GB 30 days, DStv Compact, WAEC result checker PIN …
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->string('code', 80);                       // provider variation code
            $t->string('name', 150);
            $t->decimal('cost', 18, 2);                   // what the provider charges us (face value)
            $t->decimal('price', 18, 2);                  // what the default tier pays
            $t->string('validity', 40)->nullable();       // "30 days"
            $t->json('meta')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('position')->default(0);
            $t->timestamps();
            $t->unique(['service_id', 'code']);
        });

        // Discount for a tier on a service: percent off the price (or face value), or a flat amount off.
        Schema::create('service_tier_discounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('price_tier_id')->constrained()->cascadeOnDelete();
            $t->string('type', 8)->default('percent');    // percent | flat
            $t->decimal('value', 10, 2)->default(0);
            $t->timestamps();
            $t->unique(['service_id', 'price_tier_id']);
        });

        Schema::create('api_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('prefix', 16)->unique();           // shown, so a key can be recognised: afk_live_3F9A…
            $t->string('key_hash', 64)->unique();         // sha256 of the full key; the key itself is shown once
            $t->string('webhook_url')->nullable();
            $t->string('webhook_secret', 64)->nullable();
            $t->json('allowed_ips')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        // A purchase: one airtime top-up, one data plan, one bill, one SMS send, one batch of exam PINs.
        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('wallet_id')->constrained();
            $t->string('category', 20)->index();
            $t->foreignId('service_id')->constrained();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('country_code', 2);
            $t->string('recipient', 255);                 // phone, smartcard, meter, profile ID or "1,250 recipients"
            $t->string('customer_name', 150)->nullable(); // from verification (cable, electricity)
            $t->string('phone', 30)->nullable();          // the buyer's contact number for the provider
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('amount', 18, 2);                 // face value delivered
            $t->decimal('discount', 18, 2)->default(0);
            $t->decimal('fee', 18, 2)->default(0);
            $t->decimal('total', 18, 2);                  // charged to the wallet = amount - discount + fee
            $t->decimal('cost', 18, 2)->nullable();       // what the provider charged (for profit reports)
            $t->string('currency', 3);
            $t->string('status', 12)->default('pending')->index();   // pending | processing | successful | failed | refunded
            $t->string('provider', 30);
            $t->string('provider_reference', 100)->nullable()->index();
            $t->json('provider_response')->nullable();
            $t->text('token')->nullable();                // electricity token, exam PINs
            $t->string('message', 255)->nullable();       // last status message shown to the customer
            $t->string('channel', 10)->default('web');    // web | app | api
            $t->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $t->string('client_reference', 80)->nullable(); // the reseller's own id (idempotency)
            $t->json('meta')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('refunded_at')->nullable();
            $t->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['user_id', 'id']);
            $t->index(['status', 'created_at']);
            $t->unique(['user_id', 'client_reference']);
        });

        // Money into a wallet: card / bank transfer (Paystack, OPay) or a manual credit by staff.
        Schema::create('wallet_fundings', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 40)->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('wallet_id')->constrained();
            $t->string('gateway', 20);                    // paystack | opay | manual
            $t->decimal('amount', 18, 2);                 // credited to the wallet
            $t->decimal('fee', 18, 2)->default(0);        // gateway fee paid by the customer
            $t->decimal('charged', 18, 2);                // amount + fee
            $t->string('currency', 3);
            $t->string('status', 12)->default('pending')->index();   // pending | successful | failed | abandoned
            $t->string('gateway_reference', 100)->nullable();
            $t->json('gateway_response')->nullable();
            $t->string('channel', 10)->default('web');
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        // Business SMS: the sender name an account sends from must be approved first (networks require it).
        Schema::create('sms_sender_ids', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('sender_id', 11);
            $t->string('purpose', 255);
            $t->string('status', 10)->default('pending');  // pending | approved | rejected
            $t->string('note', 255)->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'sender_id']);
        });

        // Schools that receive fees through AfriTopup (settled by staff).
        Schema::create('schools', function (Blueprint $t) {
            $t->id();
            $t->string('country_code', 2)->index();
            $t->string('name', 150);
            $t->string('code', 30)->unique();
            $t->string('city', 80)->nullable();
            $t->string('contact_email')->nullable();
            $t->string('contact_phone', 30)->nullable();
            $t->string('bank_name', 80)->nullable();
            $t->string('account_number', 20)->nullable();
            $t->string('account_name', 150)->nullable();
            $t->json('fee_items')->nullable();            // [{"name": "Tuition", "amount": 150000}, …] — optional fixed items
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
        Schema::dropIfExists('sms_sender_ids');
        Schema::dropIfExists('wallet_fundings');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('service_tier_discounts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('services');
        Schema::dropIfExists('wallet_entries');
        Schema::dropIfExists('wallets');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('price_tier_id');
            $t->dropConstrainedForeignId('referred_by');
            $t->dropColumn(['country_code', 'transaction_pin', 'referral_code', 'business_name']);
        });
        Schema::dropIfExists('price_tiers');
        Schema::dropIfExists('countries');
    }
};
