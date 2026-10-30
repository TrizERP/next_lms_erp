<?php

namespace App\Services\QuestionPapers;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Support\FallbackChainModelClient;
use RuntimeException;

/**
 * Reads the questions out of a block of question-paper text with the school's configured
 * Document Intelligence provider chain (the same one the document classifier uses).
 *
 * The model transcribes and translates non-English content to English. It leaves absent fields null;
 * QuestionPaperItemNormaliser validates every returned question before it can enter the question bank.
 */
class QuestionPaperParser
{
    private ?FallbackChainModelClient $chain = null;

    private int|string|null $tenant = null;

    /**
     * @param  list<array{page: int|null, text: string}>  $pages
     * @return array{header: array<string, string|null>, questions: list<array<string, mixed>>}
     */
    public function parseChunk(array $pages, int $tenant): array
    {
        $client = $this->client($tenant);
        if (!$client->isConfigured()) {
            throw new PermanentQuestionPaperException(
                'No AI provider is configured for Document Intelligence, so the questions cannot be read. Configure one in AI Providers, then retry.',
                true
            );
        }

        $body = '';
        foreach ($pages as $page) {
            $body .= '[[PAGE ' . ($page['page'] ?? '?') . "]]\n" . $page['text'] . "\n\n";
        }

        $result = $client->json(
            [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => "Question paper text:\n\n" . $body],
            ],
            null,
            8000,
            0.0
        );

        // A null result is a provider/transport problem, not a verdict on the paper: retry.
        if (!is_array($result)) {
            throw new RuntimeException('The AI provider returned no usable response for this section.');
        }

        $questions = $result['questions'] ?? null;
        if (!is_array($questions)) {
            throw new RuntimeException('The AI response did not contain a questions list.');
        }

        $header = [];
        foreach (['standard', 'subject', 'chapter', 'academic_year'] as $key) {
            $value = $result['header'][$key] ?? null;
            $header[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return [
            'header' => $header,
            'questions' => array_values(array_filter($questions, 'is_array')),
        ];
    }

    private function client(int $tenant): FallbackChainModelClient
    {
        if ($this->chain === null || $this->tenant !== $tenant) {
            $this->chain = app(AiModelClientFactory::class)->for('document_intelligence', $tenant);
            $this->tenant = $tenant;
        }

        return $this->chain;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You extract questions from a school question paper into strict JSON and return ONE JSON object.

{
  "header": {"standard": string|null, "subject": string|null, "chapter": string|null, "academic_year": string|null},
  "questions": [
    {
      "item_number": string|null,   // as printed, e.g. "3", "Q.4", "5(a)"
      "page": integer|null,         // from the [[PAGE n]] marker the question starts on
      "format": "mcq"|"true_false"|"fill_blank"|"match_following"|"assertion_reason"|"numerical"|"very_short"|"short"|"long"|"case_study"|"proof"|"construction"|"other",
      "question_text": string,      // faithfully translated into English when printed in another language
      "chapter": string|null,        // explicit chapter heading governing this question, else null
      "options": [{"label": "A", "text": string}],   // only for printed options; text is in English
      "correct_option": string|null,                // option label, ONLY if the paper prints an answer key
      "answer_text": string|null,                   // printed model answer / true-false verdict in English, else null
      "explanation": string|null,                   // printed explanation in English, else null
      "marks": integer|null,                        // printed marks, else null
      "pairs": [{"left": string, "right": string}]|null   // match-the-following only
    }
  ]
}

Rules:
- Output all student-facing text in English. Faithfully translate question text, option text, printed answers,
  explanations, matching-pair text and explicit chapter headings when the source is in another language.
- If the paper already includes an English version, use that version rather than translating it again.
- Translation must preserve meaning and difficulty. Do not solve, rewrite, correct, summarize or improve a question.
- Preserve names, numbers, units, formulas, symbols, option labels and item numbering.
- NEVER invent an answer, a correct option, an explanation, marks, a standard, subject or chapter.
  If the paper does not print it, use null.
- "header" comes only from titles/headings that state it (e.g. "Class 9 Science, Chapter 1: Matter").
- Set a question's "chapter" only when a printed chapter title explicitly governs that question; otherwise null.
- Choose "format" from what the question is: lettered options -> mcq; a statement to mark true or false ->
  true_false; blanks -> fill_blank; two columns to match -> match_following; Assertion/Reason -> assertion_reason;
  otherwise by expected answer length: very_short, short, long. Use "other" if none fits.
- Skip instructions, headings, page numbers and section titles that are not questions.
- A question that continues across a [[PAGE]] marker is one question.
- If the section has no questions, return {"header": {...}, "questions": []}.
Return JSON only.
PROMPT;
    }
}
