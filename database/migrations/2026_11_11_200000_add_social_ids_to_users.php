<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sign in with Google / Apple: the provider's account ID ("sub"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('google_id', 64)->nullable()->unique()->after('referral_code');
            $t->string('apple_id', 64)->nullable()->unique()->after('google_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['google_id']);
            $t->dropUnique(['apple_id']);
            $t->dropColumn(['google_id', 'apple_id']);
        });
    }
};
