<?php

namespace App\Services\Alumni;

use Illuminate\Support\Facades\DB;

/**
 * Alumni surveys (spec section 39): build, publish, collect and report.
 *
 * THE RULES LIVE HERE so whatever collects responses - staff recording one today, the alumni portal later - gets the same
 * answers: a survey must have questions before it opens; questions are LOCKED once any response exists (changing a
 * question under existing answers would make the results meaningless); a response is accepted only while the survey is
 * open, from an eligible alumnus inside its audience, once per alumnus (the participants table has a unique key, so even a
 * double click cannot record two); and every answer is validated against its question's type and limits.
 *
 * ANONYMOUS surveys store the answers without the alumnus. Participation is still recorded separately - that is what blocks
 * a second response - but nothing joins "this person responded" to "these answers", and the results never name anyone.
 */
class AlumniSurveyService
{
    public const TYPES = [
        'alumni_satisfaction' => 'Alumni satisfaction', 'career_progress' => 'Career progress', 'institution_feedback' => 'Institution feedback',
        'curriculum_feedback' => 'Curriculum feedback', 'industry_feedback' => 'Industry feedback', 'event_feedback' => 'Event feedback',
        'mentorship_feedback' => 'Mentorship feedback', 'placement_feedback' => 'Placement feedback',
    ];

    public const QUESTION_TYPES = ['text', 'single_choice', 'multiple_choice', 'rating', 'yes_no', 'scale'];

    public function __construct(private AlumniMessaging $messaging)
    {
    }

    public function find(int $t, int $id): ?object
    {
        return DB::table('alumni_surveys')->where('sub_institute_id', $t)->where('id', $id)->first();
    }

    public function questions(int $t, int $surveyId)
    {
        return DB::table('alumni_survey_questions')->where('sub_institute_id', $t)->where('survey_id', $surveyId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($q) {
                $q->options = $q->options ? json_decode((string) $q->options, true) : [];

                return $q;
            });
    }

    public function responseCount(int $t, int $surveyId): int
    {
        return (int) DB::table('alumni_survey_submissions')->where('sub_institute_id', $t)->where('survey_id', $surveyId)->count();
    }

    // ---------------------------------------------------------------- questions

    /**
     * Validate a list of questions and return them normalised. @throws AlumniRuleException
     *
     * @param  array<int, array<string, mixed>>  $questions
     * @return array<int, array<string, mixed>>
     */
    public function normaliseQuestions(array $questions): array
    {
        if (count($questions) > 60) {
            throw new AlumniRuleException('A survey can have at most 60 questions.', 422, 'questions');
        }
        $out = [];
        foreach (array_values($questions) as $i => $q) {
            $n = $i + 1;
            $type = (string) ($q['question_type'] ?? '');
            $prompt = trim((string) ($q['prompt'] ?? ''));
            if (! in_array($type, self::QUESTION_TYPES, true)) {
                throw new AlumniRuleException("Question {$n}: choose a question type.", 422, 'questions');
            }
            if ($prompt === '' || mb_strlen($prompt) > 500) {
                throw new AlumniRuleException("Question {$n}: write the question (up to 500 characters).", 422, 'questions');
            }
            $row = ['question_type' => $type, 'prompt' => $prompt, 'required' => ! empty($q['required']) ? 1 : 0, 'sort_order' => $n, 'options' => null, 'scale_min' => null, 'scale_max' => null];

            if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
                // options that differ only by case ("Good" / "good") would be confusing duplicates: keep the first of each
                $options = [];
                foreach ((array) ($q['options'] ?? []) as $o) {
                    $o = trim((string) $o);
                    if ($o !== '' && ! isset($options[mb_strtolower($o)])) {
                        $options[mb_strtolower($o)] = $o;
                    }
                }
                $options = array_values($options);
                if (count($options) < 2 || count($options) > 20) {
                    throw new AlumniRuleException("Question {$n}: give between 2 and 20 different options.", 422, 'questions');
                }
                foreach ($options as $o) {
                    if (mb_strlen($o) > 190) {
                        throw new AlumniRuleException("Question {$n}: an option is longer than 190 characters.", 422, 'questions');
                    }
                }
                $row['options'] = json_encode($options);
            }
            if ($type === 'scale') {
                $min = (int) ($q['scale_min'] ?? 0);
                $max = (int) ($q['scale_max'] ?? 100);
                if ($min < 0 || $max > 100 || $min >= $max) {
                    throw new AlumniRuleException("Question {$n}: the scale must run from a lower number to a higher one, between 0 and 100.", 422, 'questions');
                }
                [$row['scale_min'], $row['scale_max']] = [$min, $max];
            }
            $out[] = $row;
        }

        return $out;
    }

    /** Replace a survey's questions. Refused once anyone has responded. @throws AlumniRuleException */
    public function saveQuestions(int $t, int $surveyId, array $questions): void
    {
        if ($this->responseCount($t, $surveyId) > 0) {
            throw new AlumniRuleException('This survey already has responses, so its questions can no longer be changed.');
        }
        $rows = $this->normaliseQuestions($questions);
        DB::transaction(function () use ($t, $surveyId, $rows) {
            DB::table('alumni_survey_questions')->where('sub_institute_id', $t)->where('survey_id', $surveyId)->delete();
            foreach ($rows as $r) {
                DB::table('alumni_survey_questions')->insert($r + ['sub_institute_id' => $t, 'survey_id' => $surveyId, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    // -------------------------------------------------------------- life cycle

    /** @throws AlumniRuleException */
    public function publish(int $t, int $id): object
    {
        $s = $this->find($t, $id) ?? throw new AlumniRuleException('Survey not found.', 404);
        if ($s->status !== 'draft') {
            throw new AlumniRuleException('Only a draft survey can be published (this one is ' . $s->status . ').');
        }
        if ($this->questions($t, $id)->isEmpty()) {
            throw new AlumniRuleException('Add at least one question before publishing.', 422, 'questions');
        }
        if ($s->closes_at && strtotime((string) $s->closes_at) < time()) {
            throw new AlumniRuleException('The closing time has already passed.', 422, 'closes_at');
        }
        DB::table('alumni_surveys')->where('id', $id)->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);

        return $this->find($t, $id);
    }

    /** @throws AlumniRuleException */
    public function close(int $t, int $id): object
    {
        $s = $this->find($t, $id) ?? throw new AlumniRuleException('Survey not found.', 404);
        if ($s->status !== 'published') {
            throw new AlumniRuleException('Only a published survey can be closed (this one is ' . $s->status . ').');
        }
        DB::table('alumni_surveys')->where('id', $id)->update(['status' => 'closed', 'closed_at' => now(), 'updated_at' => now()]);

        return $this->find($t, $id);
    }

    public function isOpen(object $s): bool
    {
        return $s->status === 'published'
            && (! $s->opens_at || strtotime((string) $s->opens_at) <= time())
            && (! $s->closes_at || strtotime((string) $s->closes_at) >= time());
    }

    // --------------------------------------------------------------- responses

    /**
     * Record one alumnus's response. $answers is question_id => value (a string, a number, or a list for multiple choice).
     *
     * @param  array<int|string, mixed>  $answers
     *
     * @throws AlumniRuleException with `field` = the question that failed, so the form can mark it
     */
    public function submit(int $t, int $surveyId, int $alumniId, array $answers, ?int $enteredBy): int
    {
        $s = $this->find($t, $surveyId) ?? throw new AlumniRuleException('Survey not found.', 404);
        if (! $this->isOpen($s)) {
            throw new AlumniRuleException($s->status === 'draft' ? 'This survey has not been published yet.' : ($s->status === 'closed' || ($s->closes_at && strtotime((string) $s->closes_at) < time()) ? 'This survey is closed.' : 'This survey has not opened yet.'));
        }
        $filters = $s->audience ? (array) json_decode((string) $s->audience, true) : [];
        if (! $this->messaging->audience($t, $filters)->where('id', $alumniId)->exists()) {
            throw new AlumniRuleException('This alumnus is not eligible for this survey.', 422, 'alumni_id');
        }
        if (DB::table('alumni_survey_participants')->where('survey_id', $surveyId)->where('alumni_id', $alumniId)->exists()) {
            throw new AlumniRuleException('This alumnus has already responded to this survey.', 409, 'alumni_id');
        }

        $questions = $this->questions($t, $surveyId);
        $known = $questions->pluck('id')->map(fn ($i) => (int) $i)->all();
        foreach (array_keys($answers) as $qid) {
            if (! in_array((int) $qid, $known, true)) {
                throw new AlumniRuleException('An answer was given to a question that is not in this survey.', 422, 'answers');
            }
        }

        $rows = [];
        foreach ($questions as $q) {
            $raw = $answers[$q->id] ?? $answers[(string) $q->id] ?? null;
            $blank = $raw === null || $raw === '' || $raw === [];
            if ($blank) {
                if ($q->required) {
                    throw new AlumniRuleException('Please answer: ' . $q->prompt, 422, 'q' . $q->id);
                }

                continue;
            }
            $rows[] = $this->answerRow($q, $raw) + ['question_id' => (int) $q->id];
        }

        try {
            return DB::transaction(function () use ($t, $surveyId, $alumniId, $s, $rows, $enteredBy) {
                // the unique key is the real guard against a double submit; a clash lands in the catch below
                DB::table('alumni_survey_participants')->insert(['sub_institute_id' => $t, 'survey_id' => $surveyId, 'alumni_id' => $alumniId, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $sid = (int) DB::table('alumni_survey_submissions')->insertGetId(['sub_institute_id' => $t, 'survey_id' => $surveyId, 'alumni_id' => $s->anonymous ? null : $alumniId,
                    'entered_by' => $enteredBy, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                foreach ($rows as $r) {
                    DB::table('alumni_survey_answers')->insert($r + ['sub_institute_id' => $t, 'submission_id' => $sid, 'created_at' => now(), 'updated_at' => now()]);
                }

                return $sid;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw new AlumniRuleException('This alumnus has already responded to this survey.', 409, 'alumni_id');
        }
    }

    /** @return array<string, mixed> @throws AlumniRuleException */
    private function answerRow(object $q, mixed $raw): array
    {
        $f = 'q' . $q->id;
        $bad = fn (string $m) => new AlumniRuleException($m . ' (' . $q->prompt . ')', 422, $f);

        switch ($q->question_type) {
            case 'text':
                $text = trim((string) (is_scalar($raw) ? $raw : ''));
                if ($text === '' || mb_strlen($text) > 5000) {
                    throw $bad('Answer in up to 5000 characters');
                }

                return ['answer_text' => $text, 'answer_value' => null, 'answer_options' => null];
            case 'single_choice':
                if (! is_scalar($raw) || ! in_array((string) $raw, $q->options, true)) {
                    throw $bad('Choose one of the listed options');
                }

                return ['answer_text' => (string) $raw, 'answer_value' => null, 'answer_options' => null];
            case 'multiple_choice':
                $picked = array_values(array_unique(array_map('strval', (array) $raw)));
                if ($picked === [] || array_diff($picked, $q->options) !== []) {
                    throw $bad('Choose from the listed options only');
                }

                return ['answer_text' => implode(', ', $picked), 'answer_value' => null, 'answer_options' => json_encode($picked)];
            case 'rating':
                if (! is_numeric($raw) || (int) $raw != $raw || (int) $raw < 1 || (int) $raw > 5) {
                    throw $bad('Give a rating from 1 to 5');
                }

                return ['answer_text' => (string) (int) $raw, 'answer_value' => (int) $raw, 'answer_options' => null];
            case 'yes_no':
                $v = strtolower((string) (is_scalar($raw) ? $raw : ''));
                if (! in_array($v, ['yes', 'no'], true)) {
                    throw $bad('Answer yes or no');
                }

                return ['answer_text' => $v, 'answer_value' => null, 'answer_options' => null];
            default: // scale
                if (! is_numeric($raw) || (float) $raw < (int) $q->scale_min || (float) $raw > (int) $q->scale_max) {
                    throw $bad("Give a number from {$q->scale_min} to {$q->scale_max}");
                }

                return ['answer_text' => (string) $raw, 'answer_value' => (float) $raw, 'answer_options' => null];
        }
    }

    // ------------------------------------------------------------------ results

    /** @return array<string, mixed> */
    public function results(int $t, int $surveyId): array
    {
        $s = $this->find($t, $surveyId) ?? throw new AlumniRuleException('Survey not found.', 404);
        $responses = $this->responseCount($t, $surveyId);
        $filters = $s->audience ? (array) json_decode((string) $s->audience, true) : [];
        $audience = (int) $this->messaging->audience($t, $filters)->count();
        $out = [];

        foreach ($this->questions($t, $surveyId) as $q) {
            $answers = DB::table('alumni_survey_answers as a')->join('alumni_survey_submissions as s', 's.id', '=', 'a.submission_id')
                ->where('a.sub_institute_id', $t)->where('a.question_id', $q->id)->get(['a.answer_text', 'a.answer_value', 'a.answer_options', 's.alumni_id']);
            $row = ['id' => (int) $q->id, 'prompt' => $q->prompt, 'question_type' => $q->question_type, 'required' => (bool) $q->required, 'answered' => $answers->count()];

            if (in_array($q->question_type, ['single_choice', 'multiple_choice', 'yes_no'], true)) {
                $options = $q->question_type === 'yes_no' ? ['yes', 'no'] : $q->options;
                $counts = array_fill_keys($options, 0);
                foreach ($answers as $a) {
                    $picked = $q->question_type === 'multiple_choice' ? (array) json_decode((string) $a->answer_options, true) : [$a->answer_text];
                    foreach ($picked as $p) {
                        if (isset($counts[$p])) {
                            $counts[$p]++;
                        }
                    }
                }
                $row['counts'] = collect($counts)->map(fn ($n, $label) => ['option' => $label, 'count' => $n, 'percent' => $answers->count() ? round($n * 100 / $answers->count(), 1) : 0])->values()->all();
            } elseif (in_array($q->question_type, ['rating', 'scale'], true)) {
                $values = $answers->pluck('answer_value')->map(fn ($v) => (float) $v);
                $row['average'] = $values->isEmpty() ? null : round($values->avg(), 2);
                $row['min'] = $values->isEmpty() ? null : $values->min();
                $row['max'] = $values->isEmpty() ? null : $values->max();
                if ($q->question_type === 'rating') {
                    $row['distribution'] = collect(range(1, 5))->map(fn ($n) => ['rating' => $n, 'count' => $values->filter(fn ($v) => (int) $v === $n)->count()])->all();
                }
            } else {
                $names = $s->anonymous ? collect() : DB::table('alumni')->where('sub_institute_id', $t)->whereIn('id', $answers->pluck('alumni_id')->filter())->pluck('full_name', 'id');
                $row['texts'] = $answers->take(200)->map(fn ($a) => ['answer' => $a->answer_text, 'by' => ($s->anonymous || ! $a->alumni_id) ? null : ($names[$a->alumni_id] ?? null)])->values()->all();
            }
            $out[] = $row;
        }

        return ['survey' => $s, 'responses' => $responses, 'audience' => $audience, 'response_rate' => $audience > 0 ? round($responses * 100 / $audience, 1) : null, 'questions' => $out];
    }
}
