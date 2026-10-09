<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->date('period')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('no_activity')->default(false);
            $table->text('activity_summary')->nullable();
            $table->unsignedInteger('activity_count')->nullable();
            $table->text('achievements')->nullable();
            $table->unsignedInteger('members_active')->nullable();
            $table->unsignedInteger('workforce_count')->nullable();
            $table->integer('members_change')->nullable();
            $table->unsignedBigInteger('income_sales')->nullable();
            $table->unsignedBigInteger('income_other')->nullable();
            $table->unsignedBigInteger('costs')->nullable();
            $table->bigInteger('profit')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'period']);
        });

        Schema::create('report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->decimal('production_qty', 12, 2)->nullable();
            $table->string('production_unit', 30)->nullable();
            $table->decimal('sales_qty', 12, 2)->nullable();
            $table->string('sales_unit', 30)->nullable();
            $table->unsignedBigInteger('revenue')->nullable();
            $table->timestamps();
        });

        Schema::create('report_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_report_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['kind', 'category']);
        });

        Schema::create('report_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_reviews');
        Schema::dropIfExists('report_issues');
        Schema::dropIfExists('report_items');
        Schema::dropIfExists('monthly_reports');
    }
};
