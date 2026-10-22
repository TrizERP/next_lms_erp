<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One question of an online exam, read for the student who is stuck on it.
 *
 * Everything returned comes from the rows the exam itself is built from - `question_paper`,
 * `lms_question_master`, `answer_master` and `lms_question_mapping` - so the help can never
 * disagree with what the exam screen shows or what the result screen later marks right.
 * Nothing here is written, and no answer is held in code.
 *
 * ## Who may ask about which question
 *
 * A student gets help only on a paper they could open and attempt right now, by the same rules
 * `ApiQuestionPaperController::index` lists exams by: an online/practice paper of their own
 * institute (or the shared LMS library), for their own standard, inside its open/close window.
 * That is what stops this tool being a way to read the answer key of a paper they have not been
 * given. Staff and administrators may look at any paper of an institute they can access, to
 * preview what a student would be told.
 *
 * ## The teacher's choice about answers is respected
 *
 * `question_paper.result_show_ans` is the teacher's setting for whether students may see the
 * correct answers. When it is off, this returns the question, its options and the topic it
 * belongs to - and no key, no teacher hint and no model answer - and says so. The assistant can
 * still explain the idea; it cannot be used to read off the answer the teacher chose to hide.
 */
class ExamQuestionHelpService
{
    /** The paper types a student can attempt, matching the exam list. */
    private const STUDENT_EXAM_TYPES = ['online', 'practice', ''];

    /**
     * @param  array<string, mixed>  $arguments  paper_id, question_id, question_number, question_text.
     * @return array<string, mixed>
     */
    public function help(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('question_paper') || ! Schema::hasTable('lms_question_master')) {
            return $this->unavailable('Online exams are not recorded in this estate.');
        }

        $paperId = (int) ($arguments['paper_id'] ?? 0);

        if ($paperId <= 0) {
            return $this->unavailable('Open the exam first, so I know which one you are attempting.');
        }

        $paper = DB::table('question_paper')->where('id', $paperId)->first();

        if (! $paper) {
            return $this->unavailable('I could not find that exam.');
        }

        $refusal = $this->mayAsk($context, $paper);

        if ($refusal !== null) {
            return $this->unavailable($refusal);
        }

        $questions = $this->paperQuestions($paper);

        if ($questions === []) {
            return $this->unavailable('This exam has no questions I can read.');
        }

        $picked = $this->pick($questions, $arguments);
        $paperSummary = [
            'id' => (int) $paper->id,
            'name' => (string) $paper->paper_name,
            'question_count' => count($questions),
            'class' => $this->className($paper),
            'subject' => $this->subjectName($paper),
        ];

        if ($picked === null) {
            return [
                'status' => 'need_question',
                'paper' => $paperSummary,
                'questions' => array_map(static fn (array $q) => [
                    'number' => $q['number'],
                    'id' => $q['id'],
                    'title' => $q['title'],
                ], $questions),
            ];
        }

        // The owner asked that Ask AI always gives the answer, so a paper's `result_show_ans` = 0 does not
        // hide it by default. AI_EXAM_HELP_RESPECT_TEACHER_SETTING=true restores the teacher's choice.
        $reveal = ! config('ai.exam_help.respect_teacher_setting', false)
            || (int) ($paper->result_show_ans ?? 0) === 1;

        return [
            'status' => 'ok',
            'paper' => $paperSummary,
            'answers_revealed' => $reveal,
            'question' => $this->detail($picked, $reveal),
        ];
    }

    /**
     * Why this caller may not be helped with this paper, or null when they may.
     */
    private function mayAsk(McpRequestContext $context, object $paper): ?string
    {
        $institute = (int) $paper->sub_institute_id;
        $ownInstitute = in_array($institute, $context->allowedInstituteIds, true)
            || $institute === $context->selectedInstituteId;

        // The shared LMS library: a paper owned by institute 1 is open to an institute set up as LMS.
        $library = $institute === 1 && $this->isLmsInstitute($context->selectedInstituteId);

        if (! $ownInstitute && ! $library) {
            return 'That exam does not belong to your school.';
        }

        if (! $context->isStudent) {
            return null;
        }

        if (! in_array(strtolower((string) $paper->exam_type), self::STUDENT_EXAM_TYPES, true)) {
            return 'That is not an exam you can attempt online.';
        }

        $enrolment = DB::table('tblstudent_enrollment')
            ->where('student_id', $context->userId)
            ->where('syear', $paper->syear)
            ->where('standard_id', $paper->standard_id)
            ->exists();

        if (! $enrolment) {
            return 'That exam is not assigned to your class.';
        }

        // The open/close dates are not checked: the exam screen opens a paper whatever its dates say, so
        // a student who is on it is helped. Enrolment in the paper's class is what scopes access.
        return null;
    }

    /** The class the paper is set for ("Std 8"), so the explanation can be pitched at that age. */
    private function className(object $paper): ?string
    {
        if (! Schema::hasTable('standard')) {
            return null;
        }

        $name = DB::table('standard')->where('id', $paper->standard_id)->value('name');

        return $name !== null && $name !== '' ? (string) $name : null;
    }

    private function subjectName(object $paper): ?string
    {
        if (! Schema::hasTable('sub_std_map')) {
            return null;
        }

        $name = DB::table('sub_std_map')
            ->where('subject_id', $paper->subject_id)
            ->where('standard_id', $paper->standard_id)
            ->value('display_name');

        return $name !== null && $name !== '' ? (string) $name : null;
    }

    private function isLmsInstitute(int $instituteId): bool
    {
        if (! Schema::hasTable('school_setup')) {
            return false;
        }

        return DB::table('school_setup')->where('Id', $instituteId)->value('is_Lms') === 'Y';
    }

    /**
     * The paper's questions in the order the exam screen numbers them.
     *
     * The attempt screen lists `lms_question_master` rows fetched with `whereIn(id, ...)` - that is,
     * in id order, not in the order `question_ids` was typed - so "question 3" to a student is the
     * third by id. Numbering here follows the screen, not the column.
     *
     * @return array<int, array<string, mixed>>
     */
    private function paperQuestions(object $paper): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $paper->question_ids)))));

        if ($ids === []) {
            return [];
        }

        $rows = DB::table('lms_question_master')
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $answers = DB::table('answer_master')
            ->whereIn('question_id', $rows->pluck('id')->all())
            ->orderBy('id')
            ->get()
            ->groupBy('question_id');

        $questions = [];

        foreach ($rows as $index => $row) {
            $questions[] = [
                'number' => $index + 1,
                'id' => (int) $row->id,
                'title' => $this->plain($row->question_title),
                'type_id' => (int) $row->question_type_id,
                'multiple' => (int) $row->multiple_answer === 1,
                'points' => (float) $row->points,
                'row' => $row,
                'options' => ($answers[$row->id] ?? collect())->all(),
            ];
        }

        return $questions;
    }

    /**
     * The question the caller means: by id, then by position, then by the words they used.
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    private function pick(array $questions, array $arguments): ?array
    {
        $id = (int) ($arguments['question_id'] ?? 0);

        if ($id > 0) {
            foreach ($questions as $question) {
                if ($question['id'] === $id) {
                    return $question;
                }
            }

            // An id that is not on this paper is never "close enough".
            return null;
        }

        $number = (int) ($arguments['question_number'] ?? 0);

        if ($number > 0) {
            foreach ($questions as $question) {
                if ($question['number'] === $number) {
                    return $question;
                }
            }

            return null;
        }

        $text = $this->normalise((string) ($arguments['question_text'] ?? ''));

        if (mb_strlen($text) < 12) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($questions as $question) {
            $title = $this->normalise($question['title']);

            if ($title === '') {
                continue;
            }

            // The student pasted (most of) the question: either contains the other.
            if (str_contains($text, $title) || (mb_strlen($text) >= 20 && str_contains($title, $text))) {
                return $question;
            }

            $score = $this->overlap($text, $title);

            if ($score > $bestScore) {
                $best = $question;
                $bestScore = $score;
            }
        }

        // Words in common are only a guess; require most of the question's own words, and a clear winner.
        return $bestScore >= 0.7 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function detail(array $question, bool $reveal): array
    {
        /** @var object $row */
        $row = $question['row'];
        $options = array_map(fn (object $option) => [
            'id' => (int) $option->id,
            'text' => $this->plain($option->answer),
            'correct' => (int) $option->correct_answer === 1,
            'feedback' => $this->plain($option->feedback),
        ], $question['options']);

        $detail = [
            'id' => $question['id'],
            'number' => $question['number'],
            'title' => $question['title'],
            'type' => $question['type_id'] === 1 ? 'multiple_choice' : 'written',
            'multiple_answers' => $question['multiple'],
            'points' => $question['points'],
            'options' => array_map(static fn (array $o) => ['id' => $o['id'], 'text' => $o['text']], $options),
            'topics' => $this->topics($question['id'], $row),
        ];

        if (! $reveal) {
            return $detail;
        }

        $detail['correct_options'] = array_values(array_map(
            static fn (array $o) => ['id' => $o['id'], 'text' => $o['text']],
            array_filter($options, static fn (array $o) => $o['correct'])
        ));
        // A written (narrative) question stores its model answer either as plain text or as a JSON document
        // with `model_answer` and `explanation` inside it; neither may reach a student as raw JSON.
        $stored = json_decode((string) $row->answer, true);
        $stored = is_array($stored) ? $stored : null;

        $detail['teacher_explanation'] = $this->plain($row->hint_text) ?: $this->plain($stored['explanation'] ?? '');
        $detail['model_answer'] = $stored !== null ? $this->plain($stored['model_answer'] ?? '') : $this->plain($row->answer);
        $detail['option_feedback'] = array_values(array_filter(array_map(
            static fn (array $o) => $o['feedback'] !== '' ? ['option' => $o['text'], 'feedback' => $o['feedback']] : null,
            $options
        )));

        return $detail;
    }

    /**
     * The chapter / concept / topic the question is filed under, as names.
     *
     * @return array<int, array{kind:string, name:string}>
     */
    private function topics(int $questionId, object $row): array
    {
        $topics = [];

        if (Schema::hasTable('lms_question_mapping') && Schema::hasTable('lms_mapping_type')) {
            $mapped = DB::table('lms_question_mapping as m')
                ->join('lms_mapping_type as kind', 'kind.id', '=', 'm.mapping_type_id')
                ->join('lms_mapping_type as value', 'value.id', '=', 'm.mapping_value_id')
                ->where('m.questionmaster_id', $questionId)
                ->get(['kind.name as kind', 'value.name as name']);

            foreach ($mapped as $entry) {
                // Bloom level, difficulty, "Skills" and the like are tags about the question, not what it
                // is about; only a chapter / topic / concept tag tells the student where to look.
                if (! preg_match('/chapter|topic|concept|unit|lesson/i', (string) $entry->kind)) {
                    continue;
                }

                $topics[] = ['kind' => (string) $entry->kind, 'name' => $this->plain($entry->name)];
            }
        }

        foreach (['concept' => 'Concept', 'subconcept' => 'Sub-concept'] as $column => $label) {
            $value = $this->plain($row->{$column} ?? '');

            if ($value !== '' && ! in_array($value, array_column($topics, 'name'), true)) {
                $topics[] = ['kind' => $label, 'name' => $value];
            }
        }

        return $topics;
    }

    /** @return array<string, mixed> */
    private function unavailable(string $message): array
    {
        return ['status' => 'not_available', 'message' => $message];
    }

    private function plain(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower($this->plain($text));

        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }

    /** The share of the question's own words that the sentence contains. */
    private function overlap(string $sentence, string $title): float
    {
        $words = array_values(array_filter(explode(' ', $title), static fn (string $w) => mb_strlen($w) > 2));

        if ($words === []) {
            return 0.0;
        }

        $have = array_flip(explode(' ', $sentence));
        $hits = count(array_filter($words, static fn (string $w) => isset($have[$w])));

        return $hits / count($words);
    }
}
