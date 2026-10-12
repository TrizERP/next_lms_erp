<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniApiController;
use App\Http\Controllers\api\alumni\AlumniSurveyApiController;
use Illuminate\Support\Facades\DB;
use Tests\Support\AlumniTestHelpers;
use Tests\TestCase;

/**
 * Surveys: question validation and locking, publish rules, response rules, anonymity, exact results, sending, isolation.
 * Runs on in-memory sqlite (see AlumniTestHelpers).
 */
class AlumniSurveysTest extends TestCase
{
    use AlumniTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAlumni();
    }

    /** a survey with one question of every type */
    private function questions(): array
    {
        return [
            ['question_type' => 'text', 'prompt' => 'What would you change?', 'required' => false],
            ['question_type' => 'single_choice', 'prompt' => 'Overall experience', 'options' => ['Good', 'Fair', 'Poor'], 'required' => true],
            ['question_type' => 'multiple_choice', 'prompt' => 'Which helped?', 'options' => ['Teachers', 'Labs', 'Sports'], 'required' => false],
            ['question_type' => 'rating', 'prompt' => 'Rate the school', 'required' => true],
            ['question_type' => 'yes_no', 'prompt' => 'Would you recommend us?', 'required' => true],
            ['question_type' => 'scale', 'prompt' => 'Career readiness %', 'scale_min' => 0, 'scale_max' => 100, 'required' => true],
        ];
    }

    private function mk(array $over = [], bool $publish = true, int $tenant = 7): int
    {
        [$s, $b] = $this->go(AlumniSurveyApiController::class, 'store', array_merge([
            'title' => 'Survey ' . uniqid(), 'survey_type' => 'alumni_satisfaction', 'questions' => $this->questions(),
        ], $over), $tenant);
        $this->assertSame(201, $s, json_encode($b));
        $id = (int) $b['data']['id'];
        if ($publish) {
            [$p, $pb] = $this->go(AlumniSurveyApiController::class, 'publish', [], $tenant, 'Admin', [$id]);
            $this->assertSame(200, $p, json_encode($pb));
        }

        return $id;
    }

    /** question ids in order: text, single, multi, rating, yes_no, scale */
    private function qids(int $survey): array
    {
        return DB::table('alumni_survey_questions')->where('survey_id', $survey)->orderBy('sort_order')->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    private function answers(array $qids, array $over = []): array
    {
        [$text, $single, $multi, $rating, $yn, $scale] = $qids;

        return array_replace([$text => 'More labs', $single => 'Good', $multi => ['Teachers', 'Labs'], $rating => 4, $yn => 'yes', $scale => 80], $over);
    }

    private function respond(int $survey, object $alumnus, array $answers, int $tenant = 7): array
    {
        return $this->go(AlumniSurveyApiController::class, 'respond', ['alumni_id' => $alumnus->id, 'answers' => $answers], $tenant, 'Admin', [$survey]);
    }

    // ------------------------------------------------------------- building

    public function test_question_definitions_are_validated(): void
    {
        $bad = [
            [['question_type' => 'essay', 'prompt' => 'x']],
            [['question_type' => 'text', 'prompt' => '   ']],
            [['question_type' => 'single_choice', 'prompt' => 'Pick', 'options' => ['Only one']]],
            [['question_type' => 'single_choice', 'prompt' => 'Pick', 'options' => ['A', 'a ', 'A']]],            // duplicates collapse to fewer than two
            [['question_type' => 'scale', 'prompt' => 'Scale', 'scale_min' => 50, 'scale_max' => 10]],
            [['question_type' => 'scale', 'prompt' => 'Scale', 'scale_min' => 0, 'scale_max' => 500]],
        ];
        $codes = array_map(fn ($q) => $this->go(AlumniSurveyApiController::class, 'store', ['title' => 'T', 'survey_type' => 'career_progress', 'questions' => $q])[0], $bad);
        [$type, $te] = $this->go(AlumniSurveyApiController::class, 'store', ['title' => 'T', 'survey_type' => 'party']);
        [$teacher] = $this->go(AlumniSurveyApiController::class, 'index', [], 7, 'Teacher');

        $this->assertSame([422, 422, 422, 422, 422, 422], $codes);
        $this->assertArrayHasKey('survey_type', $te['errors']);
        $this->assertSame([422, 403], [$type, $teacher]);
        $this->assertSame(0, DB::table('alumni_surveys')->count());
    }

    public function test_publishing_needs_questions_and_questions_lock_once_responses_exist(): void
    {
        $empty = $this->mk(['questions' => []], false);
        [$p, $pe] = $this->go(AlumniSurveyApiController::class, 'publish', [], 7, 'Admin', [$empty]);

        $id = $this->mk();
        $a = $this->alum('Locker');
        $this->respond($id, $a, $this->answers($this->qids($id)));
        [$edit, $eb] = $this->go(AlumniSurveyApiController::class, 'update', ['questions' => [['question_type' => 'text', 'prompt' => 'New']]], 7, 'Admin', [$id]);
        [$title] = $this->go(AlumniSurveyApiController::class, 'update', ['title' => 'Renamed'], 7, 'Admin', [$id]);
        [$anon] = $this->go(AlumniSurveyApiController::class, 'update', ['anonymous' => true], 7, 'Admin', [$id]);
        [$del, $db] = $this->go(AlumniSurveyApiController::class, 'destroy', [], 7, 'Admin', [$id]);

        $this->assertSame(422, $p);
        $this->assertArrayHasKey('questions', $pe['errors']);
        $this->assertSame(409, $edit);                                         // the questions under existing answers cannot change
        $this->assertStringContainsString('responses', $eb['message']);
        $this->assertSame(200, $title);                                        // but a title can
        $this->assertSame(409, $anon);
        $this->assertSame(409, $del);
        $this->assertStringContainsString('Close it', $db['message']);
        $this->assertCount(6, $this->qids($id));
    }

    public function test_lifecycle_open_window_close_and_delete_of_an_unanswered_draft(): void
    {
        $future = $this->mk(['opens_at' => now()->addDay()->toDateTimeString()]);
        $expired = $this->mk(['opens_at' => now()->subDays(3)->toDateTimeString(), 'closes_at' => now()->addHour()->toDateTimeString()]);
        DB::table('alumni_surveys')->where('id', $expired)->update(['closes_at' => now()->subMinute()]);   // the closing time passes
        $a = $this->alum('Window');

        [$notOpen, $no] = $this->respond($future, $a, $this->answers($this->qids($future)));
        [$over, $ov] = $this->respond($expired, $a, $this->answers($this->qids($expired)));
        $open = $this->mk();
        [$closed] = $this->go(AlumniSurveyApiController::class, 'close', [], 7, 'Admin', [$open]);
        [$afterClose, $ac] = $this->respond($open, $a, $this->answers($this->qids($open)));
        [$closeAgain] = $this->go(AlumniSurveyApiController::class, 'close', [], 7, 'Admin', [$open]);
        $draft = $this->mk([], false);
        [$earlyResp, $er] = $this->respond($draft, $a, $this->answers($this->qids($draft)));
        [$delDraft] = $this->go(AlumniSurveyApiController::class, 'destroy', [], 7, 'Admin', [$draft]);

        $this->assertSame([409, 409, 200, 409, 409, 409, 200], [$notOpen, $over, $closed, $afterClose, $closeAgain, $earlyResp, $delDraft]);
        $this->assertStringContainsString('not opened', $no['message']);
        $this->assertStringContainsString('closed', $ov['message']);
        $this->assertStringContainsString('closed', $ac['message']);
        $this->assertStringContainsString('not been published', $er['message']);
        $this->assertSame(0, DB::table('alumni_survey_questions')->where('survey_id', $draft)->count());   // deleting removed its questions too
    }

    // ------------------------------------------------------------ responding

    public function test_every_answer_is_validated_against_its_question(): void
    {
        $id = $this->mk();
        $q = $this->qids($id);
        $a = $this->alum('Strict');
        $cases = [
            'missing required single' => array_diff_key($this->answers($q), [$q[1] => 1]),
            'option not offered' => $this->answers($q, [$q[1] => 'Excellent']),
            'multi outside options' => $this->answers($q, [$q[2] => ['Teachers', 'Cafeteria']]),
            'rating 0' => $this->answers($q, [$q[3] => 0]),
            'rating 6' => $this->answers($q, [$q[3] => 6]),
            'rating 3.5' => $this->answers($q, [$q[3] => 3.5]),
            'yes/no maybe' => $this->answers($q, [$q[4] => 'maybe']),
            'scale 101' => $this->answers($q, [$q[5] => 101]),
            'scale text' => $this->answers($q, [$q[5] => 'high']),
            'unknown question' => $this->answers($q) + [999999 => 'x'],
            'text too long' => $this->answers($q, [$q[0] => str_repeat('x', 5001)]),
        ];

        foreach ($cases as $label => $answers) {
            [$s] = $this->respond($id, $a, $answers);
            $this->assertSame(422, $s, $label);
        }
        $this->assertSame(0, DB::table('alumni_survey_submissions')->count());          // nothing half-saved
        $this->assertSame(0, DB::table('alumni_survey_participants')->count());
        [$ok] = $this->respond($id, $a, array_diff_key($this->answers($q), [$q[0] => 1, $q[2] => 1]));   // the two optional ones may be left out
        $this->assertSame(201, $ok);
    }

    public function test_one_response_per_alumnus_and_only_eligible_alumni_in_the_audience(): void
    {
        $id = $this->mk(['filters' => ['batch' => '2012-13']]);
        $q = $this->qids($id);
        $in = $this->alum('Inside', ['batch' => '2012-13']);
        $out = $this->alum('Outside', ['batch' => '2018-19']);
        $blocked = $this->alum('Blocked', ['batch' => '2012-13'], 7, 'blocked');
        $elsewhere = $this->alum('Else', ['batch' => '2012-13'], 8);

        [$first] = $this->respond($id, $in, $this->answers($q));
        [$second, $sb] = $this->respond($id, $in, $this->answers($q));
        $codes = [$this->respond($id, $out, $this->answers($q))[0], $this->respond($id, $blocked, $this->answers($q))[0], $this->respond($id, $elsewhere, $this->answers($q))[0]];
        [$crossTenant] = $this->respond($id, $elsewhere, $this->answers($q), 8);

        $this->assertSame([201, 409], [$first, $second]);
        $this->assertStringContainsString('already responded', $sb['message']);
        $this->assertSame([422, 422, 422], $codes);
        $this->assertSame(404, $crossTenant);                                  // the other institute cannot even see this survey
        $this->assertSame(1, DB::table('alumni_survey_submissions')->count());
    }

    public function test_a_double_submit_cannot_record_two_responses(): void
    {
        $id = $this->mk();
        $q = $this->qids($id);
        $a = $this->alum('Twice');
        $service = app(\App\Services\Alumni\AlumniSurveyService::class);

        $service->submit(7, $id, (int) $a->id, $this->answers($q), 55);
        // simulate the race: the participant row exists but the pre-check was passed by a concurrent request
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('alumni_survey_participants')->insert(['sub_institute_id' => 7, 'survey_id' => $id, 'alumni_id' => $a->id, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_anonymous_survey_keeps_the_answers_but_not_the_person(): void
    {
        $id = $this->mk(['anonymous' => true]);
        $q = $this->qids($id);
        $a = $this->alum('Hidden', ['full_name' => 'Hidden Alum']);
        $this->respond($id, $a, $this->answers($q, [$q[0] => 'Honest words']));

        $submission = DB::table('alumni_survey_submissions')->first();
        [, $res] = $this->go(AlumniSurveyApiController::class, 'results', [], 7, 'Admin', [$id]);
        [, $part] = $this->go(AlumniSurveyApiController::class, 'participation', [], 7, 'Admin', [$id]);
        [$again] = $this->respond($id, $a, $this->answers($q));

        $this->assertNull($submission->alumni_id);                             // nothing links the answers to the person
        $this->assertSame(1, DB::table('alumni_survey_participants')->where('alumni_id', $a->id)->count());   // but they cannot answer twice
        $this->assertSame(409, $again);
        $this->assertNull($res['data']['questions'][0]['texts'][0]['by']);
        $this->assertStringNotContainsString('Hidden Alum', json_encode($res['data']));
        $this->assertSame(1, $part['data']['responded']);
        $this->assertSame([], $part['data']['pending_alumni']);
    }

    // --------------------------------------------------------------- results

    public function test_results_are_exact_and_name_respondents_only_when_the_survey_is_not_anonymous(): void
    {
        $id = $this->mk();
        $q = $this->qids($id);
        $people = [$this->alum('P1'), $this->alum('P2'), $this->alum('P3'), $this->alum('P4')];
        $this->respond($id, $people[0], $this->answers($q, [$q[1] => 'Good', $q[2] => ['Teachers'], $q[3] => 5, $q[4] => 'yes', $q[5] => 90, $q[0] => 'Loved it']));
        $this->respond($id, $people[1], $this->answers($q, [$q[1] => 'Good', $q[2] => ['Teachers', 'Labs'], $q[3] => 4, $q[4] => 'yes', $q[5] => 70]));
        $this->respond($id, $people[2], $this->answers($q, [$q[1] => 'Poor', $q[2] => ['Sports'], $q[3] => 1, $q[4] => 'no', $q[5] => 20, $q[0] => 'Needs work']));

        [, $r] = $this->go(AlumniSurveyApiController::class, 'results', [], 7, 'Admin', [$id]);
        $by = collect($r['data']['questions'])->keyBy('id');

        $this->assertSame(3, $r['data']['responses']);
        $this->assertSame(4, $r['data']['audience']);                          // the audience is everyone eligible
        $this->assertEquals(75.0, $r['data']['response_rate']);
        $single = collect($by[$q[1]]['counts'])->keyBy('option');
        $this->assertSame([2, 0, 1], [$single['Good']['count'], $single['Fair']['count'], $single['Poor']['count']]);
        $this->assertEquals(66.7, $single['Good']['percent']);
        $multi = collect($by[$q[2]]['counts'])->keyBy('option');
        $this->assertSame([2, 1, 1], [$multi['Teachers']['count'], $multi['Labs']['count'], $multi['Sports']['count']]);   // a person may pick several
        $this->assertEquals(3.33, $by[$q[3]]['average']);
        $this->assertSame([1, 0, 0, 1, 1], array_column($by[$q[3]]['distribution'], 'count'));
        $this->assertSame([2, 1], array_column($by[$q[4]]['counts'], 'count'));
        $this->assertEquals(60.0, $by[$q[5]]['average']);
        $this->assertSame(3, $by[$q[0]]['answered']);                          // P2 gave the default text answer
        $this->assertEqualsCanonicalizing(['P1 Alum', 'P2 Alum', 'P3 Alum'], array_column($by[$q[0]]['texts'], 'by'));   // not anonymous: respondents are named
    }

    public function test_participation_lists_who_has_not_responded_without_linking_answers(): void
    {
        $id = $this->mk();
        $q = $this->qids($id);
        [$done, $todo] = [$this->alum('Done'), $this->alum('Todo')];
        $this->alum('Never', [], 7, 'blocked');
        $this->respond($id, $done, $this->answers($q));

        [, $p] = $this->go(AlumniSurveyApiController::class, 'participation', [], 7, 'Admin', [$id]);
        [$cross] = $this->go(AlumniSurveyApiController::class, 'participation', [], 8, 'Admin', [$id]);

        $this->assertSame([1, 1], [$p['data']['responded'], $p['data']['pending']]);
        $this->assertSame([$todo->alumni_code], array_column($p['data']['pending_alumni'], 'alumni_code'));
        $this->assertSame(404, $cross);
    }

    // ---------------------------------------------------------------- sending

    public function test_sending_a_survey_creates_a_campaign_with_the_title_and_the_portal_link_when_configured(): void
    {
        $id = $this->mk(['title' => 'Career check-in']);
        $a = $this->alum('Reach');
        $this->consent($a, ['email']);

        [$noPortal, $nb] = $this->go(AlumniSurveyApiController::class, 'send', ['channels' => ['email']], 7, 'Admin', [$id]);
        $body1 = DB::table('alumni_campaigns')->orderByDesc('id')->value('body');
        [$bad] = $this->go(AlumniApiController::class, 'saveSettings', ['alumni_portal_url' => 'javascript:alert(1)']);
        $this->go(AlumniApiController::class, 'saveSettings', ['alumni_portal_url' => 'https://alumni.example.org/']);
        $a2 = $this->alum('Reach2');
        $this->consent($a2, ['email']);
        $this->go(AlumniSurveyApiController::class, 'send', ['channels' => ['email']], 7, 'Admin', [$id]);
        $body2 = DB::table('alumni_campaigns')->orderByDesc('id')->value('body');
        $draft = $this->mk([], false);
        [$draftSend] = $this->go(AlumniSurveyApiController::class, 'send', ['channels' => ['email']], 7, 'Admin', [$draft]);

        $this->assertSame(201, $noPortal);
        $this->assertStringContainsString('Career check-in', $body1);
        $this->assertStringContainsString('contact the alumni office', $body1);   // honest when there is nowhere to send them
        $this->assertStringContainsString('No alumni portal address', $nb['message']);
        $this->assertSame(422, $bad);
        $this->assertStringContainsString("https://alumni.example.org/alumni/survey/{$id}", $body2);
        $this->assertSame(409, $draftSend);                                    // only an open survey can be sent
        $this->assertSame('survey', DB::table('alumni_campaigns')->value('comm_type'));
    }

    // -------------------------------------------------------------- listings

    public function test_listing_counts_responses_filters_and_is_scoped(): void
    {
        $mine = $this->mk(['title' => 'Mine', 'survey_type' => 'placement_feedback']);
        $this->mk(['title' => 'Theirs'], true, 8);
        $a = $this->alum('Counted');
        $this->respond($mine, $a, $this->answers($this->qids($mine)));

        [, $all] = $this->go(AlumniSurveyApiController::class, 'index');
        [, $filtered] = $this->go(AlumniSurveyApiController::class, 'index', ['survey_type' => 'curriculum_feedback']);
        [$badFilter] = $this->go(AlumniSurveyApiController::class, 'index', ['status' => 'maybe']);
        [$crossShow] = $this->go(AlumniSurveyApiController::class, 'show', [], 8, 'Admin', [$mine]);
        [$crossPublish] = $this->go(AlumniSurveyApiController::class, 'publish', [], 8, 'Admin', [$mine]);
        [$crossResults] = $this->go(AlumniSurveyApiController::class, 'results', [], 8, 'Admin', [$mine]);
        [, $show] = $this->go(AlumniSurveyApiController::class, 'show', [], 7, 'Admin', [$mine]);

        $this->assertSame(['Mine'], array_column($all['data']['rows'], 'title'));
        $this->assertSame([1, 6, true], [$all['data']['rows'][0]['response_count'], $all['data']['rows'][0]['question_count'], $all['data']['rows'][0]['is_open']]);
        $this->assertSame([], $filtered['data']['rows']);
        $this->assertSame([422, 404, 404, 404], [$badFilter, $crossShow, $crossPublish, $crossResults]);
        $this->assertTrue($show['data']['locked']);
        $this->assertCount(6, $show['data']['questions']);
        $this->assertSame(['Good', 'Fair', 'Poor'], $show['data']['questions'][1]['options']);
    }
}
