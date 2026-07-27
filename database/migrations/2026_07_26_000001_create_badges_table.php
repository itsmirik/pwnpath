<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();

            $table->string('name_ru', 64);
            $table->string('name_uz', 64);
            $table->string('name_en', 64);

            $table->text('description_ru');
            $table->text('description_uz');
            $table->text('description_en');

            $table->string('icon', 64); // emoji or asset slug rendered on the badge grid
            $table->json('criteria_json'); // programmatic award rule, see BadgeService
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
