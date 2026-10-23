<?php

namespace App\Services\CoursePresentation\Templates;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\TextbookImages;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;

/**
 * Pictures for the source-data PREVIEW, taken from the chapter's own textbook and nothing else.
 *
 * The preview is read-only and instant: it makes no web search (a search takes up to a minute here) and stores nothing. So its pictures
 * come from the curated textbook manifest (see TextbookImages): a picture is offered for a concept only when what the picture SHOWS and
 * its printed caption share enough specific words with the concept's name and topic. The picture is shrunk and carried inside the preview
 * itself as a data address, so it loads without a record in the picture library. The same ImagePlanner::textbookPicture rules apply as in
 * a generated deck: credit to the book and page, the textbook licence label, `source_kind: textbook`.
 *
 * A picture may serve two concepts at most (a map of water stress is relevant to two scarcity ideas); a concept with no relevant picture
 * gets none, and the slide says so.
 */
final class TextbookPicturePool
{
    /** The most concepts one textbook picture may be shown for. */
    public const MAX_USES = 2;

    /** Longest side of a preview picture, in pixels. */
    private const MAX_SIDE = 900;

    public function __construct(private readonly ?TextbookImages $book = null)
    {
    }

    /**
     * @param array<int,array<string,mixed>> $concepts ConceptCoverage concepts: id, name, topic
     * @return array{by_concept:array<int,array<string,mixed>>,missing:array<int,string>,available:int}
     */
    public function forChapter(int $chapterId, array $concepts): array
    {
        $book = $this->book ?? TextbookImages::fromConfig();
        if ($book === null) {
            return ['by_concept' => [], 'missing' => array_values(array_map(fn ($c) => (string) $c['name'], $concepts)), 'available' => 0];
        }
        $store = new class implements DiagramImageStore {
            public function store(string $bytes, string $mime, ?string $sourceUrl = null): string
            {
                return TextbookPicturePool::dataAddress($bytes, $mime);
            }
        };
        $uses = [];
        $by = [];
        $missing = [];
        foreach ($concepts as $id => $c) {
            $name = trim((string) $c['name']);
            $topic = trim((string) ($c['topic'] ?? ''));
            // A fresh planner per concept (so a picture may serve a second concept); files already used twice are skipped.
            $planner = (new ImagePlanner(app(ConceptImageSearchService::class), $store))->withTextbookImages($book, $chapterId);
            $skip = array_keys(array_filter($uses, fn ($n) => $n >= self::MAX_USES));
            $record = $planner->textbookPicture($name, '', $name, $skip);
            if ($record === null) {
                $missing[] = $name;
                continue;
            }
            $file = substr((string) $record['source_image_url'], strlen('textbook:'));
            $uses[$file] = ($uses[$file] ?? 0) + 1;
            $by[(int) $id] = $record;
        }

        return ['by_concept' => $by, 'missing' => $missing, 'available' => $book->count()];
    }

    /** A picture shrunk to a preview size and written as a data address (JPEG, or PNG where it has transparency). */
    public static function dataAddress(string $bytes, string $mime): string
    {
        $im = @imagecreatefromstring($bytes);
        if ($im === false) {
            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min(1.0, self::MAX_SIDE / max($w, $h));
        if ($scale < 1.0) {
            $small = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
            imagealphablending($small, false);
            imagesavealpha($small, true);
            imagefill($small, 0, 0, imagecolorallocatealpha($small, 255, 255, 255, 127));
            imagecopyresampled($small, $im, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);
            $im = $small;
        }
        // Flatten on white: a transparent icon-style picture would otherwise turn black as a JPEG.
        $flat = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        ob_start();
        imagejpeg($flat, null, 82);

        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }
}
