<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Keterangan di bawah foto sampul dan teks alternatifnya (wajib di form bila berita berfoto).
            $table->string('photo_caption', 150)->nullable()->after('cover_photo_path');
            $table->string('photo_alt', 150)->nullable()->after('photo_caption');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['photo_caption', 'photo_alt']);
        });
    }
};
