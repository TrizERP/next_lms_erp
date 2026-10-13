<?php

namespace App\Services\QuestionGeneration\Formats;

/**
 * Prove / show that -- a short deductive argument, 4-6 marks.
 *
 * Catalogue: lms_question_type_id 2, default_marks 5. Written-answer storage like the
 * other narrative formats: the model answer IS the proof, and each marking point is one
 * step the student must justify. Plays as the H5P map already says for `proof`: Course
 * presentation (the statement to prove, the worked proof in the slide notes), shown as
 * the closest built type because H5P.InteractiveBook does not exist in this platform.
 */
class ProofFormat extends AbstractNarrativeFormat
{
    protected const BATCH_SIZE = 3;

    public function code(): string
    {
        return 'proof';
    }

    public function label(): string
    {
        return 'Proof';
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
        // A proof applies a known result, analyses why it holds, or evaluates an argument.
        return ['Apply', 'Analyze', 'Evaluate'];
    }

    protected function modelAnswerWords(): array
    {
        return [60, 200];
    }

    protected function lengthGuidance(): string
    {
        return 'A proof item asks the student to prove or show that a stated result holds, using only results in the concept slice. The model answer is the complete argument, one justified step after another; each marking point is one step. Worth 4 to 6 marks.';
    }

    protected function commandVerbHint(): string
    {
        return 'Use "Prove that", "Show that" or "Verify that". Apply: Show that / Verify. Analyze: Prove that / Justify why. Evaluate: Prove or disprove / Assess whether.';
    }

    protected function exampleRow(): string
    {
        return <<<'EX'
{
  "question_title": "Prove that the product of two negative integers is a positive integer.",
  "description": "Tests the sign rule at Analyze, 4 marks; common error: assuming the rule without justification.",
  "subconcept": "<exact knowledge string>",
  "points": 4, "multiple_answer": 0, "hint_text": "Start from a product you already know is zero.",
  "learning_outcome": ["<exact outcome>"],
  "answer": {
    "v": "ans-2.0", "question_type": "proof", "sub_type": "Proof",
    "bloom_level": "Analyze", "dok_level": 3, "difficulty": "Hard", "stimulus": null,
    "estimated_time_seconds": 360,
    "model_answer": "Let a and b be positive integers, so -a and -b are negative integers. We know that (-a) x 0 = 0. Write 0 as b + (-b). Then (-a) x (b + (-b)) = 0. By the distributive property, (-a) x b + (-a) x (-b) = 0. Since (-a) x b = -(ab), this gives -(ab) + (-a) x (-b) = 0. The additive inverse of -(ab) is ab, so (-a) x (-b) = ab, which is positive. Hence the result holds.",
    "marking_points": [
      {"mark": 1, "criterion": "Sets up the product with zero written as b + (-b)", "accept": ["0 = b + (-b)", "uses zero as a sum"], "reject": ["assumes the result"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Applies the distributive property correctly", "accept": ["distributes -a over the sum"], "reject": ["adds instead of multiplying"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Uses (-a) x b = -(ab) for the unlike-signs product", "accept": ["negative times positive is negative"], "reject": ["states it is positive"], "knowledge_ref": "<exact knowledge>"},
      {"mark": 1, "criterion": "Concludes (-a) x (-b) = ab from the additive inverse", "accept": ["inverse of -(ab) is ab"], "reject": ["concludes without the inverse"], "knowledge_ref": "<exact knowledge>"}
    ],
    "keywords": [{"term": "distributive", "weight": 0.3, "synonyms": ["distribute"]}, {"term": "additive inverse", "weight": 0.3, "synonyms": ["opposite"]}, {"term": "zero", "weight": 0.2, "synonyms": ["0"]}, {"term": "hence", "weight": 0.2, "synonyms": ["therefore"]}],
    "common_errors": [{"misconception_ref": "<exact misconception>", "erroneous_answer": "Two negatives make a positive, so it is true.", "mark_ceiling": 0}],
    "full_credit_threshold": 3,
    "explanation": "The proof builds the product from a known zero and uses the additive inverse to fix its sign.",
    "remediation": "Reteach why the sign rule holds rather than only stating it.",
    "knowledge_refs": ["<exact knowledge>"], "ability_ref": "<exact ability>", "misconception_refs": ["<exact misconception>"]
  }
}
EX;
    }
}
