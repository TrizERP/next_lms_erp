<?php

namespace App\Services\QuestionGeneration\Formats;

/**
 * Long answer -- a developed response of a paragraph or more, 4-6 marks.
 *
 * Catalogue: lms_question_type_id 2, default_marks 5. Plays as the written-answer
 * player, Flash cards and Course presentation; a long answer is prose, so nothing
 * that marks a string applies.
 */
class LongFormat extends AbstractNarrativeFormat
{
    protected const BATCH_SIZE = 3;

    public function code(): string
    {
        return 'long';
    }

    public function label(): string
    {
        return 'Long Answer';
    }

    public function defaultMarks(): int
    {
        return 5;
    }

    public function marksRange(): array
    {
        return [4, 6];
    }

    public function allowedBloomLevels(): array
    {
        return ['Apply', 'Analyze', 'Evaluate', 'Create'];
    }

    protected function modelAnswerWords(): array
    {
        return [80, 220];
    }

    protected function lengthGuidance(): string
    {
        return 'A long answer is a developed response -- a short paragraph or a structured set of points -- giving four to six creditable elements, worth 4 to 6 marks.';
    }

    protected function commandVerbHint(): string
    {
        return 'Apply: Apply / Determine. Analyze: Analyse / Compare / Examine / Justify why. Evaluate: Evaluate / Assess / Critique / Argue whether. Create: Design / Propose / Construct.';
    }

    protected function exampleRow(): string
    {
        return <<<'EX'
{
  "question_title": "A student claims that multiplying any two integers gives a positive result. Evaluate the claim using the rule for the sign of a product, and give examples for each combination of signs.",
  "description": "Tests the sign rule at Evaluate, 4 marks; common error: all products positive.",
  "subconcept": "<exact knowledge string>",
  "points": 4, "multiple_answer": 0, "hint_text": "Try one example for each pair of signs.",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "long", "sub_type": "Long Answer",
    "bloom_level": "Evaluate", "dok_level": 3, "difficulty": "Hard", "stimulus": null,
    "estimated_time_seconds": 300,
    "model_answer": "The claim is incorrect. A product of two positive integers is positive, for example 3 x 4 = 12. A product of two negative integers is also positive, for example (-3) x (-4) = 12. However, a product of integers with unlike signs is negative, for example 3 x (-4) = -12 and (-3) x 4 = -12. The result depends on whether the signs are alike or unlike, so not every product is positive.",
    "marking_points": [
      {"mark": 1, "criterion": "States that the claim is incorrect", "accept": ["claim is false", "not always positive"], "reject": ["claim is correct"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Shows that like signs give a positive product with an example", "accept": ["3 x 4 = 12", "(-3) x (-4) = 12"], "reject": ["any like-sign example giving a negative"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Shows that unlike signs give a negative product with an example", "accept": ["3 x (-4) = -12"], "reject": ["3 x (-4) = 12"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Concludes that the sign depends on whether the signs are alike or unlike", "accept": ["alike or unlike"], "reject": ["depends on size"], "knowledge_ref": "<exact knowledge>"}
    ],
    "keywords": [{"term": "unlike signs", "weight": 0.3, "synonyms": ["different signs"]}, {"term": "negative", "weight": 0.3, "synonyms": ["below zero"]}, {"term": "like signs", "weight": 0.2, "synonyms": ["same signs"]}, {"term": "alike or unlike", "weight": 0.1, "synonyms": []}, {"term": "depends", "weight": 0.1, "synonyms": ["determined by"]}],
    "common_errors": [{"misconception_ref": "<exact misconception>", "erroneous_answer": "Every product is positive.", "mark_ceiling": 1}],
    "full_credit_threshold": 3,
    "explanation": "The sign of a product is decided by whether the factors' signs are alike or unlike.",
    "remediation": "Reteach the four sign combinations with one example each.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": "<exact ability>", "misconception_refs": ["<exact misconception>"]
  }
}
EX;
    }
}
