<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('institution_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('village_id')->constrained()->restrictOnDelete();
            $table->text('address')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->string('leader_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('members_count')->nullable();
            $table->text('description')->nullable();
            $table->text('production_capacity')->nullable();
            $table->text('markets')->nullable();
            $table->unsignedInteger('workforce_count')->nullable();
            $table->text('assets')->nullable();
            $table->text('facilities')->nullable();
            $table->text('equipment')->nullable();
            $table->string('legal_status', 20)->default('unknown');
            $table->string('legal_number')->nullable();
            $table->text('legal_notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('reporting_starts_on');
            $table->boolean('is_public')->default(false);
            $table->boolean('show_contact_publicly')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_public']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->restrictOnDelete();
        });

        Schema::create('organization_sector', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_sector_id')->constrained()->restrictOnDelete();
            $table->primary(['organization_id', 'business_sector_id']);
            $table->index('business_sector_id');
        });

        Schema::create('organization_opd', function (Blueprint $table) {
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opd_id')->constrained()->restrictOnDelete();
            $table->primary(['organization_id', 'opd_id']);
            $table->index('opd_id');
        });

        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('number')->nullable();
            $table->string('status', 20)->default('none');
            $table->date('submitted_on')->nullable();
            $table->date('issued_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 30)->nullable();
            $table->string('capacity')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(false);
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->index(['is_public', 'is_featured']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('visibility', 10)->default('private');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('products');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('organization_opd');
        Schema::dropIfExists('organization_sector');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });
        Schema::dropIfExists('organizations');
    }
};
