<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agents & referral earnings, and KYC:
 *  - wallets.type: "main" (spend) or "earnings" (referral bonuses and commission, moved to main on request)
 *  - referral_rewards: one row per bonus / commission paid, so nothing is paid twice and refunds claw back
 *  - account_upgrades: requests to become an agent or reseller
 *  - kyc_submissions + users.kyc_level: identity checks that raise purchase and balance limits
 */
return new class extends Migration
{
    public function up(): void
    {
        // Safe to re-run after a partial failure (MySQL can't roll back schema changes).
        if (!Schema::hasColumn('wallets', 'type')) {
            Schema::table('wallets', fn (Blueprint $t) => $t->string('type', 10)->default('main')->after('currency'));
        }
        // Add the new unique index BEFORE dropping the old one: on MySQL the old
        // index also serves the user_id foreign key, which needs an index starting with user_id.
        if (!Schema::hasIndex('wallets', 'wallets_user_id_currency_type_unique')) {
            Schema::table('wallets', fn (Blueprint $t) => $t->unique(['user_id', 'currency', 'type']));
        }
        if (Schema::hasIndex('wallets', 'wallets_user_id_currency_unique')) {
            Schema::table('wallets', fn (Blueprint $t) => $t->dropUnique(['user_id', 'currency']));
        }

        if (!Schema::hasColumn('users', 'kyc_level')) {
            Schema::table('users', fn (Blueprint $t) => $t->unsignedTinyInteger('kyc_level')->default(0)->after('paystack_customer_code'));
        }

        if (!Schema::hasTable('referral_rewards')) Schema::create('referral_rewards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();            // who earns
            $t->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();   // whose activity earned it
            $t->string('type', 20);                                                 // signup_bonus | welcome_bonus | commission
            $t->morphs('source');                                                   // WalletFunding / Transaction
            $t->decimal('amount', 18, 2);
            $t->string('currency', 3);
            $t->string('status', 10)->default('credited');                          // credited | reversed
            $t->string('description', 255);
            $t->timestamps();
            $t->unique(['user_id', 'type', 'source_type', 'source_id']);
        });

        if (!Schema::hasTable('account_upgrades')) Schema::create('account_upgrades', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('requested_type', 10);              // agent | reseller
            $t->string('business_name', 150)->nullable();
            $t->string('business_address', 255)->nullable();
            $t->string('monthly_volume', 40)->nullable();  // "₦500k – ₦2m"
            $t->text('reason')->nullable();
            $t->string('status', 10)->default('pending'); // pending | approved | rejected
            $t->string('note', 255)->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });

        if (!Schema::hasTable('kyc_submissions')) Schema::create('kyc_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('id_type', 20);                     // nin | bvn | passport | drivers_licence | voters_card
            $t->text('id_number');                         // encrypted
            $t->date('date_of_birth')->nullable();
            $t->string('document_path')->nullable();       // private disk
            $t->string('selfie_path')->nullable();
            $t->string('status', 10)->default('pending');  // pending | approved | rejected
            $t->unsignedTinyInteger('level_granted')->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_submissions');
        Schema::dropIfExists('account_upgrades');
        Schema::dropIfExists('referral_rewards');
        if (Schema::hasColumn('users', 'kyc_level')) Schema::table('users', fn (Blueprint $t) => $t->dropColumn('kyc_level'));
        if (!Schema::hasIndex('wallets', 'wallets_user_id_currency_unique')) {
            Schema::table('wallets', fn (Blueprint $t) => $t->unique(['user_id', 'currency']));
        }
        if (Schema::hasIndex('wallets', 'wallets_user_id_currency_type_unique')) {
            Schema::table('wallets', fn (Blueprint $t) => $t->dropUnique(['user_id', 'currency', 'type']));
        }
        if (Schema::hasColumn('wallets', 'type')) Schema::table('wallets', fn (Blueprint $t) => $t->dropColumn('type'));
    }
};
