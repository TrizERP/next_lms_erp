<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\Documents\Writers\InteractionWriter;

/**
 * Gives parts of a study document a picture the chapter already has.
 *
 * The chapter's classroom presentation was built with photographs (credited, with their licence) and drawn diagrams, all kept in
 * the database. A study document reuses them: nothing is searched for, fetched or generated again, and a picture keeps the
 * licence and attribution it was stored with. A picture goes to the part whose concepts it illustrates best, and each picture and
 * each part is used once.
 *
 * Pure: the candidates come in, a set comes out, keyed like an interaction (InteractionWriter::key) so the assembler applies it
 * wherever it builds the parts, and the page fit measures what is really printed.
 */
final class DocumentPictures
{
    /** Part types that carry a picture of the concepts they teach. */
    private const TYPES = ['topic', 'unit', 'activity', 'note'];

    /**
     * @param array<string,mixed> $document a finished document
     * @param list<array{concepts:list<int>,image:array<string,mixed>}> $candidates DocumentPictures::candidatesFromDeck()
     * @return array<string,array<string,mixed>> part key => the picture (a DeckImage, marked `reused`)
     */
    public static function choose(array $document, array $candidates, int $max = 12): array
    {
        $pairs = [];
        foreach ($document['sections'] as $part) {
            if (!in_array($part['type'], self::TYPES, true) || ($part['image'] ?? null) !== null) {
                continue;
            }
            $wanted = array_unique(array_merge((array) ($part['taught_concept_ids'] ?? []), (array) ($part['concept_ids'] ?? [])));
            foreach ($candidates as $ci => $c) {
                $overlap = count(array_intersect($wanted, $c['concepts']));
                if ($overlap > 0) {
                    // More shared concepts first; a photograph before a diagram on a tie (a diagram is the part's own drawing's job).
                    $pairs[] = [$overlap, ($c['image']['type'] ?? '') === 'photo' ? 1 : 0, $part['n'], $ci, $part];
                }
            }
        }
        usort($pairs, fn ($a, $b) => [$b[0], $b[1], $a[2], $a[3]] <=> [$a[0], $a[1], $b[2], $b[3]]);

        $set = [];
        $usedParts = [];
        $usedPictures = [];
        foreach ($pairs as [, , $n, $ci, $part]) {
            if (count($set) >= $max || isset($usedParts[$n]) || isset($usedPictures[$ci])) {
                continue;
            }
            $usedParts[$n] = true;
            $usedPictures[$ci] = true;
            $set[InteractionWriter::key($part)] = ['reused' => true] + $candidates[$ci]['image'];
        }

        return $set;
    }

    /**
     * The pictures of a stored study deck, as candidates.
     *
     * @param array<string,mixed> $deck the stored deck (its slides name pictures as `study-deck-image:<id>`)
     * @param list<int> $exclude picture ids to leave out (a picture a person judged unsuitable)
     * @return list<array{concepts:list<int>,image:array<string,mixed>}>
     */
    public static function candidatesFromDeck(array $deck, array $exclude = []): array
    {
        $out = [];
        $seen = [];
        foreach ((array) ($deck['slides'] ?? []) as $slide) {
            $image = $slide['image'] ?? null;
            if (!is_array($image) || !preg_match('/^study-deck-image:(\d+)$/', (string) ($image['url'] ?? ''), $m)) {
                continue;
            }
            $id = (int) $m[1];
            $concepts = array_map('intval', (array) (($slide['taught_concept_ids'] ?? []) ?: ($slide['concept_ids'] ?? [])));
            if ($concepts === [] || isset($seen[$id]) || in_array($id, $exclude, true) || trim((string) ($image['alt'] ?? '')) === '') {
                continue;
            }
            // A photograph must carry its licence and credit.
            if (($image['type'] ?? '') === 'photo' && (trim((string) ($image['licence'] ?? '')) === '' || trim((string) ($image['attribution'] ?? '')) === '')) {
                continue;
            }
            $seen[$id] = true;
            unset($image['asset_id']);
            $out[] = ['concepts' => $concepts, 'image' => $image];
        }

        return $out;
    }
}
