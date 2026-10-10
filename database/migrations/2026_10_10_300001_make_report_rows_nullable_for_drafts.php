<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draf laporan disimpan otomatis saat baris belum lengkap (mis. jumlah sudah diisi, nama produk belum).
 * Kelengkapan baris diperiksa saat laporan diajukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->change();
        });

        Schema::table('report_issues', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_items', function (Blueprint $table) {
            $table->string('product_name')->nullable(false)->change();
        });

        Schema::table('report_issues', function (Blueprint $table) {
            $table->string('category', 30)->nullable(false)->change();
        });
    }
};
