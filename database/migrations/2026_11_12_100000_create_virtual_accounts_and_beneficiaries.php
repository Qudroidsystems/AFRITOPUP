<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank-transfer funding: each customer's own account number (a Paystack
 * dedicated virtual account); transfers into it credit the wallet.
 * Beneficiaries: numbers, meters and smartcards people buy for often.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('paystack_customer_code', 40)->nullable()->after('apple_id');
        });

        Schema::create('virtual_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 20)->default('paystack');
            $t->string('currency', 3)->default('NGN');
            $t->string('bank_name', 80);
            $t->string('bank_slug', 40)->nullable();
            $t->string('account_number', 20)->index();
            $t->string('account_name', 150);
            $t->string('provider_reference', 60)->nullable();   // Paystack dedicated account id
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['provider', 'account_number']);
        });

        Schema::create('beneficiaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('category', 20);
            $t->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $t->string('recipient', 60);
            $t->string('label', 80)->nullable();       // "Mum", "Shop meter", or the verified customer name
            $t->unsignedInteger('uses')->default(0);
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'category', 'recipient']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
        Schema::dropIfExists('virtual_accounts');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('paystack_customer_code'));
    }
};
