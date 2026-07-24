<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('filename', 128);
            $table->string('storage_path', 255);
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type', 96);
            $table->timestamp('created_at')->nullable();

            $table->index('challenge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_files');
    }
};
