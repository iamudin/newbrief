<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log pengiriman WhatsApp untuk dipantau super admin.
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 30);
            $table->text('text');
            $table->string('kind', 30)->default('info');
            $table->string('status', 10)->default('queued'); // queued | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
