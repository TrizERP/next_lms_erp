<?php

namespace App\Services\QuestionGeneration\Formats;

/**
 * Very short answer -- a word, a value or one short sentence. 1-2 marks.
 *
 * Catalogue: lms_question_type_id 2, default_marks 2. A row whose model answer is
 * six words or fewer also plays as Fill in the blanks / Drag / Mark the words via
 * the H5P map; longer ones play as the written-answer player.
 */
class VeryShortFormat extends AbstractNarrativeFormat
{
    protected const BATCH_SIZE = 5;

    public function code(): string
    {
        return 'very_short';
    }

    public function label(): string
    {
        return 'Very Short Answer';
    }

    public function defaultMarks(): int
    {
        return 2;
    }

    public function marksRange(): array
    {
        return [1, 2];
    }

    public function allowedBloomLevels(): array
    {
        return ['Remember', 'Understand', 'Apply'];
    }

    protected function modelAnswerWords(): array
    {
        return [1, 25];
    }

    protected function lengthGuidance(): string
    {
        return 'A very short answer is a word, a value or a single short sentence, worth 1 or 2 marks.';
    }

    protected function commandVerbHint(): string
    {
        return 'Remember: State / List / Define / Name. Understand: Explain briefly / Differentiate. Apply: Calculate / Identify.';
    }

    protected function exampleRow(): string
    {
        return <<<'EX'
{
  "question_title": "State the sign of the product of a positive integer and a negative integer.",
  "description": "Tests the sign rule at Remember, 1 mark; common error: product taken as positive.",
  "subconcept": "<exact knowledge string>",
  "points": 1, "multiple_answer": 0, "hint_text": null,
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "very_short", "sub_type": "Very Short Answer",
    "bloom_level": "Remember", "dok_level": 1, "difficulty": "Easy", "stimulus": null,
    "estimated_time_seconds": 45,
    "model_answer": "The product is negative.",
    "marking_points": [{"mark": 1, "criterion": "States that the product is negative", "accept": ["negative", "less than zero"], "reject": ["positive"], "knowledge_ref": "<exact knowledge>"}],
    "keywords": [{"term": "below zero", "weight": 0.5, "synonyms": ["less than zero"]}, {"term": "unlike signs", "weight": 0.2, "synonyms": []}, {"term": "minus", "weight": 0.2, "synonyms": []}, {"term": "result", "weight": 0.1, "synonyms": ["answer"]}],
    "common_errors": [{"misconception_ref": "<exact misconception>", "erroneous_answer": "The product is positive.", "mark_ceiling": 0}],
    "full_credit_threshold": 1,
    "explanation": "A positive number multiplied by a negative number always gives a negative number.",
    "remediation": "Reteach the sign rule for unlike signs.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": null, "misconception_refs": ["<exact misconception>"]
  }
}
EX;
    }
}
