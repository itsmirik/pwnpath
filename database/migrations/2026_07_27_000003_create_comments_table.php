<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Self-referencing parent for 1-level nesting; deleting a parent removes its replies.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            // Threaded listing: top-level then replies, oldest first, per challenge.
            $table->index(['challenge_id', 'parent_id', 'created_at']);
            // Rate-limit lookup: this user's recent comments.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
