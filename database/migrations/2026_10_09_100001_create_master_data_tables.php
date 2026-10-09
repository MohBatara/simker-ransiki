<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 50)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('institution_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->timestamps();
        });

        Schema::create('business_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('opd_id')->references('id')->on('opds')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['opd_id']);
        });
        Schema::dropIfExists('business_sectors');
        Schema::dropIfExists('institution_types');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('opds');
    }
};
