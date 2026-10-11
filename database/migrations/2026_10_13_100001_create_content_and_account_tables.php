<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 5: Berita & Kegiatan, riwayat perubahan data kelompok, verifikasi profil, dan undangan akun lewat email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 20);
            $table->string('summary', 300)->nullable();
            $table->longText('body');
            $table->string('cover_photo_path')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('organization_post', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->primary(['post_id', 'organization_id']);
            $table->index('organization_id');
        });

        // Riwayat perubahan (tidak pernah diubah). organization_id disalin agar riwayat satu kelompok
        // (profil, produk, izin) bisa diambil dengan satu query. Nilai disimpan sebagai teks siap tampil.
        Schema::create('change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('loggable_type', 40);
            $table->unsignedBigInteger('loggable_id');
            $table->string('subject')->nullable();
            $table->string('action', 20);
            $table->string('field', 60)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organization_id', 'id']);
            $table->index(['loggable_type', 'loggable_id']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('profile_status', 20)->default('unverified')->after('show_contact_publicly');
            $table->timestamp('profile_verified_at')->nullable()->after('profile_status');
            $table->foreignId('profile_verified_by')->nullable()->after('profile_verified_at')->constrained('users')->nullOnDelete();
            $table->json('profile_changed_fields')->nullable()->after('profile_verified_by');
            $table->timestamp('profile_changed_at')->nullable()->after('profile_changed_fields');

            $table->index('profile_status');
        });

        Schema::table('users', function (Blueprint $table) {
            // Hash SHA-256 token undangan; dikosongkan setelah dipakai sehingga tautan hanya berlaku sekali.
            $table->string('invitation_token', 64)->nullable()->after('must_change_password');
            $table->timestamp('invited_at')->nullable()->after('invitation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invitation_token', 'invited_at']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropForeign(['profile_verified_by']);
            $table->dropIndex(['profile_status']);
            $table->dropColumn(['profile_status', 'profile_verified_at', 'profile_verified_by', 'profile_changed_fields', 'profile_changed_at']);
        });

        Schema::dropIfExists('change_logs');
        Schema::dropIfExists('organization_post');
        Schema::dropIfExists('posts');
    }
};
