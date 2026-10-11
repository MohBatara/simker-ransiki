<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            // JPEG cadangan 1920 px; varian WebP di sampingnya (ImageVariants::HERO). File asli tidak disimpan.
            $table->string('photo_path');
            $table->string('caption', 100)->nullable();
            $table->string('alt_text', 200);
            $table->string('focus', 10)->default('center');
            $table->unsignedTinyInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
