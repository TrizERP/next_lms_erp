<?php

namespace App\Services\StudyDeck\Contracts;

/**
 * A completer that can answer several independent prompts at the same time. The pipeline uses it where chunks do not depend on
 * each other (slide text, interaction choices); a completer without it is asked one prompt after another, as before.
 */
interface ParallelCompleter extends Completer
{
    /**
     * @param array<int,string> $prompts
     * @return array<int,string> the same keys, each the reply text; throws when any prompt could not be answered within the time allowed
     */
    public function completeMany(string $system, array $prompts, int $maxTokens = 16000): array;
}
