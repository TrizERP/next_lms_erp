<?php

namespace App\Services\lms\Content;

use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckImageUrls;
use Illuminate\Support\Facades\DB;

/**
 * A Vocabulary Class: one flashcard deck, stored as ONE content_master row.
 *
 * The whole deck is a single JSON document in `content_master.description`, so there is no table of cards, no row per
 * card and no sidecar file: a row that holds the document IS the deck. The row is recognised by its file name
 * (`vocabulary_class__<key>.json`, the same "the name is the identity" convention the study decks use), and filed under
 * its own 'Vocabulary Cards' category, which the Classroom Resource list shows as the "Vocabulary cards" tab.
 *
 * ONE ROW, FOUR CHAPTERS. A deck can cover several chapters (each topic names its own chapter_id), but a content_master
 * row has one chapter_id. The row is stored against the chapter of its first topic and `forChapter()` lists it for the
 * other chapters its topics name, within the same standard, subject and school. Nothing is copied.
 *
 * Pure validation lives here so the store command, the read API and the tests share one definition of "a deck".
 */
class VocabularyClassDeck
{
    public const TYPE = 'vocabulary_class';

    public const VERSION = 1;

    /** Its own Classroom Resource category and tab: "Vocabulary cards". */
    public const CATEGORY = 'Vocabulary Cards';

    public const FILE_PREFIX = 'vocabulary_class__';

    public const FILE_TYPE = 'flashcards';

    /** content_master.source: written by people from a supplied document, not generated. */
    public const SOURCE = 'Authored';

    public static function isVocabularyFilename(?string $filename): bool
    {
        return str_starts_with((string) $filename, self::FILE_PREFIX) && str_ends_with((string) $filename, '.json');
    }

    public static function filenameFor(string $key): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($key)), '_');

        return self::FILE_PREFIX . $slug . '.json';
    }

    /** The student route that plays a deck, addressed by the chapter the learner came from. */
    public static function deepLink(int $chapterId, int $contentId): string
    {
        return '/student/vocabulary-class/' . $chapterId . '?content=' . $contentId;
    }

    /** @return list<int> the chapters the deck's topics name, in topic order */
    public static function chapterIds(array $deck): array
    {
        $ids = [];
        foreach ((array) ($deck['topics'] ?? []) as $topic) {
            $id = (int) ($topic['chapter_id'] ?? 0);
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function cardCount(array $deck): int
    {
        $n = 0;
        foreach ((array) ($deck['topics'] ?? []) as $topic) {
            $n += count((array) ($topic['cards'] ?? []));
        }

        return $n;
    }

    /**
     * Why a document is not a playable deck. An empty list means it is one.
     *
     * Strict about what the player relies on (ids, terms, definitions, a real address for every picture) and silent
     * about anything extra, so a deck with added fields still plays.
     *
     * @return list<string>
     */
    public static function problems(mixed $deck): array
    {
        if (!is_array($deck)) {
            return ['The document is not a JSON object.'];
        }
        $problems = [];
        if (($deck['type'] ?? null) !== self::TYPE) {
            $problems[] = 'type must be "' . self::TYPE . '".';
        }
        if (($deck['version'] ?? null) !== self::VERSION) {
            $problems[] = 'version must be ' . self::VERSION . '.';
        }
        if (trim((string) ($deck['title'] ?? '')) === '') {
            $problems[] = 'title is required.';
        }
        $topics = $deck['topics'] ?? null;
        if (!is_array($topics) || $topics === []) {
            return array_merge($problems, ['topics must be a non-empty list.']);
        }

        $seenCards = [];
        $seenTopics = [];
        foreach ($topics as $t => $topic) {
            $where = 'topics[' . $t . ']';
            $topicId = trim((string) ($topic['id'] ?? ''));
            if ($topicId === '' || isset($seenTopics[$topicId])) {
                $problems[] = $where . ' needs a unique id.';
            }
            $seenTopics[$topicId] = true;
            if ((int) ($topic['chapter_id'] ?? 0) < 1) {
                $problems[] = $where . ' needs a chapter_id.';
            }
            if (trim((string) ($topic['title'] ?? '')) === '') {
                $problems[] = $where . ' needs a title.';
            }
            $cards = $topic['cards'] ?? null;
            if (!is_array($cards) || $cards === []) {
                $problems[] = $where . ' needs at least one card.';
                continue;
            }
            foreach ($cards as $c => $card) {
                $at = $where . '.cards[' . $c . ']';
                $id = trim((string) ($card['id'] ?? ''));
                if ($id === '' || isset($seenCards[$id])) {
                    $problems[] = $at . ' needs a unique id.';
                }
                $seenCards[$id] = true;
                if (trim((string) ($card['term'] ?? '')) === '') {
                    $problems[] = $at . ' needs a term.';
                }
                if (trim((string) ($card['definition'] ?? '')) === '') {
                    $problems[] = $at . ' needs a definition.';
                }
                $image = $card['image'] ?? null;
                if ($image !== null) {
                    $src = (string) ($image['src'] ?? '');
                    if (!is_array($image) || (StudyDeckImages::idFromRef($src) === null && !preg_match('#^https://[^\s]+$#', $src))) {
                        $problems[] = $at . '.image needs a stored picture reference (study-deck-image:<id>) or an https src.';
                    }
                    if (trim((string) ($image['alt'] ?? '')) === '') {
                        $problems[] = $at . '.image needs alt text.';
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * The deck with every `study-deck-image:<id>` picture reference replaced by a signed address the browser can load.
     *
     * The pictures live in the database (study_deck_images), shared with the study decks. A picture the school may not
     * see, or that is gone, keeps its reference and loads nothing; the rest of the deck still plays.
     *
     * @param array<string,mixed> $deck
     * @return array<string,mixed>
     */
    public static function hydrate(array $deck, int $tenant): array
    {
        $wanted = [];
        foreach ($deck['topics'] as $topic) {
            foreach ($topic['cards'] as $card) {
                $id = StudyDeckImages::idFromRef($card['image']['src'] ?? null);
                if ($id !== null) {
                    $wanted[$id] = $id;
                }
            }
        }
        if ($wanted === []) {
            return $deck;
        }
        $urls = (new StudyDeckImageUrls())->forIds(array_values($wanted), $tenant);
        foreach ($deck['topics'] as $t => $topic) {
            foreach ($topic['cards'] as $c => $card) {
                $id = StudyDeckImages::idFromRef($card['image']['src'] ?? null);
                if ($id !== null && isset($urls[$id])) {
                    $deck['topics'][$t]['cards'][$c]['image']['src'] = $urls[$id];
                }
            }
        }

        return $deck;
    }

    /** The deck stored in a content_master row, or null when the row is not a readable deck. */
    public static function decode(?string $description): ?array
    {
        $deck = json_decode((string) $description, true);

        return is_array($deck) && self::problems($deck) === [] ? $deck : null;
    }

    /**
     * Deck rows from OTHER chapters that name this chapter in a topic, for the Classroom Resource list.
     *
     * Same school rule as the list itself (`$tenants`), and the same standard and subject: a deck only ever reaches
     * chapters of the book it was made for. Rows already in the chapter's own list are not repeated.
     *
     * @param  list<int|string>  $tenants
     * @return list<array<string,mixed>>
     */
    public static function siblingRows(int $chapterId, int $subjectId, int $standardId, array $tenants): array
    {
        $rows = DB::table('content_master')
            ->where('content_category', self::CATEGORY)
            ->where('filename', 'like', self::FILE_PREFIX . '%')
            ->where('subject_id', $subjectId)
            ->where('standard_id', $standardId)
            ->where('chapter_id', '<>', $chapterId)
            ->whereIn('sub_institute_id', $tenants)
            ->where(fn ($q) => $q->whereNull('show_hide')->orWhere('show_hide', '<>', 0))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            // LIKE's `_` matches any character, so the name is confirmed here too.
            $deck = self::isVocabularyFilename($row->filename) ? self::decode($row->description) : null;
            if ($deck !== null && in_array($chapterId, self::chapterIds($deck), true)) {
                $out[] = (array) $row;
            }
        }

        return $out;
    }
}
