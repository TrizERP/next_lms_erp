<?php

namespace App\Http\Controllers\api\lms\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs an AI evaluation job once the HTTP response has been sent, instead of queueing it.
 *
 * These jobs used to be queued, and with QUEUE_CONNECTION=database and no worker running a
 * submission sat on "Checking" for ever (weeks-old jobs were found unprocessed). Running after
 * the response keeps the student's click instant and still produces the marks without any
 * worker. A throw is routed to the job's own failed() so the row ends on a failure status
 * rather than on "Checking".
 *
 * Provider, model and API key are resolved inside the job from the `evaluation_ai` module
 * (ai_api_keys), so nothing here knows about a key.
 */
trait EvaluatesAfterResponse
{
    protected function evaluateAfterResponse(object $job): void
    {
        app()->terminating(function () use ($job): void {
            @ignore_user_abort(true);
            @set_time_limit(0);

            try {
                app()->call([$job, 'handle']);
            } catch (Throwable $exception) {
                Log::error('Evaluation (after response) failed', [
                    'job' => $job::class,
                    'message' => $exception->getMessage(),
                ]);

                try {
                    $job->failed($exception);
                } catch (Throwable $ignored) {
                    // The row is left as the job last wrote it.
                }
            }
        });
    }
}
