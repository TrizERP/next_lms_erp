<?php

namespace Tests\Unit;

use App\Domain\AI\Conversation\IntentClassifier;
use PHPUnit\Framework\TestCase;

/**
 * What an intent has to earn before it may claim a question.
 *
 * The classifier scores confidence as the winner's *share* of everything that matched.
 * That is a good measure of how contested a reading is and a useless one of how well
 * supported it is: an intent matching the single word "which", with nothing else
 * matching at all, scored 100%.
 *
 * The cost was not theoretical. "Which students are in class 5?" — a roster question —
 * was routed to the academic-risk agent on the strength of "which", and answered with a
 * risk scan of a different student entirely. So an intent must now clear an absolute
 * score as well as a share.
 */
class IntentPrecisionTest extends TestCase
{
    private IntentClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new IntentClassifier();
    }

    /**
     * @dataProvider genericPhrasings
     */
    public function test_a_generic_question_does_not_claim_an_intent(string $question): void
    {
        // These must reach the model planner, which can pick a directory or attendance
        // tool, rather than being routed at an agent that will answer something else.
        $this->assertSame(
            'unknown',
            $this->classifier->classify($question)->key,
            sprintf('"%s" should not match an intent on generic words alone.', $question)
        );
    }

    /**
     * @return array<int, array{0:string}>
     */
    public static function genericPhrasings(): array
    {
        return [
            ['Which students are in class 5?'],
            ['Who has low attendance?'],
            ['Show me the list'],
            ['What about this term?'],
        ];
    }

    /**
     * @dataProvider realIntents
     */
    public function test_a_question_with_real_evidence_still_matches(string $question, string $expected): void
    {
        $this->assertSame($expected, $this->classifier->classify($question)->key);
    }

    /**
     * @return array<int, array{0:string, 1:string}>
     */
    public static function realIntents(): array
    {
        return [
            ['Which students are at academic risk?', 'student_risk_scan'],
            ['Who is struggling?', 'student_risk_scan'],
            ['Why is Ravi at risk?', 'student_risk_explain'],
            ['What evidence supports this?', 'evidence_inspect'],
            ['What should the teacher do?', 'recommendation_advice'],
            ['Approve the recommendation.', 'approve_recommendation'],
            ['Reject it.', 'reject_recommendation'],
            ['What happened after approval?', 'workflow_status'],
            ['Did the intervention work?', 'outcome_status'],
            ['What has the system learned?', 'learning_effectiveness'],
            ['Confirm the admission for enquiry 21', 'admission_confirm'],
            ['Which admission enquiries are pending?', 'admission_enquiry_list'],
        ];
    }

    public function test_an_explicit_reference_survives_an_unmatched_intent(): void
    {
        // Whether a sentence resolves to an intent and whether it named a record are
        // separate questions. Discarding the reference because the surrounding words
        // were weak throws away the one certain thing in the message.
        $intent = $this->classifier->classify('Show me CASE-2026-000001');

        $this->assertSame('unknown', $intent->key);
        $this->assertSame('CASE-2026-000001', $intent->slot('case_reference'));
    }

    public function test_an_enquiry_number_is_extracted_for_the_admissions_flow(): void
    {
        $intent = $this->classifier->classify('Confirm the admission for enquiry 21');

        $this->assertSame('admission_confirm', $intent->key);
        $this->assertSame(21, $intent->slot('enquiry_id'));
    }

    public function test_the_matched_payload_reports_the_score_it_won_on(): void
    {
        // A reader comparing two turns needs to tell a decisive match from a bare one,
        // and confidence alone cannot show that.
        $matched = $this->classifier->classify('Which students are at academic risk?')->matched;

        $this->assertArrayHasKey('score', $matched);
        $this->assertArrayHasKey('minimum_score', $matched);
        $this->assertGreaterThanOrEqual($matched['minimum_score'], $matched['score']);
    }
}
