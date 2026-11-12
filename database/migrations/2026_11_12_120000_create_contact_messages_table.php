<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages sent from the website's contact page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('email', 190);
            $t->string('phone', 30)->nullable();
            $t->string('topic', 30);
            $t->text('message');
            $t->string('status', 10)->default('new');   // new | replied | closed
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
