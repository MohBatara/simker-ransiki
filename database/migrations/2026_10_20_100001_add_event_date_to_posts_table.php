<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tanggal pelaksanaan untuk post Kegiatan (Agenda di halaman Berita); kosong = memakai tanggal terbit. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->date('event_date')->nullable()->after('published_at');
            $table->index(['category', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['category', 'event_date']);
            $table->dropColumn('event_date');
        });
    }
};
