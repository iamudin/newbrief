<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekap evaluasi harian per jabatan (dikunci bersama snapshot kepatuhan) agar rekap
 * mingguan/bulanan dashboard Kepala Dinas tidak menghitung ulang data mentah.
 * Ditambah jumlah revisi laporan untuk nilai "Efektif".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_records', function (Blueprint $table) {
            $table->string('zone', 10)->nullable()->after('acted_at');
            $table->unsignedSmallInteger('needs_report')->default(0)->after('zone');
            $table->unsignedSmallInteger('reported')->default(0)->after('needs_report');
            $table->unsignedSmallInteger('reported_on_time')->default(0)->after('reported');
            $table->unsignedSmallInteger('tasks_total')->default(0)->after('reported_on_time');
            $table->unsignedSmallInteger('tasks_done')->default(0)->after('tasks_total');
            $table->json('issues')->nullable()->after('tasks_done');
            $table->timestamp('evaluated_at')->nullable()->after('issues');
        });

        Schema::table('direction_recipients', function (Blueprint $table) {
            $table->unsignedTinyInteger('revision_count')->default(0)->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_records', function (Blueprint $table) {
            $table->dropColumn(['zone', 'needs_report', 'reported', 'reported_on_time', 'tasks_total', 'tasks_done', 'issues', 'evaluated_at']);
        });

        Schema::table('direction_recipients', function (Blueprint $table) {
            $table->dropColumn('revision_count');
        });
    }
};
