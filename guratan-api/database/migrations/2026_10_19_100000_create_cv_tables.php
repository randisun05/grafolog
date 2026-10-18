<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cv_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cv_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cv_group_id')->constrained('cv_groups')->cascadeOnDelete();
            $table->string('key', 60)->unique();
            $table->string('label', 150);
            $table->string('type', 20);
            $table->json('options')->nullable();
            $table->string('help', 255)->nullable();
            $table->boolean('required')->default(false);
            $table->string('visible_to', 10)->default('all');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cv_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cv_field_id')->constrained('cv_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'cv_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cv_values');
        Schema::dropIfExists('cv_fields');
        Schema::dropIfExists('cv_groups');
    }
};
