<?php

namespace App\Services\QuestionGeneration;

/**
 * Reads of the `lms_question_master.answer` envelope that more than one reader
 * has to agree on.
 *
 * The structured fields a format stores beyond the text answer -- match-the-following
 * `pairs`, drag-the-words `distractors` -- are exposed to the client by three readers
 * (getQuestionBank, ApiQuestionBankController::shape, PalQuestionForms::describe), and the
 * client prefers them to parsing text, so the three must normalise them identically.
 * clientFields() is that one projection: a reader spreads it into its row instead of naming
 * each field, so a format that stores a new structured field is exposed by changing this
 * class alone, not three controllers.
 */
final class QuestionEnvelope
{
    /**
     * `answer.pairs` as a clean list of {left, right} strings, or null.
     *
     * Null (not an empty list) when the envelope carries no usable pairs, so the
     * client can tell "this row has none" from "this row has them" and fall back
     * to parsing the model answer for the extracted rows that predate the field.
     * A pair with an empty half is dropped rather than repaired.
     *
     * @param  mixed  $envelope  a decoded envelope
     * @return array<int, array{left: string, right: string}>|null
     */
    public static function pairs(mixed $envelope): ?array
    {
        if (!is_array($envelope) || !isset($envelope['pairs']) || !is_array($envelope['pairs'])) {
            return null;
        }

        $pairs = [];
        foreach ($envelope['pairs'] as $pair) {
            if (!is_array($pair)) {
                continue;
            }

            $left = trim((string) ($pair['left'] ?? ''));
            $right = trim((string) ($pair['right'] ?? ''));

            if ($left !== '' && $right !== '') {
                $pairs[] = ['left' => $left, 'right' => $right];
            }
        }

        return $pairs === [] ? null : $pairs;
    }

    /**
     * `answer.distractors` as a clean list of non-empty strings, or null.
     *
     * Null (not an empty list) when the envelope carries none, so the client can tell a
     * row that never had a word bank from one that has one.
     *
     * @param  mixed  $envelope  a decoded envelope
     * @return list<string>|null
     */
    public static function distractors(mixed $envelope): ?array
    {
        if (!is_array($envelope) || !isset($envelope['distractors']) || !is_array($envelope['distractors'])) {
            return null;
        }

        $words = [];
        foreach ($envelope['distractors'] as $word) {
            if (is_string($word) && trim($word) !== '') {
                $words[] = trim($word);
            }
        }

        return $words === [] ? null : $words;
    }

    /**
     * Every structured, client-facing field the envelope can carry, keyed as the API row
     * names them. Spread into a reader's row: `...QuestionEnvelope::clientFields($envelope)`.
     *
     * @param  mixed  $envelope  a decoded envelope
     * @return array<string, mixed>
     */
    /**
     * The image-based Drag & Drop payload, or null.
     *
     * Passed on only when it is a playable one (the same rules the generator enforced),
     * so a damaged row reaches the player as "no drag_drop" and is reported as
     * unplayable, rather than as a canvas with zones off the picture.
     */
    public static function dragDrop(mixed $envelope): ?array
    {
        if (!is_array($envelope) || !is_array($envelope['drag_drop'] ?? null)) {
            return null;
        }

        return (new DragDrop\DragDropGeometry())->reason($envelope['drag_drop']) === null
            ? $envelope['drag_drop']
            : null;
    }

    public static function clientFields(mixed $envelope): array
    {
        return [
            'drag_drop' => self::dragDrop($envelope),
            'pairs' => self::pairs($envelope),
            'distractors' => self::distractors($envelope),
        ];
    }
}
