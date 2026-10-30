<?php

// How long a worker may hold a job before the queue decides it has died and hands the
// job to ANOTHER worker ("retry_after" for database and redis, the job TTR for beanstalkd).
//
// It has to be longer than the slowest job's `$timeout`, or a job that is still running
// gets delivered a second time and runs twice. Exam answer-sheet evaluation times out at
// 600s, so where it runs set QUEUE_RETRY_AFTER=660 (timeout + a minute's margin, which is
// also how long its overlap lock is held). Unset, the old value of 90 stays, so nothing
// changes for a deployment that does not opt in.
//
// One value, read once, applied to whichever of these drivers is actually in use: it used
// to reach only "database", so QUEUE_CONNECTION=redis silently ignored it. The price of a
// longer value is slower recovery from a worker that crashed mid-job: that job sits
// invisible for the whole interval before anyone retries it. SQS has no such setting here;
// its visibility timeout belongs to the queue itself (set it above the longest job timeout
// in AWS). See docs/EXAM_EVALUATION_DEPLOYMENT_RUNBOOK.md.
//
// Anything that is not a positive number (a typo, an empty value, 0) falls back to 90
// rather than being used: zero or one second would redeliver every job the instant it was
// picked up, which is far worse than the old default.
$retryAfter = (int) env('QUEUE_RETRY_AFTER', 90);
$retryAfter = $retryAfter > 0 ? $retryAfter : 90;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => $retryAfter,
            'after_commit' => false,
        ],

        // Question Paper extraction. Same `jobs` table as `database`, but its own queue name and a
        // retry_after ABOVE the job's 900s timeout: with the default 90s a still-running extraction
        // would be handed to a second worker. A worker started with
        //   php artisan queue:work question_papers --queue=question-papers
        // runs only these jobs and never anything else waiting in `default`.
        'question_papers' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'question-papers',
            'retry_after' => 1000,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => $retryAfter,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => $retryAfter,
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
