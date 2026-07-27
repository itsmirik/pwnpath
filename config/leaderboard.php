<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Weekly leaderboard driver
    |--------------------------------------------------------------------------
    |
    | The weekly (rolling 7-day) leaderboard is served from Redis sorted sets
    | in production for O(log n) writes and range reads. Set this to "database"
    | to compute it directly from the solves table instead — used in the test
    | suite (no Redis) and as an automatic fallback when Redis is unavailable.
    |
    | Supported: "redis", "database"
    */

    'weekly_driver' => env('LEADERBOARD_WEEKLY_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Weekly bucket TTL (days)
    |--------------------------------------------------------------------------
    |
    | Each day's sorted set expires this many days after it is written. The
    | window query unions the last 7 daily buckets, so 8 keeps yesterday's
    | data alive long enough for the full rolling window.
    */

    'weekly_ttl_days' => 8,

];
