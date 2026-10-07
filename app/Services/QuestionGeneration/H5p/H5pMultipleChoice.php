<?php

namespace App\Services\QuestionGeneration\H5p;

/**
 * H5P Multiple Choice -- a stem, exactly four options, exactly one correct.
 *
 * Stored through the existing MCQ path: `answer.options` (label, text, is_correct,
 * distractor_type, rationale) and `answer.correct_option`, which is what
 * QuestionGenerationService::validateRows() checks for a legacy MCQ row and what
 * buildAnswerRows() turns into `answer_master`. The frontend plays it as H5P Multiple
 * Choice / Single choice set from `question_format_code = mcq`; nothing H5P-specific is
 * stored on the row.
 */
class H5pMultipleChoice extends AbstractH5pContentType
{
    private const LABELS = ['A', 'B', 'C', 'D'];

    public function formatCode(): string
    {
        return 'mcq';
    }

    public function questionType(): string
    {
        return 'mcq';
    }

    public function h5pType(): string
    {
        return 'H5P.MultiChoice';
    }

    public function h5pLabel(): string
    {
        return 'Multiple Choice';
    }

    public function taskLabel(): string
    {
        return 'multiple-choice';
    }

    public function prompt(int $count): string
    {
        return H5pPrompts::render(H5pPrompts::H5P_MULTIPLE_CHOICE_PROMPT, $count);
    }

    public function responseSchema(): string
    {
        return <<<'SCHEMA'
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "type": "object",
  "additionalProperties": false,
  "required": ["questions"],
  "properties": {
    "questions": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["type", "question", "options", "explanation", "hint", "knowledge_refs", "learning_outcome"],
        "properties": {
          "type": { "const": "mcq" },
          "question": { "type": "string", "minLength": 10, "maxLength": 400 },
          "options": {
            "type": "array",
            "minItems": 4,
            "maxItems": 4,
            "items": {
              "type": "object",
              "additionalProperties": false,
              "required": ["text", "is_correct"],
              "properties": {
                "text": { "type": "string", "minLength": 1, "maxLength": 200 },
                "is_correct": { "type": "boolean" }
              }
            }
          },
          "explanation": { "type": "string", "minLength": 30 },
          "hint": { "type": ["string", "null"], "maxLength": 200 },
          "knowledge_refs": { "type": "array", "minItems": 1, "items": { "type": "string" } },
          "learning_outcome": { "type": "array", "minItems": 1, "items": { "type": "string" } }
        }
      }
    }
  }
}
SCHEMA;
    }

    public function validateQuestion(array $q): ?string
    {
        if (($q['type'] ?? null) !== $this->questionType()) {
            return 'type must be "mcq", got "' . (is_scalar($q['type'] ?? null) ? $q['type'] : 'none') . '"';
        }

        $stem = $this->text($q['question'] ?? null);
        if ($stem === '' || mb_strlen($stem) < 10) {
            return 'the question is missing or too short';
        }
        if (mb_strlen($stem) > 400) {
            return 'the question is longer than 400 characters';
        }

        $options = $q['options'] ?? null;
        if (!is_array($options) || !array_is_list($options)) {
            return 'options must be a list of exactly 4 options';
        }
        if (count($options) !== 4) {
            return 'there must be exactly 4 options, got ' . count($options);
        }

        $correct = 0;
        $seen = [];
        foreach ($options as $i => $option) {
            $n = $i + 1;
            if (!is_array($option)) {
                return "option {$n} is not an object";
            }
            $text = $this->text($option['text'] ?? null);
            if ($text === '') {
                return "option {$n} has no text";
            }
            if (mb_strlen($text) > 200) {
                return "option {$n} is longer than 200 characters";
            }
            if (!array_key_exists('is_correct', $option) || !is_bool($option['is_correct'])) {
                return "option {$n} must have a true/false is_correct";
            }
            if (preg_match('/\b(all|none) of (the above|these)\b|\bboth [a-d] and [a-d]\b/i', $text)) {
                return "option {$n} is an \"all/none of the above\" style option";
            }

            $key = $this->fingerprint($text);
            if ($key === '') {
                return "option {$n} has no readable text";
            }
            if (isset($seen[$key])) {
                return 'options ' . ($seen[$key] + 1) . " and {$n} are duplicates";
            }
            $seen[$key] = $i;

            if ($option['is_correct']) {
                $correct++;
            }
        }

        if ($correct === 0) {
            return 'no option is marked correct';
        }
        if ($correct > 1) {
            return "exactly one option must be correct, {$correct} are marked correct";
        }

        return $this->provenanceReason($q);
    }

    public function validateSet(array $questions): ?string
    {
        return $this->duplicateStem(array_map(fn ($q) => $this->text($q['question'] ?? null), $questions));
    }

    public function toRow(array $q, array $slot, string $envelopeVersion): array
    {
        [$row, $answer] = $this->baseRow($q, $slot, $this->text($q['question']), 'mcq', 'MCQ', $envelopeVersion, 60);

        $explanation = $this->text($q['explanation']);
        $options = [];
        $correctLabel = null;

        foreach ($q['options'] as $i => $option) {
            $isCorrect = $option['is_correct'] === true;
            $label = self::LABELS[$i];
            if ($isCorrect) {
                $correctLabel = $label;
            }
            $options[] = [
                'label' => $label,
                'text' => $this->text($option['text']),
                'is_correct' => $isCorrect,
                'distractor_type' => $isCorrect ? 'correct' : 'plausible',
                // The correct option carries the explanation; a distractor carries the
                // model's own note when it wrote one. buildAnswerRows() stores it as feedback.
                'rationale' => $isCorrect ? $explanation : $this->text($option['rationale'] ?? null),
            ];
        }

        $answer['options'] = $options;
        $answer['correct_option'] = $correctLabel;
        $row['answer'] = $answer;

        return $row;
    }
}
