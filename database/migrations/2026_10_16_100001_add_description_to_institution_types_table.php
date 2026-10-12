<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_types', function (Blueprint $table) {
            // Satu kalimat untuk kartu "Jenis kelembagaan" di beranda.
            $table->string('description', 200)->nullable()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('institution_types', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
