<?php

use App\Models\Writeup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writeups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('challenge_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 2);
            $table->longText('content');
            $table->string('status', 12)->default(Writeup::STATUS_PENDING);
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_note')->nullable();
            $table->unsignedInteger('upvote_count')->default(0);
            $table->timestamps();
            $table->timestamp('moderated_at')->nullable();

            $table->unique(['user_id', 'challenge_id']);
            // Public list: approved writeups for a challenge, ranked by votes.
            $table->index(['challenge_id', 'status', 'upvote_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writeups');
    }
};
