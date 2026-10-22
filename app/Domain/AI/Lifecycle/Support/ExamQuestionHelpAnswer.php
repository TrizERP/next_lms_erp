<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageOutcome;
use App\Domain\AI\Support\ModelClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What the assistant tells a student who is stuck on an exam question: the answer, and why, briefly.
 *
 * The answer is NEVER written by a model. It is the option(s) marked correct in `answer_master`, read
 * by `exam_assessment.question_help`, and printed as the headline. A model is used for one thing only:
 * one or two simple sentences saying why. Its sentence is discarded unless it names the key's correct
 * option, and the teacher's own explanation (`hint_text`) is used instead; if there is none, the
 * headline stands alone. So the answer is the same on every run, whatever the model does.
 *
 * Where the teacher has chosen to hide answers (`question_paper.result_show_ans`), the headline says so
 * and the student gets a short nudge that names no option and passes no verdict on one.
 */
class ExamQuestionHelpAnswer
{
    private const MODULE = 'conversational_ai';

    // Generous on purpose: Gemini spends output tokens on reasoning before it writes, and a small ceiling
    // returns a few words of text cut off mid-sentence (measured: 600 left ~25 tokens of answer).
    private const MAX_TOKENS = 2000;

    public function __construct(
        private readonly AnswerComposer $compose,
        private readonly ModelClient $client,
        private readonly ?AiModelClientFactory $clients = null,
    ) {
    }

    public function compose(StageContext $context): StageOutcome
    {
        $results = $context->get('mcp_step_results', []);
        $result = is_array($results) ? ($results['read_question'] ?? null) : null;

        if (! is_array($result)) {
            $context->setHeadline('I could not read that question just now, so I will not guess at it. Please try again.');

            return StageOutcome::blocked('The question could not be read.', ['status' => 'unreadable']);
        }

        return match ((string) ($result['status'] ?? '')) {
            'ok' => $this->question($context, $result),
            'need_question' => $this->whichQuestion($context, $result),
            default => $this->unavailable($context, (string) ($result['message'] ?? '')),
        };
    }

    // ---------------------------------------------------------------- outcomes

    /**
     * @param  array<string, mixed>  $result
     */
    private function question(StageContext $context, array $result): StageOutcome
    {
        $question = $result['question'];
        $revealed = (bool) ($result['answers_revealed'] ?? false);
        $number = (int) $question['number'];
        $correct = array_map(static fn (array $o) => (string) $o['text'], $question['correct_options'] ?? []);
        $teacher = (string) ($question['teacher_explanation'] ?? '');
        $modelAnswer = (string) ($question['model_answer'] ?? '');

        $conflict = $revealed ? $this->disagreement($question, $correct, $teacher) : null;
        $lesson = $this->why($context, $result, $correct);
        $why = $lesson['text'] ?? null;

        // The model's own doubt about the key (another option equally right, a scientific slip) is shown as a
        // flag rather than argued away: the key is what the exam is marked by, but a student should be told.
        $notes = array_values(array_filter([$conflict, $lesson['concern'] ?? null]));

        $answer = null;

        if ($revealed && $correct !== []) {
            $answer = $this->sentence(implode(' and ', $correct));
            // The teacher's own words explain the answer only if they agree with the key; if they point at a
            // different option they are the flag below, not the reason.
            $why ??= $teacher !== '' && $conflict === null ? $teacher : null;
        } elseif ($revealed && $modelAnswer !== '') {
            $answer = $this->sentence($modelAnswer);
            $why ??= $teacher !== '' ? $teacher : null;
        } elseif ($revealed) {
            $notes[] = sprintf('There is no answer recorded for question %d for me to check against.', $number);
        } else {
            $notes[] = 'Your teacher has chosen not to show answers for this exam, so here is a hint instead.';
            $why ??= 'Read the question slowly and rule out the options that clearly do not fit. Think about which part of your lesson this is from.';
        }

        $context->setHeadline("Let's understand this question!");

        // One card, drawn by the chat panel: the question, the answer, why. Keys are the translatable ones
        // (`label`, `value`, `heading`, `text`, `note`) so the language layer can carry it into Hindi or Gujarati.
        $context->addSection(array_filter([
            'type' => 'exam_answer',
            'title' => sprintf('Question %d', $number),
            'description' => (string) $question['title'],
            'label' => $answer !== null ? 'Correct Answer' : null,
            'value' => $answer,
            'heading' => $answer !== null ? 'Why is this correct?' : 'A hint',
            'text' => $why,
            'note' => $notes === [] ? null : implode(' ', array_map(fn (string $n) => $n, $notes)),
        ], static fn ($v) => $v !== null && $v !== ''));

        $context->link(['exam_help' => ['paper_id' => $result['paper']['id'] ?? null, 'question_id' => $question['id']]]);

        return StageOutcome::ran(
            sprintf('Read question %d of "%s" from the exam tables.', $number, (string) ($result['paper']['name'] ?? '')),
            [
                'paper_id' => $result['paper']['id'] ?? null,
                'question_id' => $question['id'],
                'answers_revealed' => $revealed,
                'answer_source' => 'answer_master (database key)',
                'explained_by' => $why === null ? 'none' : ($why === $teacher ? 'the teacher\'s own text' : 'model, one short paragraph'),
                'key_and_explanation_agree' => $conflict === null,
                'model_doubts_key' => isset($lesson['concern']),
            ]
        );
    }

    /**
     * The student named no question: list the real ones so they can pick.
     *
     * @param  array<string, mixed>  $result
     */
    private function whichQuestion(StageContext $context, array $result): StageOutcome
    {
        $questions = array_slice($result['questions'] ?? [], 0, 25);

        $context->setHeadline('Which question do you need help with? Tell me its number.');
        $context->addSection($this->compose->text(
            (string) ($result['paper']['name'] ?? 'Your exam'),
            implode("\n", array_map(
                static fn (array $q) => sprintf('%d. %s', $q['number'], mb_substr((string) $q['title'], 0, 140)),
                $questions
            ))
        ));

        // Shortcuts for picking a question - only here, where none was named; an answer carries no follow-ups.
        foreach (array_slice($questions, 0, 3) as $q) {
            $context->suggestFollowUp(sprintf('Help me with question %d.', $q['number']));
        }

        return StageOutcome::pending('Waiting for the student to say which question.', ['status' => 'need_question']);
    }

    private function unavailable(StageContext $context, string $message): StageOutcome
    {
        $context->setHeadline($message !== '' ? $message : 'I cannot help with that exam from here.');

        return StageOutcome::blocked('The exam question was not available to this person.', ['message' => $message]);
    }

    // ---------------------------------------------------------------- the words

    /**
     * One or two simple sentences on why, plus any doubt the model has about the key, or null.
     *
     * Shown the real question, options and key, and the student's class and subject, so it can pitch the
     * words. Checked afterwards: with answers shown it must name the correct option; with answers hidden
     * it must claim nothing about which option is right or wrong.
     *
     * @param  array<string, mixed>  $result
     * @param  array<int, string>  $correct
     * @return array{text:string, concern:?string}|null
     */
    private function why(StageContext $context, array $result, array $correct): ?array
    {
        $client = $this->clients?->for(self::MODULE, $context->scope->selectedInstituteId, $context->module->key)
            ?? $this->client;

        if (! $client->isConfigured()) {
            return null;
        }

        $revealed = (bool) ($result['answers_revealed'] ?? false);

        if ($revealed && $correct === []) {
            return null;
        }

        $facts = json_encode(
            [
                'class' => $result['paper']['class'] ?? null,
                'subject' => $result['paper']['subject'] ?? null,
                'question' => $result['question'],
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $ask = function (string $extra = '') use ($client, $revealed, $facts, $context) {
            try {
                return $client->json(
                    [
                        ['role' => 'system', 'content' => $this->systemPrompt($revealed)],
                        ['role' => 'user', 'content' => "FACTS (from the school's exam database):\n".$facts."\n\nSTUDENT SAID: ".$context->question.$extra],
                    ],
                    model: null,
                    maxTokens: self::MAX_TOKENS,
                    temperature: 0.2,
                );
            } catch (Throwable) {
                return null;
            }
        };

        $reply = $ask();
        $teacherText = (string) ($result['question']['teacher_explanation'] ?? '');

        // A smaller fallback model (used when the main one's daily quota is spent) sometimes returns nothing usable, or
        // hands back the teacher's one-line note word for word, which restates the answer instead of teaching the idea.
        // One more try, told so; whichever reply teaches is used, and if neither does, the guards below and the teacher's
        // own text still stand.
        $unusable = fn ($r): bool => ! is_array($r) || $this->explanationOf($r) === '' || $this->tooThin($this->explanationOf($r), $teacherText);

        if ($unusable($reply)) {
            $second = $ask("\n\nYour last reply was missing, too short, or only repeated the teacher's note. Teach the idea: say in simple words what the concept is, "
                .'then why the correct option is right, then how it differs from a tempting option. Two or three full sentences.');

            if (! $unusable($second)) {
                $reply = $second;
            }
        }

        if ($reply === null) {
            return null;
        }

        $concern = is_array($reply) && ($reply['key_is_sound'] ?? true) === false && is_scalar($reply['concern'] ?? null)
            ? trim((string) $reply['concern'])
            : '';
        $concern = $revealed && $concern !== '' ? 'This might be worth checking with your teacher: '.$concern.' The exam is marked by the answer key.' : null;

        $text = is_array($reply) && is_scalar($reply['explanation'] ?? null)
            ? trim((string) preg_replace('/\*\*|__/', '', (string) $reply['explanation']))
            : '';

        if ($text === '') {
            return null;
        }

        $lower = mb_strtolower($text);

        if ($revealed) {
            // The explanation must be about the key the exam is marked by.
            foreach ($correct as $option) {
                if (! $this->mentions($text, $option)) {
                    $this->discarded($context, 'explanation did not name the correct option');

                    return null;
                }
            }

            return ['text' => $text, 'concern' => $concern];
        }

        if (preg_match('/\b(?:the\s+)?(?:correct|right)\s+(?:answer|option|choice)\b|\bthe\s+answer\s+is\b|\bis\s+(?:the\s+)?(?:correct|right)\b|\b(?:incorrect|wrong)\b|\bnot\s+the\s+(?:answer|one)\b/iu', $text)) {
            $this->discarded($context, 'hint ruled on an answer the teacher hides');

            return null;
        }

        return ['text' => $text, 'concern' => null];
    }

    /** The model's explanation text, with emphasis marks removed, or '' when there is none. */
    private function explanationOf(mixed $reply): string
    {
        return is_array($reply) && is_scalar($reply['explanation'] ?? null)
            ? trim((string) preg_replace('/\*\*|__/', '', (string) $reply['explanation']))
            : '';
    }

    /**
     * Whether an explanation is too slight to teach anything: under two sentences or about eighteen words, or just the
     * teacher's own line again.
     */
    private function tooThin(string $text, string $teacher): bool
    {
        if ($text === '') {
            return false;
        }

        $norm = static fn (string $v): string => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($v)));
        $words = count(array_filter(explode(' ', $norm($text))));
        $sentences = preg_match_all('/[.!?](?:\s|$)/u', $text);

        if ($words < 18 || $sentences < 2) {
            return true;
        }

        $t = $norm($teacher);

        return $t !== '' && ($norm($text) === $t || str_contains($t, $norm($text)));
    }

    /** The text as a sentence: one full stop, never two (option texts often end in one already). */
    private function sentence(string $text): string
    {
        $text = rtrim(trim($text));

        return preg_match('/[.!?]$/u', $text) ? $text : $text.'.';
    }

    /**
     * Whether the explanation is about this option: its words, ignoring case and punctuation. Longer options
     * are often paraphrased, so most of their words are enough; a short one must appear as written.
     */
    private function mentions(string $text, string $option): bool
    {
        $norm = static fn (string $v): string => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($v)));
        $haystack = ' '.$norm($text).' ';
        $needle = $norm($option);

        if ($needle === '') {
            return true;
        }

        if (str_contains($haystack, ' '.$needle.' ')) {
            return true;
        }

        $words = array_values(array_filter(explode(' ', $needle), static fn (string $w) => mb_strlen($w) > 2));

        if (count($words) < 4) {
            return false;
        }

        $hits = count(array_filter($words, static fn (string $w) => str_contains($haystack, ' '.$w.' ')));

        return $hits / count($words) >= 0.8;
    }

    private function systemPrompt(bool $revealed): string
    {
        $base = "You are a friendly school tutor. Reply with JSON only: {\"explanation\": \"...\"}. "
            ."Write for a student of the class in FACTS: simple everyday words, no jargon (explain any technical word in brackets). "
            ."Use the question, its options and the teacher's explanation in FACTS; you may add ordinary school-level knowledge, but never contradict the teacher. ";

        if ($revealed) {
            return $base."Reply with JSON: {\"explanation\": \"...\", \"key_is_sound\": true|false, \"concern\": \"...\"}. "
                ."\"explanation\" is 2 or 3 short sentences, starting with a short heading-free sentence on what the idea is, saying why the correct option in correct_options is correct, naming it exactly as written, "
                ."and briefly how it differs from a tempting other option. Keep it scientifically accurate. Do not add examples, lists or questions. "
                ."Also judge honestly whether the answer key is right for this class and subject: if another option is equally correct, or the key is scientifically wrong, set key_is_sound to false and put ONE plain sentence in concern saying why; "
                ."in that case still state what the key says, but do not claim anything untrue to defend it. Otherwise key_is_sound is true and concern is empty.";
        }

        return $base."The teacher has HIDDEN the answers, so \"explanation\" is a 2 or 3 sentence hint about the idea the question tests and how to think about it. "
            ."Do NOT say, hint at or rank which option is correct, and never call any option right, wrong, correct or incorrect. Do not add examples, lists or questions.";
    }

    /**
     * The teacher's explanation points at a different option than the key marks.
     *
     * @param  array<string, mixed>  $question
     * @param  array<int, string>  $correct
     */
    private function disagreement(array $question, array $correct, string $teacher): ?string
    {
        if ($teacher === '' || $correct === []) {
            return null;
        }

        $teacherLower = mb_strtolower($teacher);

        foreach ($correct as $text) {
            if ($text !== '' && str_contains($teacherLower, mb_strtolower($text))) {
                return null;
            }
        }

        foreach ($question['options'] ?? [] as $option) {
            $text = mb_strtolower(trim((string) $option['text']));

            if (mb_strlen($text) >= 2 && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($text, '/').'(?![\p{L}\p{N}])/u', $teacherLower) && ! in_array((string) $option['text'], $correct, true)) {
                return sprintf(
                    'The answer key marks "%s" as correct, but the explanation your teacher wrote for this question talks about "%s". '
                    .'The exam is marked by the answer key - please ask your teacher to confirm.',
                    implode('" and "', $correct),
                    $option['text']
                );
            }
        }

        return null;
    }

    private function discarded(StageContext $context, string $reason): void
    {
        try {
            Log::info('ai.exam_help.explanation_discarded', ['reason' => $reason, 'module' => $context->module->key]);
        } catch (Throwable) {
            // Logging must never turn a discarded explanation into a failed turn.
        }
    }
}
