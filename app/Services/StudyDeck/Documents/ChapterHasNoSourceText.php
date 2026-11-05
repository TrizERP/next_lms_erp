<?php

namespace App\Services\StudyDeck\Documents;

/**
 * The chapter has no extracted textbook text, which is the only source of facts a study document may use.
 *
 * Still a RuntimeException (callers and tests that expect one are unaffected); it is its own class so that
 * ContentGenerationService can tell a teacher "this chapter has no source text yet" instead of a generic failure.
 */
class ChapterHasNoSourceText extends \RuntimeException
{
    public static function forChapter(int $chapterId): self
    {
        return new self('Chapter ' . $chapterId . ' has no ground-truth text (document_extractions.md_content); refusing to generate.');
    }
}
