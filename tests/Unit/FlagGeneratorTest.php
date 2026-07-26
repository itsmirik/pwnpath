<?php

namespace Tests\Unit;

use App\Models\Challenge;
use App\Models\User;
use App\Services\FlagGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_matches_hmac_spec(): void
    {
        config(['app.flag_secret' => 'unit-test-secret']);

        $user = User::factory()->create();
        $challenge = Challenge::factory()->create();
        $generator = new FlagGenerator;

        $expectedHash = hash_hmac('sha256', $user->id.':'.$challenge->id, 'unit-test-secret');
        $expected = 'HTP{'.substr($expectedHash, 0, 24).'}';

        $this->assertSame($expected, $generator->generate($user, $challenge));
        $this->assertTrue($generator->matches($user, $challenge, $expected));
        $this->assertFalse($generator->matches($user, $challenge, 'HTP{000000000000000000000000}'));
    }

    public function test_flags_differ_per_user_and_challenge(): void
    {
        config(['app.flag_secret' => 'unit-test-secret']);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $challengeA = Challenge::factory()->create();
        $challengeB = Challenge::factory()->create();
        $generator = new FlagGenerator;

        $a = $generator->generate($userA, $challengeA);
        $b = $generator->generate($userB, $challengeA);
        $c = $generator->generate($userA, $challengeB);

        $this->assertNotSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertMatchesRegularExpression('/^HTP\{[a-f0-9]{24}\}$/', $a);
    }

    public function test_static_flag_match(): void
    {
        config(['app.flag_secret' => 'unit-test-secret']);

        $user = User::factory()->create();
        $challenge = Challenge::factory()->staticFlag('HTP{static_value_here_xx}')->create();
        $generator = new FlagGenerator;

        $this->assertTrue($generator->matches($user, $challenge, 'HTP{static_value_here_xx}'));
        $this->assertFalse($generator->matches($user, $challenge, $generator->generate($user, $challenge)));
    }
}
