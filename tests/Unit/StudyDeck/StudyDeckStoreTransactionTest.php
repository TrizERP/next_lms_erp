<?php

namespace Tests\Unit\StudyDeck;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Story\StoryTemplate;
use Tests\Feature\CoursePresentation\CoursePresentationTestCase;

/**
 * storeStudyDeck() hands the content-row insert a closure that runs inside the transaction. That closure once read $input,
 * which it did not capture ("Undefined variable $input"), so every store failed after the pictures were drawn.
 *
 * NO REAL DATABASE and NO object store: the seams that touch them are overridden and record what the closure asked for. The
 * base class boots the application on throwaway in-memory sqlite and refuses to run against anything else (a plain
 * Tests\TestCase would boot against the shared database named in .env).
 */
class StudyDeckStoreTransactionTest extends CoursePresentationTestCase
{
    private function service(): object
    {
        return new class extends ContentGenerationService {
            public array $hidden = [];

            public array $linked = [];

            public function __construct()
            {
            }

            protected function renderStudyDeckPdf(array $deck, array $options = []): string
            {
                return '%PDF-1.4 fake';
            }

            protected function withStudyDeckImages(int $tenant, callable $step): mixed
            {
                return $step();
            }

            /** What the real storeAuthoredContent does with the closure: runs it with the new row id, inside the transaction. */
            public function storeAuthoredContent(array $input, string $html, string $authoredBy = 'authored'): array
            {
                ($input['in_transaction'])(777);

                return ['http' => 201, 'body' => ['data' => []]];
            }

            protected function hidePreviousStudyDecks(int $chapterId, int $tenant, int $keepId, string $like = 'study\\_deck\\_'): int
            {
                $this->hidden[] = [$chapterId, $tenant, $keepId, $like];

                return 1;
            }

            protected function linkStudyDeckImages(int $contentId, array $deck, ?int $chapterId): int
            {
                $this->linked[] = [$contentId, $chapterId];

                return 0;
            }
        };
    }

    private function store(object $svc, string $filename): array
    {
        $input = ['chapter' => (object) ['id' => 8592], 'sub_institute_id' => 1, 'filename' => $filename];

        return $svc->storeStudyDeck($input, '<section class="slide"></section>', ['version' => 3, 'slides' => []]);
    }

    public function test_the_transaction_closure_runs_and_hides_only_decks_of_the_same_kind(): void
    {
        $svc = $this->service();

        $presentation = $this->store($svc, 'study_deck_exploration_abcdef123456.pptx');
        $story = $this->store($svc, StoryTemplate::FILE_PREFIX . 'exploration_abcdef123456.pptx');

        $this->assertSame(201, $presentation['http']);
        $this->assertSame(201, $story['http']);
        $this->assertSame(
            [[8592, 1, 777, 'study\\_deck\\_'], [8592, 1, 777, 'study\\_story\\_']],
            $svc->hidden,
            'a presentation hides older presentations, a story lesson hides older story lessons',
        );
        $this->assertSame([[777, 8592], [777, 8592]], $svc->linked, 'the picture links are written in the same transaction');
    }
}
