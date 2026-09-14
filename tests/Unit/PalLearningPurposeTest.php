<?php

namespace Tests\Unit;

use App\Services\PAL\Content\PalVocabulary;
use Tests\TestCase;

/**
 * The Learning Purpose vocabulary.
 *
 * content_type says what a thing IS and format says what FORM it takes; neither
 * says what it is FOR. Without that, PAL's Corrective Micro-Lesson step can
 * only ask "what else exists on this concept?", and the answer to that question
 * includes the assessment item the learner just failed.
 *
 * The `corrective` split is the part that earns its keep, so most of these
 * tests are about what is EXCLUDED from it.
 */
class PalLearningPurposeTest extends TestCase
{
    public function test_the_vocabulary_is_the_closed_set_the_content_model_specifies(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                'understand', 'prerequisite', 'explain', 'demonstrate',
                'practice', 'apply', 'transfer',
                'remediate', 'enrich',
                'recall', 'assess',
            ],
            PalVocabulary::learningPurposes()
        );
    }

    public function test_an_unregistered_purpose_is_a_write_failure_not_a_new_category(): void
    {
        $errors = PalVocabulary::validate(['learning_purpose' => 'revise']);

        $this->assertNotEmpty(
            $errors,
            'CONTENT LAW: an unregistered value is a write failure. Letting "revise" through is how one vocabulary becomes four hundred spellings.'
        );
        $this->assertStringContainsString('learning_purpose', $errors[0]);
    }

    public function test_a_registered_purpose_validates_and_an_absent_one_is_not_required(): void
    {
        $this->assertSame([], PalVocabulary::validate(['learning_purpose' => 'remediate']));

        // Partial updates must not be forced to supply it, and existing rows
        // predate the vocabulary — NULL means "not yet classified".
        $this->assertSame([], PalVocabulary::validate([]));
        $this->assertSame([], PalVocabulary::validate(['learning_purpose' => null]));
    }

    public function test_assessment_purposes_are_never_servable_as_a_corrective_micro_lesson(): void
    {
        $this->assertFalse(
            PalVocabulary::isCorrectivePurpose('assess'),
            'Serving an assessment item as remediation shows the learner the very thing being measured.'
        );
        $this->assertFalse(PalVocabulary::isCorrectivePurpose('recall'));

        $corrective = PalVocabulary::correctiveLearningPurposes();
        $this->assertNotContains('assess', $corrective);
        $this->assertNotContains('recall', $corrective);
    }

    public function test_enrichment_is_not_remediation(): void
    {
        $this->assertFalse(
            PalVocabulary::isCorrectivePurpose('enrich'),
            'A learner who has just failed needs the concept again, not an extension beyond it.'
        );
    }

    public function test_more_practice_is_not_a_corrective_explanation(): void
    {
        // The failure mode this prevents: answering "they got it wrong" with
        // another item of the same kind rather than a different explanation.
        $this->assertFalse(PalVocabulary::isCorrectivePurpose('practice'));
        $this->assertFalse(PalVocabulary::isCorrectivePurpose('apply'));
        $this->assertFalse(PalVocabulary::isCorrectivePurpose('transfer'));
    }

    public function test_the_corrective_set_is_what_the_micro_lesson_step_can_choose_from(): void
    {
        $this->assertEqualsCanonicalizing(
            ['understand', 'prerequisite', 'explain', 'demonstrate', 'remediate'],
            PalVocabulary::correctiveLearningPurposes()
        );
    }

    public function test_prerequisite_content_counts_as_corrective(): void
    {
        $this->assertTrue(
            PalVocabulary::isCorrectivePurpose('prerequisite'),
            'Sometimes the honest answer to a failure is that the gap is upstream of the concept being taught, so prerequisite material has to be reachable from the corrective step.'
        );
    }

    public function test_each_purpose_reports_the_phase_of_the_loop_it_belongs_to(): void
    {
        $this->assertSame('teach', PalVocabulary::purposePhase('explain'));
        $this->assertSame('practice', PalVocabulary::purposePhase('apply'));
        $this->assertSame('support', PalVocabulary::purposePhase('remediate'));
        $this->assertSame('assess', PalVocabulary::purposePhase('assess'));
        $this->assertNull(PalVocabulary::purposePhase('not_a_purpose'));
    }

    public function test_every_registered_purpose_is_fully_specified(): void
    {
        foreach (config('pal_content.learning_purposes') as $key => $purpose) {
            $this->assertArrayHasKey('label', $purpose, "{$key} has no label");
            $this->assertArrayHasKey('description', $purpose, "{$key} has no description");
            $this->assertArrayHasKey('phase', $purpose, "{$key} has no phase");
            $this->assertArrayHasKey('corrective', $purpose, "{$key} does not say whether it is corrective");
            $this->assertIsBool($purpose['corrective'], "{$key}'s corrective flag must be a real boolean, not a truthy string");
            $this->assertContains($purpose['phase'], ['teach', 'practice', 'assess', 'support'], "{$key} has an unknown phase");
        }
    }
}
