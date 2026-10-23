<?php

namespace App\Services\CoursePresentation;

use App\Services\ContentGenerationService;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Can the study-deck pipeline reach a model at all? Answers yes or no and nothing else: the key itself is never
 * read into a response, a log or an error. It asks the same resolver the pipeline's own command uses, so the answer
 * cannot differ from what a run would find.
 */
class ModelAvailability
{
    /** Whether the model a given format needs is reachable: a story lesson needs Gemini, everything else the Claude setup. */
    public function availableFor(string $format): bool
    {
        return $format === 'branching_scenario' ? $this->geminiAvailable() : ($this->available() || $this->geminiAvailable());
    }

    /** Which executor writes slides: the configured Claude one when it is reachable, otherwise the estate's Gemini client. */
    public function slidesExecutor(): string
    {
        if ($this->available()) {
            return (string) config('claude.executor', 'api');
        }

        return $this->geminiAvailable() ? 'gemini' : (string) config('claude.executor', 'api');
    }

    /** Story lessons use the estate's Gemini key (key pool or GEMINI_API_KEY); no Anthropic key or Claude CLI is involved. */
    public function geminiAvailable(): bool
    {
        return \App\Services\StudyDeck\Story\GeminiStoryCompleter::available();
    }

    public function available(): bool
    {
        if ((string) config('claude.executor', 'api') === 'cli') {
            // The command-line executor (a development setup): present only if the binary is on the path.
            return (new ExecutableFinder())->find('claude') !== null;
        }

        $resolver = new class extends ContentGenerationService {
            public function hasKey(): bool
            {
                return $this->resolveApiKey() !== '';
            }
        };

        try {
            return $resolver->hasKey();
        } catch (\Throwable) {
            return false;
        }
    }
}
