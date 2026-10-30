<?php

namespace Tests\Support;

use App\Services\QuestionPapers\QuestionPaperParser;

/** Answers every chunk with the same canned {header, questions} read from a JSON file: no AI provider is involved. */
class FixtureQuestionPaperParser extends QuestionPaperParser
{
    public function __construct(private string $file)
    {
    }

    public function parseChunk(array $pages, int $tenant): array
    {
        $fixture = json_decode((string) file_get_contents($this->file), true);

        return ['header' => $fixture['header'] ?? [], 'questions' => $fixture['questions'] ?? []];
    }
}
