<?php

namespace App\Services\QuestionGeneration\H5p;

/**
 * H5P True/False -- one statement and a verdict.
 *
 * Handed to TrueFalseFormat as the row it already knows: the statement is
 * `question_title`, the verdict is the bare word `answer.model_answer`, and
 * TrueFalseFormat::prepareRow() adds the two True/False options, `correct_option` and
 * `statement_truth` that the MCQ-path readers and answer_master need. Nothing here
 * duplicates that.
 */
class H5pTrueFalse extends AbstractH5pContentType
{
    /** A statement that opens with one of these is a question, whatever its punctuation. */
    private const QUESTION_OPENERS = [
        'what', 'which', 'who', 'whom', 'whose', 'why',
        'is', 'are', 'am', 'was', 'were', 'do', 'does', 'did',
        'can', 'could', 'will', 'would', 'shall', 'should', 'has', 'have', 'had',
    ];

    /** Words that hand the learner the verdict (specific determiners). */
    private const GIVEAWAYS = ['always', 'never', 'none', 'entirely', 'completely', 'absolutely', 'impossible'];

    public function formatCode(): string
    {
        return 'true_false';
    }

    public function questionType(): string
    {
        return 'true_false';
    }

    public function h5pType(): string
    {
        return 'H5P.TrueFalse';
    }

    public function h5pLabel(): string
    {
        return 'True/False';
    }

    public function taskLabel(): string
    {
        return 'true/false';
    }

    public function prompt(int $count): string
    {
        return H5pPrompts::render(H5pPrompts::H5P_TRUE_FALSE_PROMPT, $count);
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
        "required": ["type", "statement", "answer", "explanation", "hint", "knowledge_refs", "learning_outcome"],
        "properties": {
          "type": { "const": "true_false" },
          "statement": { "type": "string", "minLength": 10, "maxLength": 400 },
          "answer": { "type": "boolean" },
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
            return 'type must be "true_false", got "' . (is_scalar($q['type'] ?? null) ? $q['type'] : 'none') . '"';
        }

        $statement = $this->text($q['statement'] ?? null);
        if ($statement === '' || mb_strlen($statement) < 10) {
            return 'the statement is missing or too short';
        }
        if (str_ends_with($statement, '?') || $this->opensWithQuestionWord($statement)) {
            return 'a true/false item must be a statement, not a question';
        }
        if ($this->words($statement) > 45) {
            return 'the statement is longer than 45 words';
        }

        if (!array_key_exists('answer', $q) || !is_bool($q['answer'])) {
            return 'answer must be the JSON boolean true or false';
        }

        if ($this->negations($statement) > 1) {
            return 'the statement uses more than one negative word, which risks a double negative';
        }
        foreach (self::GIVEAWAYS as $word) {
            if (preg_match('/\b' . $word . '\b/i', $statement)) {
                return "the statement contains \"{$word}\", which gives the verdict away";
            }
        }

        $reason = $this->provenanceReason($q);
        if ($reason !== null) {
            return $reason;
        }

        return $this->verdictContradiction($q['answer'], $this->text($q['explanation']));
    }

    public function validateSet(array $questions): ?string
    {
        $duplicate = $this->duplicateStem(array_map(fn ($q) => $this->text($q['statement'] ?? null), $questions));
        if ($duplicate !== null) {
            return $duplicate;
        }

        // Several statements may not all carry one verdict: at most half, rounded up.
        $total = count($questions);
        if ($total >= 2) {
            $true = count(array_filter($questions, fn ($q) => ($q['answer'] ?? null) === true));
            $limit = (int) ceil($total / 2);
            if ($true > $limit || ($total - $true) > $limit) {
                return 'the statements do not mix True and False: at most ' . $limit . ' of ' . $total . ' may share a verdict';
            }
        }

        return null;
    }

    public function toRow(array $q, array $slot, string $envelopeVersion): array
    {
        [$row, $answer] = $this->baseRow($q, $slot, $this->text($q['statement']), 'true_false', 'True/False', $envelopeVersion, 30);

        $answer['model_answer'] = $q['answer'] === true ? 'True' : 'False';
        $row['answer'] = $answer;

        return $row;
    }

    private function opensWithQuestionWord(string $statement): bool
    {
        $first = mb_strtolower((string) (preg_split('/[\s,]+/u', ltrim($statement, "\"'([ "), 2)[0] ?? ''));

        return in_array($first, self::QUESTION_OPENERS, true);
    }

    private function negations(string $statement): int
    {
        return (int) preg_match_all('/\b(not|no|never|neither|nor|none|cannot|without)\b|n\'t\b/i', $statement);
    }

    /** An explanation that argues the opposite of the stated verdict. */
    private function verdictContradiction(bool $verdict, string $explanation): ?string
    {
        $says = static fn (string $pattern): bool => (bool) preg_match($pattern, $explanation);

        $claimsFalse = $says('/^\s*(false|incorrect|no)\b/i')
            || $says('/\b(this|the|given)\s+(statement|claim)\s+is\s+(false|incorrect|wrong|not true)\b/i');
        $claimsTrue = $says('/^\s*(true|correct|yes)\b/i')
            || $says('/\b(this|the|given)\s+(statement|claim)\s+is\s+(true|correct)\b/i');

        if ($verdict && $claimsFalse) {
            return 'the explanation says the statement is false but the answer is true';
        }
        if (!$verdict && $claimsTrue) {
            return 'the explanation says the statement is true but the answer is false';
        }

        return null;
    }
}
