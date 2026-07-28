<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\User;
use App\Models\Writeup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        return User::factory()->moderator()->create();
    }

    public function test_moderator_approves_a_writeup(): void
    {
        $moderator = $this->moderator();
        $writeup = Writeup::factory()->create();

        $this->actingAs($moderator)
            ->post(route('admin.writeups.approve', $writeup->id), ['note' => 'nice'])
            ->assertRedirect();

        $fresh = $writeup->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($moderator->id, $fresh->moderator_id);
        $this->assertNotNull($fresh->moderated_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_WRITEUP_APPROVE,
            'actor_id' => $moderator->id,
            'entity_id' => $writeup->id,
        ]);
    }

    public function test_rejection_requires_a_note(): void
    {
        $writeup = Writeup::factory()->create();

        $this->actingAs($this->moderator())
            ->post(route('admin.writeups.reject', $writeup->id), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame('pending', $writeup->fresh()->status);
    }

    public function test_moderator_rejects_with_a_note(): void
    {
        $writeup = Writeup::factory()->create();

        $this->actingAs($this->moderator())
            ->post(route('admin.writeups.reject', $writeup->id), ['note' => 'off-topic'])
            ->assertRedirect();

        $fresh = $writeup->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('off-topic', $fresh->moderation_note);
    }

    public function test_hiding_a_comment_resolves_its_open_reports(): void
    {
        $moderator = $this->moderator();
        $comment = Comment::factory()->create();
        $report = CommentReport::factory()->create(['comment_id' => $comment->id]);

        $this->actingAs($moderator)
            ->post(route('admin.comments.hide', $comment->id))
            ->assertRedirect();

        $this->assertTrue($comment->fresh()->is_hidden);
        $this->assertSame(CommentReport::STATUS_RESOLVED, $report->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_COMMENT_HIDE,
            'actor_id' => $moderator->id,
            'entity_id' => $comment->id,
        ]);
    }

    public function test_dismissing_a_report_leaves_the_comment(): void
    {
        $moderator = $this->moderator();
        $comment = Comment::factory()->create();
        $report = CommentReport::factory()->create(['comment_id' => $comment->id]);

        $this->actingAs($moderator)
            ->post(route('admin.reports.dismiss', $report->id))
            ->assertRedirect();

        $this->assertSame(CommentReport::STATUS_RESOLVED, $report->fresh()->status);
        $this->assertFalse($comment->fresh()->is_hidden);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_REPORT_DISMISS,
            'actor_id' => $moderator->id,
        ]);
    }

    public function test_author_cannot_moderate(): void
    {
        $writeup = Writeup::factory()->create();

        $this->actingAs(User::factory()->author()->create())
            ->post(route('admin.writeups.approve', $writeup->id))
            ->assertForbidden();
    }
}
