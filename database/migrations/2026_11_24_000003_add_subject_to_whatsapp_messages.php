<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Data sumber yang memicu pesan (surat, disposisi, instruksi, teguran) agar statusnya tampil di daftar.
            $table->nullableMorphs('subject');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropMorphs('subject');
        });
    }
};
