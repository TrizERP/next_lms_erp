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
        if (!is_array($envelope)) {
            return null;
        }

        $source = $envelope['pairs'] ?? $envelope['interaction_settings']['pairs'] ?? null;
        if (!is_array($source)) {
            return null;
        }

        $pairs = [];
        foreach ($source as $pair) {
            if (!is_array($pair)) {
                continue;
            }

            $left = trim((string) ($pair['left'] ?? $pair[0] ?? ''));
            $right = trim((string) ($pair['right'] ?? $pair[1] ?? ''));

            if ($left !== '' && $right !== '') {
                $pairs[] = ['left' => $left, 'right' => $right];
            }
        }

        return $pairs === [] ? null : $pairs;
    }

    /**
     * `answer.distractors` as a clean list of non-empty strings, or null.
     */
    public static function distractors(mixed $envelope): ?array
    {
        if (!is_array($envelope)) {
            return null;
        }

        $source = $envelope['distractors'] ?? null;
        if (!is_array($source)) {
            $wordBank = $envelope['interaction_settings']['word_bank'] ?? $envelope['interaction_settings']['distractors'] ?? null;
            if (is_array($wordBank)) {
                $blanks = $envelope['interaction_settings']['blanks'] ?? [];
                $answers = is_array($blanks) ? array_map('strtolower', array_map('trim', array_values($blanks))) : [];
                $dist = [];
                foreach ($wordBank as $word) {
                    if (is_string($word) && trim($word) !== '') {
                        $clean = trim($word);
                        if (!in_array(strtolower($clean), $answers, true)) {
                            $dist[] = $clean;
                        }
                    }
                }
                if ($dist !== []) {
                    return $dist;
                }
                $source = $wordBank;
            }
        }

        if (!is_array($source)) {
            return null;
        }

        $words = [];
        foreach ($source as $word) {
            if (is_string($word) && trim($word) !== '') {
                $words[] = trim($word);
            }
        }

        return $words === [] ? null : $words;
    }

    public static function hotspots(mixed $envelope): ?array
    {
        if (!is_array($envelope)) {
            return null;
        }

        $hotspots = $envelope['interaction_settings']['hotspots'] ?? $envelope['hotspots'] ?? null;
        return is_array($hotspots) && $hotspots !== [] ? $hotspots : null;
    }

    public static function imageRef(mixed $envelope): ?array
    {
        if (!is_array($envelope)) {
            return null;
        }

        $imageRef = $envelope['image_ref'] ?? null;
        return is_array($imageRef) && $imageRef !== [] ? $imageRef : null;
    }

    public static function interactionSettings(mixed $envelope): ?array
    {
        if (!is_array($envelope)) {
            return null;
        }

        $settings = $envelope['interaction_settings'] ?? null;
        return is_array($settings) && $settings !== [] ? $settings : null;
    }

    /**
     * The image-based Drag & Drop payload, or null.
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
            'hotspots' => self::hotspots($envelope),
            'image_ref' => self::imageRef($envelope),
            'interaction_settings' => self::interactionSettings($envelope),
        ];
    }
}
