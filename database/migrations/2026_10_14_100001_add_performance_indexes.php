<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk query yang sering dipakai daftar, dasbor, antrean, dan statistik. Kolom foreign key
 * (organization_id, monthly_report_id, dst.) sudah ber-index sendiri dari constraint-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kelompok wajib lapor: status + reporting_starts_on <= periode (Organization::obligatedFor).
        Schema::table('organizations', function (Blueprint $table) {
            $table->index(['status', 'reporting_starts_on']);
        });

        // Antrean verifikasi dan daftar laporan: status lalu urut periode.
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->index(['status', 'period']);
        });

        // Pengajuan terakhir, "diajukan ulang", dan pemisahan tugas mencari aksi per laporan.
        Schema::table('report_reviews', function (Blueprint $table) {
            $table->index(['monthly_report_id', 'action', 'created_at']);
        });

        // Statistik legalitas: EXISTS izin per kelompok dengan status tertentu.
        Schema::table('licenses', function (Blueprint $table) {
            $table->index(['organization_id', 'status']);
        });

        // Angka lonceng di setiap halaman area login: notifikasi belum dibaca milik satu akun.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', fn (Blueprint $table) => $table->dropIndex(['notifiable_type', 'notifiable_id', 'read_at']));
        // MySQL melepas index bawaan foreign key saat index gabungan di atas dibuat; pasang lagi sebelum dihapus.
        Schema::table('licenses', function (Blueprint $table) {
            $table->index('organization_id');
            $table->dropIndex(['organization_id', 'status']);
        });
        Schema::table('report_reviews', function (Blueprint $table) {
            $table->index('monthly_report_id');
            $table->dropIndex(['monthly_report_id', 'action', 'created_at']);
        });
        Schema::table('monthly_reports', fn (Blueprint $table) => $table->dropIndex(['status', 'period']));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropIndex(['status', 'reporting_starts_on']));
    }
};
