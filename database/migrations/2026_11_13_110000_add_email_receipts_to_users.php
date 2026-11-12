<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E-mail receipts preference. null = default (on for customers and agents, off for resellers, who get webhooks). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'email_receipts')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('email_receipts')->nullable()->after('kyc_level'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'email_receipts')) Schema::table('users', fn (Blueprint $t) => $t->dropColumn('email_receipts'));
    }
};
