<?php

use App\Models\Challenge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('category', 16);
            $table->string('difficulty', 8);
            $table->unsignedInteger('points');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default(Challenge::STATUS_DRAFT);
            $table->string('flag_type', 8)->default(Challenge::FLAG_DYNAMIC);
            $table->string('static_flag', 128)->nullable();
            $table->string('flag_format', 128)->default('HTP\{[a-f0-9]{24}\}');
            $table->unsignedInteger('solve_count')->default(0);
            $table->timestamps();
            $table->timestamp('published_at')->nullable();

            $table->index(['status', 'published_at']);
            $table->index(['category', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
