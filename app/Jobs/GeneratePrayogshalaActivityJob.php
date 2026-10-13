<?php

namespace App\Jobs;

use App\Services\lms\Prayogshala\PrayogshalaGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates one topic's Prayogshala activity in the background.
 *
 * One job is one topic, so a curriculum-wide run is many small, independently retryable jobs
 * rather than one request that could time out half way. The generator is idempotent (the row is
 * claimed under a unique index), and the job is unique per (institute, topic), so a double
 * dispatch collapses into one call. The generator records its own failures on the activity row;
 * the job therefore does not rethrow a provider failure (a retry loop would only re-bill it) -
 * a teacher retries from the UI, or the command is run again.
 */
class GeneratePrayogshalaActivityJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 400;
    public int $uniqueFor = 900;

    public function __construct(
        public int $topicId,
        public int $tenant,
        public ?int $userId = null,
        public bool $regenerate = false,
    ) {
    }

    public function uniqueId(): string
    {
        return "prayogshala:{$this->tenant}:{$this->topicId}";
    }

    public function handle(PrayogshalaGenerator $generator): void
    {
        $result = $generator->generate($this->topicId, $this->tenant, $this->userId, $this->regenerate);
        Log::info('Prayogshala generation', ['topic_id' => $this->topicId, 'tenant' => $this->tenant] + $result);
    }
}
