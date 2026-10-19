<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('email_mailbox_id')
                ->constrained('email_mailboxes')
                ->cascadeOnDelete();

            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();

            $table->string('folder')->default('INBOX');
            $table->string('uid')->nullable();

            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to_recipients')->nullable();
            $table->json('cc_recipients')->nullable();

            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->boolean('is_read')->default(false);
            $table->boolean('is_sent')->default(false);

            $table->foreignId('crm_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->foreignId('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('crm_opportunity_id')->nullable()->constrained('crm_opportunities')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();

            $table->json('raw_headers')->nullable();

            $table->timestamps();

            $table->unique(['email_mailbox_id', 'message_id']);
            $table->index('from_email');
            $table->index('received_at');
            $table->index('crm_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
