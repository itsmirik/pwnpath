<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\ChallengeRepoSync;
use Illuminate\Console\Command;

class SyncChallengesCommand extends Command
{
    protected $signature = 'challenges:sync {--path= : Override the challenges source path}';

    protected $description = 'Sync challenges from the git repo working copy into the database (plan §9)';

    public function handle(ChallengeRepoSync $sync): int
    {
        $report = $sync->sync($this->option('path') ?: null);

        $this->info('Synced from: '.$report['path']);

        if ($report['pulled']) {
            $this->line('git pull: ok');
        }

        $this->line(sprintf(
            'Created: %d   Updated: %d   Errors: %d',
            count($report['created']),
            count($report['updated']),
            count($report['errors']),
        ));

        foreach ($report['created'] as $slug) {
            $this->line("  + {$slug}");
        }

        foreach ($report['updated'] as $slug) {
            $this->line("  ~ {$slug}");
        }

        foreach ($report['errors'] as $folder => $message) {
            $this->error("  ! {$folder}: {$message}");
        }

        AuditLog::record(
            AuditLog::ACTION_CHALLENGE_SYNC,
            actor: null,
            meta: [
                'created' => $report['created'],
                'updated' => $report['updated'],
                'errors' => $report['errors'],
                'source' => 'cli',
            ],
        );

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
