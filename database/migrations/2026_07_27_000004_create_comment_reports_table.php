<?php

use App\Models\CommentReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 128);
            $table->string('status', 12)->default(CommentReport::STATUS_OPEN);
            $table->timestamp('created_at')->useCurrent();

            // One report per user per comment.
            $table->unique(['comment_id', 'reporter_id']);
            // Mod queue: open reports first.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
    }
};
