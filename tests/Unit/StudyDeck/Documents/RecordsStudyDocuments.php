<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\Documents\StudyDocumentService;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckPublisher;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Support\Facades\Storage;

/**
 * What a test needs to publish a study document without touching the shared database: a validated document whose
 * diagrams really sit in the (in-memory SQLite) picture table, and a ContentGenerationService whose content_master
 * calls are recorders. Use together with StudyDocumentFixture and UsesImageDatabase, and Storage::fake('digitalocean').
 */
trait RecordsStudyDocuments
{
    /**
     * A validated document of this kind, written through the whole pipeline over the fixture chapter, whose diagrams
     * were stored in the (SQLite) picture table as they were drawn.
     *
     * @return array{0:array<string,mixed>,1:string} the document and its design-system markup
     */
    protected function document(DocumentKind $kind = DocumentKind::RevisionNotes, ?array $replies = null, array $options = []): array
    {
        $service = StudyDocumentService::make(
            $this->completer(array_map(fn ($r) => json_encode($r), $replies ?? $this->repliesFor($kind))),
            StudyImageStores::database(1, 1),
            $this->imageSearch(null)
        );
        $r = $service->fromRaw($kind, $this->raw(), $options);
        $this->assertTrue($r['report']['ok'], "the fixture document must validate:\n- " . implode("\n- ", $r['report']['errors']));

        return [$r['document'], $r['html']];
    }

    /** A ContentGenerationService with every database seam replaced by a recorder. */
    protected function publisher(int $tenant = 1, bool $realPdf = false): object
    {
        return new class(new StudyDeckPublisher(new StudyDeckImages(), $tenant), $realPdf) extends ContentGenerationService {
            public array $inserted = [];

            public array $lookups = [];

            public array $hidden = [];

            public array $restored = [];

            public array $filesAtInsert = [];

            public ?object $existing = null;

            public bool $failInsert = false;

            public bool $failPdf = false;

            public int $pdfRenders = 0;

            public array $pdfOptions = [];

            public function __construct(private readonly StudyDeckPublisher $publisher, private readonly bool $realPdf)
            {
            }

            protected function studyDeckPublisher(int $tenant): StudyDeckPublisher
            {
                return $this->publisher;
            }

            protected function findStudyDocumentRow(int $chapterId, int $tenant, string $filename): ?object
            {
                $this->lookups[] = [$chapterId, $tenant, $filename];

                return $this->existing;
            }

            protected function insertContentRow(array $content, ?callable $inTransaction = null): int|string
            {
                $this->filesAtInsert = Storage::disk('digitalocean')->allFiles();
                if ($this->failInsert) {
                    throw new \RuntimeException('the database went away');
                }
                $this->inserted[] = $content;
                if ($inTransaction) {
                    $inTransaction(777);
                }

                return 777;
            }

            protected function hidePreviousStudyDocuments(DocumentKind $kind, int $chapterId, int $tenant, string $chapterName, string $scope, int $keepId): int
            {
                $this->hidden[] = [$kind, $chapterId, $tenant, $chapterName, $scope, $keepId];

                return 1;
            }

            protected function restoreStudyDocumentRow(DocumentKind $kind, int $id, int $chapterId, int $tenant, string $chapterName, string $scope): bool
            {
                $this->restored[] = [$kind, $id, $chapterId, $tenant, $chapterName, $scope];

                return true;
            }

            protected function renderStudyDocumentPdf(array $document, array $options = []): string
            {
                $this->pdfRenders++;
                $this->pdfOptions[] = $options;
                if ($this->failPdf) {
                    throw new \RuntimeException('dompdf fell over');
                }

                return $this->realPdf ? parent::renderStudyDocumentPdf($document, $options) : '%PDF-1.4 fake ' . $this->pdfRenders;
            }

            protected function loadStudyDeckQuestions(array $ids): array
            {
                return [];
            }
        };
    }
}
