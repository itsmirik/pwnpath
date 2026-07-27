<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserRankTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function xpTierProvider(): array
    {
        return [
            'zero is bronze' => [0, 'bronze'],
            'bronze ceiling' => [499, 'bronze'],
            'silver floor' => [500, 'silver'],
            'silver ceiling' => [1999, 'silver'],
            'gold floor' => [2000, 'gold'],
            'gold ceiling' => [4999, 'gold'],
            'platinum floor' => [5000, 'platinum'],
            'platinum ceiling' => [9999, 'platinum'],
            'diamond floor' => [10000, 'diamond'],
            'diamond stays diamond' => [999999, 'diamond'],
        ];
    }

    #[DataProvider('xpTierProvider')]
    public function test_rank_for_xp_maps_thresholds(int $xp, string $tier): void
    {
        $this->assertSame($tier, User::rankForXp($xp));
    }

    public function test_rank_accessor_matches_static_helper(): void
    {
        $user = new User;
        $user->xp_total = 2500;

        $this->assertSame('gold', $user->rank);
    }

    public function test_rank_progress_reports_next_tier_and_fraction(): void
    {
        $user = new User;
        $user->xp_total = 1250; // halfway between silver(500) and gold(2000)

        $progress = $user->rankProgress();

        $this->assertSame('silver', $progress['tier']);
        $this->assertSame(500, $progress['floor']);
        $this->assertSame(2000, $progress['next']);
        $this->assertSame(0.5, $progress['progress']);
    }

    public function test_rank_progress_caps_at_top_tier(): void
    {
        $user = new User;
        $user->xp_total = 25000;

        $progress = $user->rankProgress();

        $this->assertSame('diamond', $progress['tier']);
        $this->assertNull($progress['next']);
        $this->assertSame(1.0, $progress['progress']);
    }
}
