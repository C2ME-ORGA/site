<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_mailboxes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('email');

            $table->boolean('is_shared')->default(false);

            $table->string('imap_host');
            $table->unsignedInteger('imap_port')->default(993);
            $table->string('imap_encryption', 16)->default('ssl');
            $table->string('imap_username');
            $table->text('imap_password');

            $table->string('smtp_host');
            $table->unsignedInteger('smtp_port')->default(465);
            $table->string('smtp_encryption', 16)->default('ssl');
            $table->string('smtp_username');
            $table->text('smtp_password');

            $table->string('signature', 2000)->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_uid')->nullable();
            $table->text('last_error')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('user_id');
            $table->index('email');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_mailboxes');
    }
};
