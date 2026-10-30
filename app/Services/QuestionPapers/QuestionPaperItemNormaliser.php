<?php

namespace App\Services\QuestionPapers;

use App\Services\QuestionGeneration\QuestionFormatRegistry;

/**
 * Turns one question as the parser read it into the shape the question bank stores, or says why it
 * cannot be stored. Pure: no database, no model.
 *
 * Nothing is invented. A missing answer key stays missing and is recorded as a review reason; the
 * persistence layer then stores the question inactive instead of guessing a correct option.
 */
class QuestionPaperItemNormaliser
{
    /** Formats whose answer is one of a fixed set of options. */
    private const OBJECTIVE = ['mcq', 'true_false', 'assertion_reason'];

    public function __construct(private ?QuestionFormatRegistry $registry = null)
    {
        $this->registry ??= new QuestionFormatRegistry();
    }

    /** @return array{ok: bool, item?: array<string, mixed>, reason?: string} */
    public function normalise(array $raw): array
    {
        $text = $this->clean($raw['question_text'] ?? '');
        if (mb_strlen($text) < 5) {
            return ['ok' => false, 'reason' => 'No question text could be read.'];
        }

        $code = strtolower(trim((string) ($raw['format'] ?? '')));
        $format = $this->registry->get($code);
        // Same rule the generator applies: the legacy narrative alias is not a catalogue form (mcq is legacy but is one).
        if ($format === null || ($format->isLegacy() && $format->persistedFormatCode() === null)) {
            return ['ok' => false, 'reason' => 'Question format "' . ($code ?: 'unknown') . '" is not supported by the question bank.'];
        }
        $code = $format->code();

        $review = [];
        $options = $this->options($raw['options'] ?? null);
        $answerText = $this->clean($raw['answer_text'] ?? '');
        $correct = null;
        $pairs = null;

        if ($code === 'true_false') {
            // The bank stores true/false as two options; the verdict must come from the source.
            $verdict = $this->verdict($answerText !== '' ? $answerText : (string) ($raw['correct_option'] ?? ''));
            $options = [['label' => 'A', 'text' => 'True', 'is_correct' => $verdict === true], ['label' => 'B', 'text' => 'False', 'is_correct' => $verdict === false]];
            $correct = $verdict === null ? null : ($verdict ? 'A' : 'B');
        } elseif (in_array($code, self::OBJECTIVE, true)) {
            if (count($options) < 2) {
                return ['ok' => false, 'reason' => 'A multiple-choice question needs at least two options; found ' . count($options) . '.'];
            }
            $labels = array_column($options, 'label');
            if (count($labels) !== count(array_unique($labels))) {
                return ['ok' => false, 'reason' => 'Option labels are repeated, so the options cannot be told apart.'];
            }
            $given = strtoupper(trim((string) ($raw['correct_option'] ?? '')));
            $correct = in_array($given, $labels, true) ? $given : null;
            foreach ($options as &$option) {
                $option['is_correct'] = $correct !== null && $option['label'] === $correct;
            }
            unset($option);
        } elseif ($code === 'match_following') {
            $pairs = $this->pairs($raw['pairs'] ?? null);
            if (count($pairs) < 2) {
                return ['ok' => false, 'reason' => 'A match-the-following question needs at least two pairs.'];
            }
            $options = [];
        } else {
            // Descriptive and fill-in formats carry no fixed options.
            $options = [];
        }

        if (in_array($code, self::OBJECTIVE, true) && $correct === null) {
            $review[] = 'Correct answer is not given in the source document.';
        }

        $marks = (int) ($raw['marks'] ?? 0);
        [$min, $max] = $format->marksRange();
        if ($marks < $min || $marks > $max) {
            $marks = $this->registry->defaultMarksFor($format);
        }

        $item = [
            'item_number' => mb_substr(trim((string) ($raw['item_number'] ?? '')), 0, 16) ?: null,
            'source_page' => isset($raw['page']) && is_numeric($raw['page']) ? max(1, (int) $raw['page']) : null,
            'format_code' => $code,
            'question_text' => $text,
            'chapter_text' => ($chapter = $this->clean($raw['chapter'] ?? '')) !== '' ? mb_substr($chapter, 0, 250) : null,
            'options' => $options,
            'correct_option' => $correct,
            'model_answer' => in_array($code, self::OBJECTIVE, true) ? null : ($answerText !== '' ? $answerText : null),
            'explanation' => ($e = $this->clean($raw['explanation'] ?? '')) !== '' ? $e : null,
            'pairs' => $pairs,
            'marks' => $marks,
            'review' => $review,
        ];
        $item['content_hash'] = $this->hash($item);

        return ['ok' => true, 'item' => $item];
    }

    /** Same words, same format, same options give the same hash, whatever the page or numbering. */
    public function hash(array $item): string
    {
        $parts = [
            $this->fold($item['question_text']),
            $item['format_code'],
            implode('|', array_map(fn ($o) => $this->fold($o['text']), $item['options'] ?? [])),
            implode('|', array_map(fn ($p) => $this->fold($p['left'] . '>' . $p['right']), $item['pairs'] ?? [])),
        ];

        return hash('sha256', implode("\n", $parts));
    }

    private function fold(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N} ]/u', '', mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)))) ?? '';
    }

    private function clean(mixed $value): string
    {
        return is_scalar($value) ? trim(preg_replace('/[ \t]+/u', ' ', (string) $value)) : '';
    }

    /** @return list<array{label: string, text: string, is_correct: bool}> */
    private function options(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach (array_values($raw) as $i => $option) {
            if (!is_array($option)) {
                continue;
            }
            $text = $this->clean($option['text'] ?? '');
            if ($text === '') {
                continue;
            }
            $label = strtoupper(trim((string) ($option['label'] ?? '')));
            $out[] = ['label' => $label !== '' ? $label : chr(65 + $i), 'text' => $text, 'is_correct' => false];
        }

        return array_slice($out, 0, 8);
    }

    /** @return list<array{left: string, right: string}> */
    private function pairs(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $pair) {
            $left = $this->clean($pair['left'] ?? '');
            $right = $this->clean($pair['right'] ?? '');
            if ($left !== '' && $right !== '') {
                $out[] = ['left' => $left, 'right' => $right];
            }
        }

        return $out;
    }

    private function verdict(string $value): ?bool
    {
        return match (strtolower(trim($value, " .\t\n"))) {
            'true', 't' => true,
            'false', 'f' => false,
            default => null,
        };
    }
}
