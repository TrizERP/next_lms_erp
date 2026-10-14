<?php

namespace Tests\Unit\Evaluation;

use App\Services\Evaluation\TransientAiFailure;
use App\Services\Homework\Exceptions\DocumentExtractionException;
use App\Services\Homework\Exceptions\EvaluationException;
use App\Services\Homework\Exceptions\ModelQuotaExhaustedException;
use App\Services\Homework\Exceptions\ProviderBusyException;
use App\Services\Homework\Exceptions\RateLimitedException;
use Illuminate\Http\Client\ConnectionException;
use PHPUnit\Framework\TestCase;

class TransientAiFailureTest extends TestCase
{
    public function test_failures_that_clear_by_waiting_are_transient(): void
    {
        $this->assertTrue(TransientAiFailure::is(new ProviderBusyException('busy')));
        $this->assertTrue(TransientAiFailure::is(new RateLimitedException('per-minute limit')));
        $this->assertTrue(TransientAiFailure::is(new ConnectionException('cURL error 28: timed out')));
    }

    public function test_failures_that_waiting_will_not_fix_are_not(): void
    {
        $this->assertFalse(TransientAiFailure::is(new ModelQuotaExhaustedException('daily allowance spent')));
        $this->assertFalse(TransientAiFailure::is(new DocumentExtractionException('unreadable scan')));
        $this->assertFalse(TransientAiFailure::is(new \RuntimeException('bad key')));
    }

    public function test_a_transient_cause_is_found_behind_a_wrapper(): void
    {
        $wrapped = new EvaluationException('grading failed', 0, new ProviderBusyException('busy'));

        $this->assertTrue(TransientAiFailure::is($wrapped));
        $this->assertFalse(TransientAiFailure::is(new EvaluationException('grading failed', 0, new \RuntimeException('bad key'))));
    }
}
