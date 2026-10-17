<?php

namespace Tests\Support;

/**
 * Stored-sidecar shapes for chapter 8592, copied from what the read-only inspection found in the real
 * study deck (61401), classroom-activities document (61404) and revision notes (61402): the topic and
 * concept ids, slide types, stored minutes, bank question ids and activity sections are the real ones for
 * the topics used in the tests (23809, 23810, 23812). Titles are shortened.
 */
class CedsFlowFixtures
{
    /** @return array<string,mixed> */
    public static function deck(): array
    {
        $slide = fn (int $n, string $type, string $title, array $concepts, int $min, array $q = []) => [
            'n' => $n, 'slide_type' => $type, 'title' => $title, 'concept_ids' => $concepts, 'taught_concept_ids' => $concepts,
            'question_ids' => $q, 'content' => ['minutes' => $min],
        ];

        return [
            'version' => 3,
            'concepts' => [
                ['id' => 7184, 'topic_id' => 23809], ['id' => 7185, 'topic_id' => 23809], ['id' => 7186, 'topic_id' => 23809],
                ['id' => 7187, 'topic_id' => 23810], ['id' => 7188, 'topic_id' => 23810], ['id' => 7189, 'topic_id' => 23810], ['id' => 7190, 'topic_id' => 23810],
                ['id' => 7196, 'topic_id' => 23812], ['id' => 7197, 'topic_id' => 23812], ['id' => 7198, 'topic_id' => 23812], ['id' => 7199, 'topic_id' => 23812],
            ],
            'slides' => [
                $slide(1, 'cover', 'Exploration', [], 1),
                $slide(2, 'hook', 'A magnifying glass and a compass', [], 3),
                $slide(3, 'objectives', 'What we will explore', [], 2),
                $slide(4, 'concept_intro', 'Science asks what and how', [7184], 3),
                $slide(5, 'concept_visual', 'Noticing details', [7185], 3),
                $slide(6, 'relationship', 'Two ways of asking', [7186, 7185], 3),
                $slide(7, 'concept_visual', 'Models are simplified views', [7187], 3, [701403]),
                $slide(8, 'relationship', 'Ignoring details on purpose', [7188, 7187], 3),
                $slide(9, 'worked_example', 'Example 1.1: a cricket shot for a six', [7189, 7188], 4, [701405]),
                $slide(10, 'concept_visual', 'Adding detail for greater accuracy', [7190], 3),
                $slide(16, 'concept_intro', 'Law, theory, principle', [7196], 3),
                $slide(17, 'relationship', 'How they connect', [7197, 7196], 3),
                $slide(18, 'concept_intro', 'Say it precisely', [7198], 3),
                $slide(19, 'misconception', 'Theory is only a guess', [7199], 3, [701411]),
                $slide(34, 'concept_map', 'The map of exploration', [], 3),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function activities(): array
    {
        return ['sections' => [
            ['n' => 0, 'type' => 'cover', 'title' => 'Exploration', 'topic_id' => null, 'concept_ids' => [], 'content' => []],
            ['n' => 1, 'type' => 'activity', 'title' => 'Model the cricket six', 'topic_id' => 23810, 'concept_ids' => [7187, 7188, 7189, 7190],
                'content' => ['minutes' => 25, 'format' => 'inquiry', 'grouping' => 'pair']],
            ['n' => 2, 'type' => 'activity', 'title' => 'Magnifying glass and compass', 'topic_id' => 23809, 'concept_ids' => [7185, 7186, 7184],
                'content' => ['minutes' => 10, 'format' => 'discussion', 'grouping' => 'group']],
            ['n' => 5, 'type' => 'activity', 'title' => 'Law, theory or principle', 'topic_id' => 23812, 'concept_ids' => [7196, 7197, 7198, 7199],
                'content' => ['minutes' => 15, 'format' => 'sequencing', 'grouping' => 'group']],
        ]];
    }

    /** @return array<string,mixed> */
    public static function notes(): array
    {
        $note = fn (int $n, string $title, int $topic, array $concepts) => ['n' => $n, 'type' => 'note', 'title' => $title, 'topic_id' => $topic, 'concept_ids' => $concepts];

        return ['sections' => [
            ['n' => 1, 'type' => 'cover', 'title' => 'Revision notes', 'topic_id' => null, 'concept_ids' => []],
            $note(4, 'Models as simplified representations', 23810, [7187]),
            $note(5, 'Deliberate ignoring of details', 23810, [7188]),
            $note(6, 'Question decides relevant details', 23810, [7189]),
            $note(7, 'Models gain accuracy with added detail', 23810, [7190]),
            $note(9, 'Law, theory or principle', 23812, [7196, 7197]),
        ]];
    }

    /** @return array<string,mixed> the material LessonFlowBuilder takes */
    public static function material(array $over = []): array
    {
        return $over + [
            'deck' => self::deck(), 'deck_id' => 61401,
            'activities' => self::activities(), 'activities_id' => 61404,
            'notes' => self::notes(), 'notes_id' => 61402,
            'labs' => [
                ['id' => 1, 'topic_id' => 23810, 'title' => 'Same Ball, Different Questions', 'status' => 'review'],
                ['id' => 5, 'topic_id' => 23809, 'title' => 'Compass or Wandering?', 'status' => 'review'],
            ],
            'unreadable' => [],
        ];
    }
}
