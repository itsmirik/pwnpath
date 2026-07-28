<?php

namespace App\Console\Commands;

use App\Services\SuspiciousSolveDetector;
use Illuminate\Console\Command;

class DetectSuspiciousSolvesCommand extends Command
{
    protected $signature = 'security:detect-suspicious-solves';

    protected $description = 'Flag accounts solving too many hard challenges too fast (plan §11)';

    public function handle(SuspiciousSolveDetector $detector): int
    {
        $flagged = $detector->detect();

        if ($flagged === []) {
            $this->info('No suspicious solve patterns detected.');

            return self::SUCCESS;
        }

        $this->warn(count($flagged).' account(s) flagged for review:');

        foreach ($flagged as $row) {
            $this->line(sprintf(
                '  user #%d — %d hard solves in %dm',
                $row['user_id'],
                $row['hard_solves'],
                $row['window_minutes'],
            ));
        }

        return self::SUCCESS;
    }
}
