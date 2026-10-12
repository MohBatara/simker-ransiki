<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_types', function (Blueprint $table) {
            // JPEG cadangan; varian WebP di sampingnya (ImageVariants::HERO). Foto kartu di beranda, opsional.
            $table->string('photo_path')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('institution_types', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
