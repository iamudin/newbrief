<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Surat masuk yang diinput Operator E-Arsip.
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('agenda_number', 30)->unique();
            $table->string('letter_number');
            $table->date('letter_date');
            $table->date('received_date');
            $table->string('sender');
            $table->text('subject');
            $table->string('nature', 15)->default('biasa'); // biasa | segera | sangat_segera | rahasia
            $table->string('file_path');
            $table->string('file_name');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('operator_position_id')->constrained('positions')->cascadeOnDelete();
            // Paraf atasan operator (Kasubag Umum), lalu diteruskan ke Kepala Dinas
            $table->foreignId('verifier_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->timestamp('paraf_at')->nullable();
            $table->foreignId('paraf_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('paraf_note')->nullable();
            $table->foreignId('kadis_position_id')->nullable()->constrained('positions')->nullOnDelete();
            // Disposisi Kepala Dinas
            $table->timestamp('disposed_at')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('expectations')->nullable();
            $table->text('disposition_note')->nullable();
            $table->timestamps();
            $table->index(['verifier_position_id', 'paraf_at']);
            $table->index(['kadis_position_id', 'disposed_at']);
        });

        // Disposisi berjenjang: Kadis → Eselon 3 → Eselon 4 → Staff. Melekat pada jabatan.
        Schema::create('letter_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('letter_dispositions')->cascadeOnDelete();
            $table->foreignId('from_position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('expectations')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['to_position_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_dispositions');
        Schema::dropIfExists('letters');
    }
};
