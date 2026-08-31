<?php

namespace App\Domain\AI\Signals;

/**
 * What one detector sweep was actually able to judge.
 *
 * A detector that returns no signals is saying one of two very different things: "I
 * looked at these students and none of them is at risk", or "I could not form a view,
 * because the records I need are not there". Only the first is reassuring, and until
 * this existed the pipeline reported both as silence — so a trace could state that
 * three detectors had queried live records while one of them had, in fact, skipped
 * every student in the school for want of five attendance rows.
 *
 * `requirement` is the sentence that names the bar a student has to clear before the
 * detector can say anything at all, so a reader can see whether the gap is the school's
 * risk profile or the school's data capture.
 */
final class DetectorCoverage
{
    public function __construct(
        public readonly string $signalKey,
        /** Students the sweep considered. */
        public readonly int $examined,
        /** Of those, how many held enough records to evaluate. */
        public readonly int $evaluated,
        /** Of those evaluated, how many produced a signal. */
        public readonly int $signalled,
        /** The minimum this detector needs before it can judge a student. */
        public readonly string $requirement,
    ) {
    }

    /** Students skipped because there was too little to judge. */
    public function insufficientData(): int
    {
        return max(0, $this->examined - $this->evaluated);
    }

    /**
     * True when the detector could not form a view about a single student.
     *
     * This is the state worth shouting about: the detector ran, cost time, and
     * contributed nothing, and no reader of the trace could have known.
     */
    public function isBlind(): bool
    {
        return $this->examined > 0 && $this->evaluated === 0;
    }

    /** One plain sentence, suitable for the trace. */
    public function summary(): string
    {
        if ($this->examined === 0) {
            return 'No students were in scope for this detector.';
        }

        if ($this->isBlind()) {
            return sprintf(
                'Could not judge any of the %d students examined: %s',
                $this->examined,
                $this->requirement
            );
        }

        $skipped = $this->insufficientData();

        return sprintf(
            'Judged %d of %d students and raised %d signal%s.%s',
            $this->evaluated,
            $this->examined,
            $this->signalled,
            $this->signalled === 1 ? '' : 's',
            $skipped === 0
                ? ''
                : sprintf(' %d had too little data to judge: %s', $skipped, $this->requirement)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'signal_key' => $this->signalKey,
            'examined' => $this->examined,
            'evaluated' => $this->evaluated,
            'insufficient_data' => $this->insufficientData(),
            'signalled' => $this->signalled,
            'blind' => $this->isBlind(),
            'requirement' => $this->requirement,
            'summary' => $this->summary(),
        ];
    }
}
