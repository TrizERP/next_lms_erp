<?php

namespace App\Services\QuestionGeneration\Formats;

/**
 * Short answer -- two to four sentences, 2-3 marks.
 *
 * Catalogue: lms_question_type_id 2, default_marks 3. Plays as the written-answer
 * player; a row whose model answer is a short phrase also plays as Fill in the
 * blanks / Flash cards via the H5P map.
 */
class ShortFormat extends AbstractNarrativeFormat
{
    protected const BATCH_SIZE = 3;

    public function code(): string
    {
        return 'short';
    }

    public function label(): string
    {
        return 'Short Answer';
    }

    public function defaultMarks(): int
    {
        return 3;
    }

    public function marksRange(): array
    {
        return [2, 3];
    }

    public function allowedBloomLevels(): array
    {
        return ['Understand', 'Apply', 'Analyze'];
    }

    protected function modelAnswerWords(): array
    {
        return [20, 80];
    }

    protected function lengthGuidance(): string
    {
        return 'A short answer is two to four sentences giving two or three creditable points, worth 2 or 3 marks.';
    }

    protected function commandVerbHint(): string
    {
        return 'Understand: Explain / Describe / Differentiate / Interpret. Apply: Apply / Calculate / Predict / Determine. Analyze: Analyse / Compare / Examine.';
    }

    protected function exampleRow(): string
    {
        return <<<'EX'
{
  "question_title": "A diver descends 3 m every minute. Explain why her change in depth after 4 minutes is -12 m and not +12 m.",
  "description": "Tests multiplication with unlike signs at Apply, 2 marks; common error: sign dropped.",
  "subconcept": "<exact knowledge string>",
  "points": 2, "multiple_answer": 0, "hint_text": "Which direction is a descent?",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "short", "sub_type": "Short Answer",
    "bloom_level": "Apply", "dok_level": 2, "difficulty": "Medium", "stimulus": null,
    "estimated_time_seconds": 120,
    "model_answer": "A descent is a change in the negative direction, so each minute contributes -3 m. Multiplying 4 by -3 gives -12 m, a negative product because the signs are unlike.",
    "marking_points": [
      {"mark": 1, "criterion": "Treats the descent as a negative change", "accept": ["-3 m per minute", "negative direction"], "reject": ["3 m per minute positive"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Concludes the product 4 x (-3) is negative", "accept": ["-12", "negative product"], "reject": ["+12"], "knowledge_ref": "<exact knowledge>"}
    ],
    "keywords": [{"term": "negative", "weight": 0.4, "synonyms": ["below zero"]}, {"term": "unlike signs", "weight": 0.3, "synonyms": []}, {"term": "product", "weight": 0.2, "synonyms": ["result"]}, {"term": "direction", "weight": 0.1, "synonyms": []}],
    "common_errors": [{"misconception_ref": "<exact misconception>", "erroneous_answer": "The change is +12 m.", "mark_ceiling": 0}],
    "full_credit_threshold": 2,
    "explanation": "A descent counts as a negative change, and a positive count times a negative change is negative.",
    "remediation": "Reteach that unlike signs give a negative product.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": "<exact ability>", "misconception_refs": ["<exact misconception>"]
  }
}
EX;
    }
}
