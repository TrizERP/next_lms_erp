<?php

namespace App\Services\StudyDeck\Branching\Pro;

/**
 * What the pro Branching Scenario format is, in one place.
 *
 * It is a deck of ordinary Study Deck V3 slides, which the student player (lms_k12 components/study-deck) already draws:
 *
 *   explore   a `hotspots` slide: a picture with parts to open                         (SlideView: visual-hotspots)
 *   decide    a `decision` slide: a question over a full-screen picture; every answer opens its own slide
 *   outcome   one `outcome` slide per answer: what happens, why, and the ways on     (`tone` sound | caution)
 *   flow      a `flow` slide: the sound path told as a short guided sequence
 *
 * A slide names where "Continue" goes with `next` (-1: Continue waits for the learner to choose). The deck carries
 * `theme: "professional"`, which is what makes the player draw decisions and outcomes full-bleed.
 *
 * This is NOT the older single-slide story format (`interaction.kind = "scenario"` with `template = story_branching_scenario`),
 * which keeps working for every chapter the gate does not name.
 */
final class ProScenarioFormat
{
    public const THEME = 'professional';

    /** The interaction kinds that make a deck a pro scenario (a deck with none of them is not one). */
    public const KINDS = ['decision', 'outcome'];

    public const TONE_SOUND = 'sound';

    public const TONE_CAUTION = 'caution';

    /** Parts on one picture (the player needs two; more than five crowd a slide). */
    public const MIN_SPOTS = 2;

    public const MAX_SPOTS = 5;

    /** Answers to one decision: two to four, three is the sample's shape. */
    public const MIN_OPTIONS = 2;

    public const MAX_OPTIONS = 4;

    /** Slide numbers `next` may take besides a slide: Continue waits for the learner's choice. */
    public const WAIT = -1;

    /** True for a deck whose lesson is made of explore / decide / outcome slides. */
    public static function isPro(array $deck): bool
    {
        foreach ((array) ($deck['slides'] ?? []) as $s) {
            if (in_array($s['interaction']['kind'] ?? null, self::KINDS, true)) {
                return true;
            }
        }

        return false;
    }
}
