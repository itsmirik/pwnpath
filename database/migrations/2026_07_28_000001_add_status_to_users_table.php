<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft-ban support (plan §11): suspended users are login-blocked but their
     * solves/writeups are preserved (no cascade delete).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('role');
            $table->string('ban_reason', 255)->nullable()->after('status');
            $table->timestamp('banned_at')->nullable()->after('ban_reason');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'ban_reason', 'banned_at']);
        });
    }
};
