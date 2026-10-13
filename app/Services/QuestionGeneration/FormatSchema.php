<?php

namespace App\Services\QuestionGeneration;

/**
 * Builds the JSON Schema a format hands the model.
 *
 * The wrapper and the row-level columns are identical for every format and
 * mirror the ones the MCQ and narrative schemas have always carried
 * (`{semantic_concept_key, question_type, underfilled, reason, rows[]}`, each row
 * column-shaped for `lms_question_master`). A format supplies only its own
 * `answer` properties and the marks rule, so adding a format cannot drift the
 * envelope the readers depend on.
 */
final class FormatSchema
{
    /**
     * @param  string                $code            the format code the model must echo
     * @param  array<string, mixed>  $answer          format-specific answer properties
     * @param  list<string>          $answerRequired  format-specific required answer keys
     * @param  array<string, mixed>  $points          JSON Schema for the row's `points`
     * @param  int                   $titleMin
     * @param  int                   $titleMax
     * @param  array<string, mixed>  $common          overrides for the common answer properties
     */
    public static function build(
        string $code,
        array $answer,
        array $answerRequired,
        array $points,
        int $titleMin = 10,
        int $titleMax = 400,
        array $common = []
    ): string {
        $answerProperties = $answer + array_replace(self::commonAnswer($code), $common);
        // The required list always carries the common keys, in a stable order.
        $required = array_values(array_unique(array_merge(
            ['v', 'question_type', 'bloom_level', 'dok_level', 'difficulty', 'explanation', 'remediation', 'knowledge_refs', 'ability_ref', 'misconception_refs', 'estimated_time_seconds'],
            $answerRequired
        )));

        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['semantic_concept_key', 'question_type', 'rows'],
            'properties' => [
                'semantic_concept_key' => ['type' => 'string'],
                'question_type' => ['const' => $code],
                'underfilled' => ['type' => 'boolean'],
                'reason' => ['type' => ['string', 'null']],
                'rows' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['question_title', 'description', 'subconcept', 'points', 'multiple_answer', 'answer', 'hint_text', 'learning_outcome'],
                        'properties' => [
                            'question_title' => ['type' => 'string', 'minLength' => $titleMin, 'maxLength' => $titleMax],
                            'description' => ['type' => 'string', 'minLength' => 10, 'maxLength' => 240],
                            'subconcept' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 240],
                            'points' => $points,
                            'multiple_answer' => ['const' => 0],
                            'hint_text' => ['type' => ['string', 'null'], 'maxLength' => 200],
                            'learning_outcome' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                            'answer' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => $required,
                                'properties' => $answerProperties,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** The answer properties every format shares. */
    public static function commonAnswer(string $code): array
    {
        return [
            'v' => ['const' => 'ans-2.0'],
            'question_type' => ['const' => $code],
            'competency_ref' => ['type' => ['string', 'null']],
            'bloom_level' => ['enum' => ['Remember', 'Understand', 'Apply', 'Analyze', 'Evaluate', 'Create']],
            'dok_level' => ['enum' => [1, 2, 3, 4]],
            'difficulty' => ['enum' => ['Easy', 'Medium', 'Hard']],
            'stimulus' => ['type' => ['string', 'null'], 'maxLength' => 700],
            'estimated_time_seconds' => ['type' => 'integer', 'minimum' => 15, 'maximum' => 900],
            'explanation' => ['type' => 'string', 'minLength' => 30],
            'remediation' => ['type' => 'string', 'minLength' => 10],
            'knowledge_refs' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
            'ability_ref' => ['type' => ['string', 'null']],
            'misconception_refs' => ['type' => 'array', 'items' => ['type' => 'string']],
        ];
    }
}
