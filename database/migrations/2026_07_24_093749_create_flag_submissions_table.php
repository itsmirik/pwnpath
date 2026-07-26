<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flag_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('submitted_flag', 256);
            $table->boolean('is_correct')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'challenge_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['submitted_flag', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_submissions');
    }
};
