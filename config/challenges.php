<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Challenge files disk
    |--------------------------------------------------------------------------
    |
    | Disk challenge attachments live on (see config/filesystems.php). S3 in
    | prod; set CHALLENGES_DISK_DRIVER=local for dev without object storage.
    |
    */

    'disk' => 'challenges',

    /*
    |--------------------------------------------------------------------------
    | Authoring repo sync (plan §9)
    |--------------------------------------------------------------------------
    |
    | Source-of-truth for challenges is a git repo working copy. Each direct
    | subfolder is one challenge: challenge.yml + <locale>.md + hints/ + files/.
    | The admin "Sync from repo" button and `challenges:sync` command read here.
    |
    */

    'source_path' => env('CHALLENGES_SOURCE_PATH', base_path('challenges')),

    // Run `git pull` in source_path before reading (needs a git checkout there).
    'git_pull' => (bool) env('CHALLENGES_GIT_PULL', false),

    // Upload ceilings (plan §9 checklist).
    'max_file_bytes' => 10 * 1024 * 1024,    // 10 MB per file
    'max_total_bytes' => 25 * 1024 * 1024,   // 25 MB per challenge

    /*
    |--------------------------------------------------------------------------
    | Suspicious-solve detection (plan §11)
    |--------------------------------------------------------------------------
    |
    | Flag an account for review when it solves more than N hard challenges
    | inside the rolling window.
    |
    */

    'suspicious_hard_solves' => (int) env('SUSPICIOUS_HARD_SOLVES', 5),
    'suspicious_window_minutes' => (int) env('SUSPICIOUS_WINDOW_MINUTES', 5),

];
