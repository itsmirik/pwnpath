<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaked_flags', function (Blueprint $table) {
            $table->id();
            $table->string('flag_value', 256);
            $table->foreignId('challenge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('distinct_submitters')->default(0);
            $table->boolean('admin_alerted')->default(false);
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->unique('flag_value');
            $table->index(['owner_user_id', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaked_flags');
    }
};
