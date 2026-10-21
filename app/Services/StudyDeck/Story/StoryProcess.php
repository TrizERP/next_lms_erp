<?php

namespace App\Services\StudyDeck\Story;

/**
 * The scientific process a story scene can show: Observation, Investigation, Evidence, Conclusion.
 *
 * A scene that is one of these steps carries `process_step`, and its picture is the same four-step sequence drawn with that step in focus
 * (DiagramRenderer's `focus`), so across a story the learner sees the sequence move on. The four words are an approved teaching frame,
 * not claims about the chapter: they are exempt from the check that every label of a drawn diagram comes from the chapter text, while
 * everything else a diagram says still has to.
 */
final class StoryProcess
{
    /** step key => the label drawn, in order. */
    public const STEPS = [
        'observation' => 'Observation',
        'investigation' => 'Investigation',
        'evidence' => 'Evidence',
        'conclusion' => 'Conclusion',
    ];

    public const TITLE = 'Observation to conclusion';

    public static function valid(string $step): bool
    {
        return isset(self::STEPS[$step]);
    }

    /**
     * The sequence as a diagram spec, with the given step in focus.
     *
     * @return array{layout:string,title:string,nodes:array<int,string>,focus:int}
     */
    public static function spec(string $step): array
    {
        $keys = array_keys(self::STEPS);

        return ['layout' => 'flow', 'title' => self::TITLE, 'nodes' => array_values(self::STEPS), 'focus' => (int) array_search($step, $keys, true)];
    }

    /** Lower-case words that belong to the approved frame (so a label made of them needs no support from the chapter text). */
    public static function words(): array
    {
        return array_values(array_unique(array_merge(
            array_map('mb_strtolower', array_values(self::STEPS)),
            array_filter(preg_split('/[^a-z]+/', mb_strtolower(self::TITLE)) ?: [])
        )));
    }

    /** The caption a step's picture carries when the writer gives none: where the learner is in the sequence. */
    public static function caption(string $step): string
    {
        $keys = array_keys(self::STEPS);
        $i = (int) array_search($step, $keys, true);

        return 'Step ' . ($i + 1) . ' of ' . count($keys) . ': ' . self::STEPS[$step] . '.';
    }
}
