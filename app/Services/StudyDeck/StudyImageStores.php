<?php

namespace App\Services\StudyDeck;

use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use App\Services\StudyDeck\Contracts\SourceImageCache;

/**
 * Where study-deck pictures are kept: the database, and nowhere else.
 *
 * `store()` returns the picture's stable reference (`study-deck-image:<id>`), which is what the deck, the
 * rendered presentation and the stored deck file carry. A picture is never written to a folder, so a
 * generation run leaves no files behind; the same picture is stored once per school (see StudyDeckImages).
 */
final class StudyImageStores
{
    public static function database(int $tenant, ?int $chapterId = null, ?StudyDeckImages $images = null): DiagramImageStore
    {
        return new class($images ?? new StudyDeckImages(), $tenant, $chapterId) implements DiagramImageStore, SourceImageCache {
            public function __construct(
                private readonly StudyDeckImages $images,
                private readonly int $tenant,
                private readonly ?int $chapterId,
            ) {
            }

            /**
             * @param string $mime ignored: the type is read from the bytes
             * @param string|null $sourceUrl where a downloaded picture came from, so it is not downloaded again
             * @return string the picture's reference
             */
            public function store(string $bytes, string $mime, ?string $sourceUrl = null): string
            {
                return $this->images->put($bytes, $this->tenant, $this->chapterId, $sourceUrl)['ref'];
            }

            public function cached(string $url): ?array
            {
                return $this->images->cachedDownload($this->tenant, $url);
            }
        };
    }
}
