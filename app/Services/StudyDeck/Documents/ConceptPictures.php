<?php

namespace App\Services\StudyDeck\Documents;

/**
 * A picture for each CONCEPT a study document teaches, so the picture sits beside the words it shows.
 *
 * A part carries one picture, which is too few for a topic of sixteen ideas. This gives the document a second, finer layer:
 * `concept_images`, a picture keyed by concept id. The viewer and the printed pages show it wherever the concept appears
 * (its row in a table, its definition, its node in the map, its lesson).
 *
 * Where each picture comes from, in this order:
 *   1. the picture the chapter's presentation already stores for a slide that teaches the concept (nothing is searched,
 *      fetched or generated again, and it keeps the licence and credit it was stored with);
 *   2. one found by the existing picture finder (ImagePlanner::find: openly licensed sources, licence check, title and tag
 *      match, a relevance review, a sharpness check, a stored copy) for the concept's NAME.
 *
 * The finder is handed in, so this class is pure and testable. The finder's own relevance review decides, and it says no to
 * an idea that no picture can show (a photograph of "cells are microscopic" teaches nothing), so some concepts have no picture
 * and that is the right answer. Nothing is invented and no picture is generated.
 */
final class ConceptPictures
{
    /** Most pictures a document carries. A page is read, not scrolled through a gallery. */
    public const MAX = 36;

    /**
     * The picture the presentation stored for a slide teaching the concept, for every concept that has one.
     *
     * @param array<string,mixed> $document a finished document
     * @param list<array{concepts:list<int>,image:array<string,mixed>}> $candidates DocumentPictures::candidatesFromDeck()
     * @return array<string,array<string,mixed>> concept id => picture (a DeckImage marked `reused`)
     */
    public static function fromCandidates(array $document, array $candidates, int $max = self::MAX): array
    {
        $set = [];
        $used = [];
        foreach (array_keys((array) $document['concepts']) as $id) {
            if (count($set) >= $max) {
                break;
            }
            foreach ($candidates as $ci => $c) {
                // A candidate belongs to the concept only when its slide taught that concept alone, or the concept leads it:
                // a picture of three ideas at once is not "the" picture of the third.
                if (isset($used[$ci]) || !in_array((int) $id, $c['concepts'], true) || (int) ($c['concepts'][0] ?? 0) !== (int) $id) {
                    continue;
                }
                $used[$ci] = true;
                $set[(string) $id] = ['reused' => true] + $c['image'];
                break;
            }
        }

        return $set;
    }

    /**
     * Find a picture for each concept that has none yet, by its name.
     *
     * @param array<string,mixed> $document a finished document
     * @param array<string,array<string,mixed>> $have pictures already chosen (concept id => picture): kept, never searched again
     * @param callable(string,array<int,string>,string,string):array<string,mixed> $find ImagePlanner::find: a picture record, or ['missing' => reason]
     * @param callable(string,string):void|null $say progress
     * @return array{set:array<string,array<string,mixed>>,missing:array<string,string>} the pictures, and why a concept has none
     */
    public static function search(array $document, array $have, callable $find, int $max = self::MAX, ?callable $say = null): array
    {
        $say ??= fn () => null;
        $set = $have;
        $missing = [];
        // A picture is used for one concept only: its source is excluded from the later searches.
        $exclude = array_values(array_filter(array_map(fn ($i) => (string) ($i['source_image_url'] ?? ''), $have)));

        $ids = array_keys((array) $document['concepts']);
        $todo = array_values(array_filter($ids, fn ($id) => !isset($set[(string) $id])));
        foreach ($todo as $k => $id) {
            if (count($set) >= $max) {
                $missing[(string) $id] = 'the document already has its most pictures';
                continue;
            }
            $concept = $document['concepts'][(string) $id];
            $name = self::queryFor((string) ($concept['name'] ?? ''));
            if ($name === '') {
                $missing[(string) $id] = 'the concept has no name to search for';
                continue;
            }
            $teaches = self::firstSentence((string) ($concept['definition'] ?? ''));
            $picture = $find($name, $exclude, 'Shows ' . $name . ' so the learner can picture it', $teaches);
            if (isset($picture['missing']) || empty($picture['url'])) {
                $missing[(string) $id] = (string) ($picture['missing'] ?? 'nothing found');
                $say('pictures', sprintf('%d/%d  %s: none (%s)', $k + 1, count($todo), $name, $missing[(string) $id]));
                continue;
            }
            $set[(string) $id] = $picture + ['concept_id' => (int) $id];
            if (!empty($picture['source_image_url'])) {
                $exclude[] = (string) $picture['source_image_url'];
            }
            $say('pictures', sprintf('%d/%d  %s: found', $k + 1, count($todo), $name));
        }

        return ['set' => $set, 'missing' => $missing];
    }

    /** A concept's name as a search phrase: the name itself, without a trailing full stop or stray spaces. */
    public static function queryFor(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', rtrim(trim($name), '.')) ?? '');
    }

    private static function firstSentence(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9])/', $text, 2);

        return (string) ($parts[0] ?? '');
    }
}
