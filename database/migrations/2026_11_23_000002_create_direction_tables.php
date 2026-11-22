<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Arahan / instruksi. Disimpan pada jabatan pengirim agar riwayat ikut jabatan.
        Schema::create('directions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->foreignId('parent_id')->nullable()->constrained('directions')->nullOnDelete();
            $table->foreignId('from_position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10)->default('task'); // general | priority | task
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
            $table->index(['from_position_id', 'date']);
        });

        // Penerima arahan, beserta konfirmasi SIAP, laporan, dan verifikasi.
        Schema::create('direction_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('to_position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('report_note')->nullable();
            $table->string('report_file_path')->nullable();
            $table->string('report_file_name')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('verification_status', 10)->nullable(); // approved | revision
            $table->text('verification_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['to_position_id', 'confirmed_at']);
        });

        Schema::create('initiatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['position_id', 'status']);
        });

        // Item checklist untuk setiap penerima.
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_recipient_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('source', 12)->default('manual'); // manual | duty | initiative
            $table->foreignId('duty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('initiative_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('pending'); // pending | proses | tunda | selesai
            $table->text('note')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('location_zone', 10)->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('task_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('direction_recipient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_messages');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('initiatives');
        Schema::dropIfExists('direction_recipients');
        Schema::dropIfExists('directions');
    }
};
