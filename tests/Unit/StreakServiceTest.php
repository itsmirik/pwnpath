<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\StreakService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    private StreakService $streaks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->streaks = new StreakService;
    }

    public function test_first_solve_starts_streak_at_one(): void
    {
        $user = User::factory()->create([
            'streak_count' => 0,
            'last_solve_date' => null,
            'streak_freeze_available' => 1,
        ]);

        $day = CarbonImmutable::parse('2026-07-20 12:00:00', StreakService::TIMEZONE);
        $this->streaks->recordSolve($user, $day);

        $user->refresh();
        $this->assertSame(1, $user->streak_count);
        $this->assertSame('2026-07-20', $user->last_solve_date?->toDateString());
    }

    public function test_consecutive_days_increment_streak(): void
    {
        $user = User::factory()->create([
            'streak_count' => 0,
            'last_solve_date' => null,
            'streak_freeze_available' => 1,
        ]);

        $this->streaks->recordSolve(
            $user,
            CarbonImmutable::parse('2026-07-20 09:00:00', StreakService::TIMEZONE),
        );
        $this->streaks->recordSolve(
            $user->fresh(),
            CarbonImmutable::parse('2026-07-21 18:00:00', StreakService::TIMEZONE),
        );
        $this->streaks->recordSolve(
            $user->fresh(),
            CarbonImmutable::parse('2026-07-22 01:00:00', StreakService::TIMEZONE),
        );

        $user->refresh();
        $this->assertSame(3, $user->streak_count);
        $this->assertSame('2026-07-22', $user->last_solve_date?->toDateString());
    }

    public function test_same_day_second_solve_does_not_double_count(): void
    {
        $user = User::factory()->create([
            'streak_count' => 0,
            'last_solve_date' => null,
        ]);

        $morning = CarbonImmutable::parse('2026-07-20 08:00:00', StreakService::TIMEZONE);
        $evening = CarbonImmutable::parse('2026-07-20 22:00:00', StreakService::TIMEZONE);

        $this->streaks->recordSolve($user, $morning);
        $this->streaks->recordSolve($user->fresh(), $evening);

        $user->refresh();
        $this->assertSame(1, $user->streak_count);
    }

    public function test_one_day_gap_consumes_freeze_and_keeps_streak(): void
    {
        $user = User::factory()->create([
            'streak_count' => 5,
            'last_solve_date' => '2026-07-18',
            'streak_freeze_available' => 1,
        ]);

        // Missed 2026-07-19, solved on 2026-07-20 → freeze burns, streak continues.
        $this->streaks->recordSolve(
            $user,
            CarbonImmutable::parse('2026-07-20 12:00:00', StreakService::TIMEZONE),
        );

        $user->refresh();
        $this->assertSame(6, $user->streak_count);
        $this->assertSame(0, $user->streak_freeze_available);
        $this->assertSame('2026-07-20', $user->last_solve_date?->toDateString());
    }

    public function test_gap_without_freeze_resets_streak(): void
    {
        $user = User::factory()->create([
            'streak_count' => 12,
            'last_solve_date' => '2026-07-15',
            'streak_freeze_available' => 0,
        ]);

        $this->streaks->recordSolve(
            $user,
            CarbonImmutable::parse('2026-07-20 12:00:00', StreakService::TIMEZONE),
        );

        $user->refresh();
        $this->assertSame(1, $user->streak_count);
        $this->assertSame(0, $user->streak_freeze_available);
    }

    public function test_two_day_gap_with_freeze_still_resets(): void
    {
        // Freeze only covers a single missed day. Two missed days → reset.
        $user = User::factory()->create([
            'streak_count' => 8,
            'last_solve_date' => '2026-07-17',
            'streak_freeze_available' => 1,
        ]);

        $this->streaks->recordSolve(
            $user,
            CarbonImmutable::parse('2026-07-20 12:00:00', StreakService::TIMEZONE),
        );

        $user->refresh();
        $this->assertSame(1, $user->streak_count);
        $this->assertSame(1, $user->streak_freeze_available);
    }

    public function test_day_by_day_week_builds_streak_of_seven(): void
    {
        $user = User::factory()->create([
            'streak_count' => 0,
            'last_solve_date' => null,
            'streak_freeze_available' => 1,
        ]);

        for ($i = 0; $i < 7; $i++) {
            $day = CarbonImmutable::parse('2026-07-01', StreakService::TIMEZONE)
                ->addDays($i)
                ->setTime(15, 0);

            $this->streaks->recordSolve($user->fresh(), $day);
        }

        $user->refresh();
        $this->assertSame(7, $user->streak_count);
        $this->assertSame('2026-07-07', $user->last_solve_date?->toDateString());
    }
}
