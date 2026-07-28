<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Challenge;
use App\Models\Solve;
use App\Models\User;
use App\Services\SuspiciousSolveDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSuspiciousSolveTest extends TestCase
{
    use RefreshDatabase;

    private function hardSolves(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $challenge = Challenge::factory()->create(['difficulty' => 'hard']);
            Solve::factory()->create([
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'created_at' => now(),
            ]);
        }
    }

    public function test_burst_of_hard_solves_is_flagged(): void
    {
        $user = User::factory()->create();
        $this->hardSolves($user, 6);

        $flagged = app(SuspiciousSolveDetector::class)->detect();

        $this->assertCount(1, $flagged);
        $this->assertSame($user->id, $flagged[0]['user_id']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_SECURITY_SUSPICIOUS_SOLVES,
            'entity_id' => $user->id,
            'actor_id' => null,
        ]);
    }

    public function test_normal_pace_is_not_flagged(): void
    {
        $user = User::factory()->create();
        $this->hardSolves($user, 5); // threshold is > 5

        $flagged = app(SuspiciousSolveDetector::class)->detect();

        $this->assertSame([], $flagged);
    }

    public function test_detection_is_idempotent_within_the_window(): void
    {
        $user = User::factory()->create();
        $this->hardSolves($user, 6);

        $detector = app(SuspiciousSolveDetector::class);
        $detector->detect();
        $detector->detect();

        $this->assertSame(
            1,
            AuditLog::query()
                ->where('action', AuditLog::ACTION_SECURITY_SUSPICIOUS_SOLVES)
                ->where('entity_id', $user->id)
                ->count(),
        );
    }

    public function test_old_solves_outside_the_window_are_ignored(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $challenge = Challenge::factory()->create(['difficulty' => 'hard']);
            Solve::factory()->create([
                'user_id' => $user->id,
                'challenge_id' => $challenge->id,
                'created_at' => now()->subHour(),
            ]);
        }

        $this->assertSame([], app(SuspiciousSolveDetector::class)->detect());
    }
}
