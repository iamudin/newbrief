<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tap SIAP harian (dipakai untuk kepatuhan staff, juga merekam lokasi).
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_zone', 10)->nullable();
            $table->timestamps();
            $table->unique(['date', 'user_id']);
        });

        Schema::create('location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('accuracy')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->string('zone', 10); // office | field | outside
            $table->string('context', 20); // siap | task | direction | report
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        // Penugasan dinas luar harian yang ditetapkan atasan.
        Schema::create('field_assignments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('set_by')->constrained('users')->cascadeOnDelete();
            $table->string('destination')->nullable();
            $table->timestamps();
            $table->unique(['date', 'user_id']);
        });

        // Snapshot kepatuhan harian per jabatan.
        Schema::create('compliance_records', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 10);
            $table->string('status', 10); // patuh | terlambat | tidak
            $table->string('deadline', 5);
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['date', 'position_id']);
        });

        Schema::create('reprimands', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_position_id')->constrained('positions')->cascadeOnDelete();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reprimands');
        Schema::dropIfExists('compliance_records');
        Schema::dropIfExists('field_assignments');
        Schema::dropIfExists('location_logs');
        Schema::dropIfExists('daily_checkins');
    }
};
