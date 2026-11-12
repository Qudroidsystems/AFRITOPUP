<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One-time 6-digit codes e-mailed to confirm an address or reset a password. Only a hash is stored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('email', 190);
            $t->string('purpose', 10);              // verify | reset
            $t->string('code_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['email', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_codes');
    }
};
