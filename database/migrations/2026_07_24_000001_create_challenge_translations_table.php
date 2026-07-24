<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->string('title', 128);
            $table->text('description');
            $table->text('hint_1')->nullable();
            $table->text('hint_2')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_translations');
    }
};
