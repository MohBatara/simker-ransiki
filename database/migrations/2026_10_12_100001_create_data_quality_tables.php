<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 4: pasangan "bukan data ganda", pengisi laporan (entered_by), dan tindak lanjut masalah & kebutuhan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pasangan kelompok yang sudah diperiksa Admin dan dinyatakan bukan data ganda.
        // organization_id selalu id yang lebih kecil, agar satu pasangan hanya punya satu baris.
        Schema::create('duplicate_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('other_organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['organization_id', 'other_organization_id'], 'duplicate_dismissals_pair_unique');
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->foreignId('entered_by')->nullable()->after('no_activity')->constrained('users')->nullOnDelete();
        });

        Schema::table('report_issues', function (Blueprint $table) {
            $table->string('follow_up_status', 20)->default('new')->after('description');
            $table->foreignId('follow_up_opd_id')->nullable()->after('follow_up_status')->constrained('opds')->restrictOnDelete();
            $table->timestamp('follow_up_at')->nullable()->after('follow_up_opd_id');

            $table->index('follow_up_status');
        });

        Schema::create('issue_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opd_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->text('note');
            $table->date('acted_on');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_follow_ups');

        Schema::table('report_issues', function (Blueprint $table) {
            $table->dropForeign(['follow_up_opd_id']);
            $table->dropIndex(['follow_up_status']);
            $table->dropColumn(['follow_up_status', 'follow_up_opd_id', 'follow_up_at']);
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropForeign(['entered_by']);
            $table->dropColumn('entered_by');
        });

        Schema::dropIfExists('duplicate_dismissals');
    }
};
