<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer ↔ support chat. One running conversation per customer (like a
 * messenger); staff close it when solved and a new message re-opens it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();      // the customer
            $t->string('status', 10)->default('open');                                // open | closed
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_message_at')->nullable();
            $t->string('last_message_preview', 160)->nullable();
            $t->boolean('last_from_staff')->default(false);
            $t->unsignedInteger('customer_unread')->default(0);   // staff messages the customer hasn't read
            $t->unsignedInteger('staff_unread')->default(0);      // customer messages staff haven't read
            $t->timestamp('staff_notified_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'last_message_at']);
        });

        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();      // sender (null = system)
            $t->boolean('from_staff')->default(false);
            $t->string('type', 10)->default('text');                                 // text | event
            $t->text('body')->nullable();
            $t->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete(); // "about this payment"
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name', 150)->nullable();
            $t->string('attachment_mime', 80)->nullable();
            $t->unsignedInteger('attachment_size')->nullable();
            $t->timestamp('read_at')->nullable();                                    // read by the other side
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
    }
};
