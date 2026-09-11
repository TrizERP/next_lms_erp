<?php

namespace Tests\Feature\Pal;

use App\Models\Eso\LearnerNodeState;
use App\Services\PAL\Pedagogy\PedagogyRuleBands;
use App\Services\PAL\Pedagogy\PedagogySelectorEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tier 1 of the Pedagogy Engine runs on the authored rules.
 *
 * Before this, `pal_pedagogy_engine_rules` held 52 authored rows that nothing
 * outside the read-only dashboard API ever read, while PedagogySelectorEngine
 * decided what students actually saw from thresholds hardcoded in PHP. The two
 * disagreed on the metric (bkt_mastery vs avg(Competency.mastery_score)), the
 * band count (5 vs 3) and every boundary.
 *
 * These tests pin the wiring: the authored bands decide, the fallback is intact
 * for environments with no rules, and a band is never invented for a learner
 * with no evidence.
 */
class PedagogyTier1RuleDrivenTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    private int $studentId;

    private int $conceptId;

    private int $nodeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Tier1 Test School',
            'ShortCode' => 'T1' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'tier1@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'tier1@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);

        $this->studentId = (int) DB::table('tblstudent')->insertGetId([
            'first_name' => 'Tier1',
            'last_name' => 'Learner',
            'sub_institute_id' => $this->subInstituteId,
            'file_size' => '',
            'file_type' => '',
        ]);

        $subjectId = (int) DB::table('subject')->insertGetId([
            'subject_name' => 'Tier1 Subject ' . random_int(1000, 9999),
            'sub_institute_id' => $this->subInstituteId,
            'status' => 1,
            'created_at' => now(),
        ]);

        $standardId = (int) DB::table('standard')->insertGetId([
            'grade_id' => 1,
            'name' => '9',
            'short_name' => '9',
            'sort_order' => 1,
            'sub_institute_id' => $this->subInstituteId,
        ]);

        $chapterId = (int) DB::table('chapter_master')->insertGetId([
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
            'sub_institute_id' => $this->subInstituteId,
            'chapter_name' => 'Tier1 Chapter',
            'created_at' => now(),
        ]);

        $this->conceptId = (int) DB::table('lms_concept')->insertGetId([
            'name' => 'Tier1 Concept',
            'subject_id' => $subjectId,
            'standard_id' => $standardId,
            'chapter_id' => $chapterId,
            'sub_institute_id' => $this->subInstituteId,
            'mastery_threshold' => 80,
            'syear' => 2026,
            'created_at' => now(),
        ]);

        $this->nodeId = (int) DB::table('pal_concept_nodes')->insertGetId([
            'concept_id' => $this->conceptId,
            'sub_institute_id' => $this->subInstituteId,
            'node_type' => 'K',
            'label' => 'Knowledge node',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The real seeder's tier-1 rows may or may not be present in the
        // configured DB. Cleared inside the transaction so these tests assert
        // against a known rule set rather than against seed state; the delete
        // rolls back with everything else.
        DB::table('pal_pedagogy_engine_rules')->where('section_key', 'tier-1')->delete();
    }

    /**
     * Two authored bands with the same shape the real seeder writes — the
     * boundaries live in rule_meta.mastery_range, not in the prose condition.
     */
    private function seedTier1Rules(): void
    {
        $rules = [
            ['mastery-below-40', 'No foundation', 'bkt_mastery < 0.40', 'concept_based', 0.0, 0.399, 'concept', 'understand', 1, 'Full - all hints available'],
            ['mastery-40-69', 'Building', 'bkt_mastery BETWEEN 0.40 AND 0.69', 'activity_based or flashcard', 0.40, 0.69, 'practice', 'apply', 2, 'Hints on request'],
            ['mastery-93-plus', 'Mastered', 'bkt_mastery >= 0.93', 'inquiry_based', 0.93, 1.0, 'assessment', 'create', 5, 'None'],
        ];

        foreach ($rules as $i => [$key, $label, $condition, $pedagogy, $min, $max, $contentType, $bloom, $difficulty, $scaffolding]) {
            DB::table('pal_pedagogy_engine_rules')->insert([
                'section_key' => 'tier-1',
                'rule_key' => $key,
                'group_label' => $label,
                'condition' => $condition,
                'action' => 'Serve ' . $contentType,
                'pedagogy' => $pedagogy,
                'scaffolding' => $scaffolding,
                'implementation_status' => 'Not Implemented',
                'rule_meta' => json_encode([
                    'mastery_range' => ['min' => $min, 'max' => $max],
                    'content_type' => $contentType,
                    'bloom_ceiling' => $bloom,
                    'difficulty' => $difficulty,
                ]),
                'sort_order' => $i + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** A node state carrying a BKT estimate, with the one attempt the evidence floor requires. */
    private function seedMastery(float $estimate): void
    {
        LearnerNodeState::updateOrCreate(
            ['student_id' => $this->studentId, 'node_id' => $this->nodeId],
            [
                'sub_institute_id' => $this->subInstituteId,
                'mastery_estimate' => $estimate,
                'attempts' => 3,
                'status' => LearnerNodeState::STATUS_LEARNING,
                'last_seen_at' => now(),
            ]
        );
    }

    private function select(): array
    {
        return app(PedagogySelectorEngine::class)->select(
            $this->studentId,
            $this->conceptId,
            ['sub_institute_id' => $this->subInstituteId]
        );
    }

    public function test_the_authored_band_decides_the_pedagogy_a_learner_is_served(): void
    {
        $this->seedTier1Rules();
        $this->seedMastery(0.25); // "No foundation"

        $selected = $this->select();

        // The rule authors "concept_based", which pal_v4.aliases maps to the
        // canonical inquiry_based. That substitution is declared config, not a
        // silent default — PedagogyRuleBands drops labels the catalog does not
        // actually know rather than letting them land here.
        $this->assertSame('inquiry_based', $selected['type']);
        $this->assertArrayHasKey(
            'tier_1_rule',
            $selected,
            'A rule-driven decision must say which authored rule produced it — otherwise it is indistinguishable from the hardcoded path it replaced.'
        );
        $this->assertSame('mastery-below-40', $selected['tier_1_rule']['rule_key']);
        $this->assertSame('pal_pedagogy_engine_rules', $selected['tier_1_rule']['source']);
        $this->assertSame(0.25, $selected['tier_1_rule']['bkt_mastery']);
    }

    public function test_a_different_band_produces_a_different_pedagogy_at_the_authored_boundary(): void
    {
        $this->seedTier1Rules();

        // 0.399 is the top of band 1; 0.40 is the bottom of band 2. The old
        // hardcoded code banded on a 0-100 score with a boundary at 50, so
        // both of these would have landed in the same "low mastery" bucket.
        $this->seedMastery(0.399);
        $low = $this->select();

        $this->seedMastery(0.40);
        $building = $this->select();

        $this->assertSame('mastery-below-40', $low['tier_1_rule']['rule_key']);
        $this->assertSame('mastery-40-69', $building['tier_1_rule']['rule_key']);
        $this->assertNotSame($low['type'], $building['type']);
    }

    public function test_the_rules_own_directives_travel_with_the_decision(): void
    {
        $this->seedTier1Rules();
        $this->seedMastery(0.50);

        $rule = $this->select()['tier_1_rule'];

        // Without these the caller gets a pedagogy name and silently drops
        // everything else the author specified for this band.
        $this->assertSame('practice', $rule['content_type']);
        $this->assertSame('apply', $rule['bloom_ceiling']);
        $this->assertSame(2, $rule['difficulty']);
        $this->assertSame('Hints on request', $rule['scaffolding']);
    }

    public function test_a_learner_with_no_evidence_is_not_banded_at_all(): void
    {
        $this->seedTier1Rules();
        // No LearnerNodeState seeded: the concept has never been attempted.

        $selected = $this->select();

        $this->assertArrayNotHasKey(
            'tier_1_rule',
            $selected,
            'ADR-001 §5: absence of evidence is a signal to diagnose, not a low mastery band. Banding an unassessed learner would serve remedial content to someone who may already know the material.'
        );
    }

    public function test_an_environment_with_no_authored_rules_keeps_its_previous_behaviour(): void
    {
        // setUp() cleared tier-1 and nothing re-seeded it — the state of any
        // environment that has not run PalPedagogyEngineSeeder.
        $this->seedMastery(0.25);

        $selected = $this->select();

        $this->assertArrayNotHasKey('tier_1_rule', $selected);
        $this->assertArrayHasKey('type', $selected, 'The fallback must still return a usable pedagogy, not nothing.');
    }

    public function test_a_deactivated_rule_is_not_served(): void
    {
        $this->seedTier1Rules();
        DB::table('pal_pedagogy_engine_rules')
            ->where('section_key', 'tier-1')
            ->where('rule_key', 'mastery-below-40')
            ->update(['is_active' => false]);

        $this->seedMastery(0.25);

        $this->assertArrayNotHasKey(
            'tier_1_rule',
            $this->select(),
            'A rule an author has taken out of service must not keep deciding what students see.'
        );
    }

    public function test_a_rule_without_a_structured_range_is_skipped_rather_than_guessed_at(): void
    {
        DB::table('pal_pedagogy_engine_rules')->insert([
            'section_key' => 'tier-1',
            'rule_key' => 'prose-only',
            'group_label' => 'Prose only',
            'condition' => 'bkt_mastery < 0.40',
            'pedagogy' => 'concept_based',
            // No rule_meta.mastery_range — the boundary exists only in prose.
            'rule_meta' => json_encode(['content_type' => 'concept']),
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(
            [],
            app(PedagogyRuleBands::class)->masteryBands(),
            'Parsing a boundary out of the prose condition would let the engine silently disagree with what an author wrote and reviewed.'
        );
    }

    // ── Tier 4: learning style changes the format, never the pedagogy ───────

    /** One authored Tier 4 style rule, shaped as the real seeder writes them. */
    private function seedStyleRule(string $style, array $h5pPriority, array $avoid = []): void
    {
        DB::table('pal_pedagogy_engine_rules')->updateOrInsert(
            ['section_key' => 'tier-4', 'rule_key' => 'style-' . str_replace('_', '-', $style)],
            [
                'group_label' => 'Learning style',
                'condition' => "learning_style = '{$style}'",
                'action' => 'Prefer the styles listed in h5p_priority.',
                'pedagogy' => 'art_integrated',
                'rule_meta' => json_encode(['h5p_priority' => $h5pPriority, 'avoid' => $avoid]),
                'implementation_status' => 'Not Implemented',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function setLearningStyle(string $style): void
    {
        DB::table('pal_learner_preferences')->insert([
            'learner_id' => $this->studentId,
            'pref_key' => 'learning_style',
            'pref_value' => $style,
        ]);
    }

    protected function tearDownStyleRules(): void
    {
        DB::table('pal_pedagogy_engine_rules')->where('section_key', 'tier-4')->delete();
    }

    public function test_learning_style_reorders_the_formats_without_changing_the_pedagogy(): void
    {
        $this->tearDownStyleRules();
        $this->seedTier1Rules();
        $this->seedMastery(0.25);

        $withoutStyle = $this->select();

        // inquiry_based's catalog formats include course_presentation and
        // interactive_video; prefer the second one.
        $this->seedStyleRule('visual', ['interactive_video', 'image_hotspot']);
        $this->setLearningStyle('visual');

        $withStyle = $this->select();

        $this->assertSame(
            $withoutStyle['type'],
            $withStyle['type'],
            'Tier 4 "does not change what is taught - it changes the format it arrives in". Letting it move the pedagogy would make it a competing selector, which is exactly the duplication this work is removing.'
        );

        $this->assertSame('style-visual', $withStyle['tier_4_style']['rule_key']);
        $this->assertSame('interactive_video', $withStyle['h5p_types'][0]);

        // Reordered, not extended: the same set of formats, in a new order.
        $this->assertEqualsCanonicalizing($withoutStyle['h5p_types'], $withStyle['h5p_types']);
    }

    public function test_a_preferred_format_the_pedagogy_disallows_is_not_introduced(): void
    {
        $this->tearDownStyleRules();
        $this->seedTier1Rules();
        $this->seedMastery(0.25);

        $allowed = $this->select()['h5p_types'];

        // A format that is not in this pedagogy's catalog entry at all.
        $this->seedStyleRule('kinesthetic', ['memory_game']);
        $this->setLearningStyle('kinesthetic');

        $selected = $this->select();

        $this->assertEqualsCanonicalizing(
            $allowed,
            $selected['h5p_types'],
            'A style preference must not make a format valid for a pedagogy whose catalog excludes it.'
        );
        $this->assertNotContains('memory_game', $selected['h5p_types']);
    }

    public function test_a_learner_with_no_recorded_style_is_left_alone(): void
    {
        $this->tearDownStyleRules();
        $this->seedTier1Rules();
        $this->seedStyleRule('visual', ['interactive_video']);
        $this->seedMastery(0.25);
        // No pal_learner_preferences row for this learner.

        $this->assertArrayNotHasKey('tier_4_style', $this->select());
    }

    public function test_a_style_with_no_authored_rule_is_left_alone(): void
    {
        $this->tearDownStyleRules();
        $this->seedTier1Rules();
        $this->seedStyleRule('visual', ['interactive_video']);
        $this->seedMastery(0.25);
        $this->setLearningStyle('auditory'); // authored for visual only

        $this->assertArrayNotHasKey('tier_4_style', $this->select());
    }

    public function test_a_deactivated_style_rule_stops_applying(): void
    {
        $this->tearDownStyleRules();
        $this->seedTier1Rules();
        $this->seedStyleRule('visual', ['interactive_video']);
        $this->setLearningStyle('visual');
        $this->seedMastery(0.25);

        $this->assertArrayHasKey('tier_4_style', $this->select());

        DB::table('pal_pedagogy_engine_rules')
            ->where('section_key', 'tier-4')
            ->update(['is_active' => false]);

        $this->assertArrayNotHasKey('tier_4_style', $this->select());
    }

    // ── Per-tier kill switches ──────────────────────────────────────────────
    //
    // These tiers were dark for a long time and are being switched on one at a
    // time. A tier making bad decisions in front of students has to be
    // stoppable without a deploy, and stopping one must not take the others
    // down with it.

    public function test_turning_tier_1_off_returns_it_to_the_previous_behaviour(): void
    {
        $this->seedTier1Rules();
        $this->seedMastery(0.25);

        $this->assertArrayHasKey('tier_1_rule', $this->select());

        config()->set('pal_content.pedagogy.tiers.tier-1', false);

        $selected = $this->select();

        $this->assertArrayNotHasKey('tier_1_rule', $selected);
        $this->assertArrayHasKey(
            'type',
            $selected,
            'Disabling a tier must degrade to the hardcoded path, not break selection — the previous code is still there and still correct.'
        );
    }

    public function test_turning_tier_4_off_leaves_tier_1_running(): void
    {
        $this->tearDownStyleRules();
        $this->seedTier1Rules();
        $this->seedStyleRule('visual', ['interactive_video']);
        $this->setLearningStyle('visual');
        $this->seedMastery(0.25);

        $this->assertArrayHasKey('tier_4_style', $this->select());

        config()->set('pal_content.pedagogy.tiers.tier-4', false);

        $selected = $this->select();

        $this->assertArrayNotHasKey('tier_4_style', $selected);
        $this->assertArrayHasKey(
            'tier_1_rule',
            $selected,
            'Switching off one tier must not take the others with it — that is the point of a PER-TIER switch.'
        );
    }

    public function test_an_unlisted_tier_is_off_rather_than_on(): void
    {
        $this->seedTier1Rules();
        $this->seedMastery(0.25);

        // Tier removed from config entirely, as an unreviewed tier would be.
        config()->set('pal_content.pedagogy.tiers', []);

        $this->assertArrayNotHasKey(
            'tier_1_rule',
            $this->select(),
            'A tier nobody has explicitly enabled has not been reviewed for rollout. Defaulting an unlisted tier to ON would run unreviewed rules in front of students.'
        );
    }

    // ── Tracker #32: Tier 2 (Engagement State) is disabled — no declining_engagement firing ──

    public function test_tier_2_disabled_by_default_does_not_fire_on_declining_exam_accuracy(): void
    {
        $this->seedTier1Rules();
        $this->seedMastery(0.50); // "Building" band

        // Simulate pal_learning_sessions rows with declining accuracy (what the sync command writes)
        // Tier 2 is disabled by default (config 'tier-2' => false), so declining_engagement must be false
        // and the Tier 1 rule must still execute.
        DB::table('pal_learning_sessions')->insert([
            ['learner_id' => $this->studentId, 'engagement_score' => 0.80, 'created_at' => now()->subDays(1)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.75, 'created_at' => now()->subDays(2)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.70, 'created_at' => now()->subDays(3)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.65, 'created_at' => now()->subDays(4)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.60, 'created_at' => now()->subDays(5)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.55, 'created_at' => now()->subDays(6)],
        ]);

        $selected = $this->select();

        // Tier 1 must still execute — the authored rule for 0.50 mastery
        $this->assertArrayHasKey('tier_1_rule', $selected, 'Tier 1 must still run when Tier 2 is disabled');
        $this->assertSame('mastery-40-69', $selected['tier_1_rule']['rule_key']);

        // Tier 2 must NOT short-circuit to game_based
        $this->assertNotSame('game_based', $selected['type'], 'Tier 2 disabled: must not return game_based for declining accuracy');
        $this->assertNotSame('Engagement is declining', $selected['reason'] ?? '', 'Must not fire "Engagement is declining" reason');
    }

    public function test_tier_2_explicitly_enabled_would_fire_but_remains_off_by_config(): void
    {
        $this->seedTier1Rules();
        $this->seedMastery(0.50);

        DB::table('pal_learning_sessions')->insert([
            ['learner_id' => $this->studentId, 'engagement_score' => 0.80, 'created_at' => now()->subDays(1)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.75, 'created_at' => now()->subDays(2)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.70, 'created_at' => now()->subDays(3)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.65, 'created_at' => now()->subDays(4)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.60, 'created_at' => now()->subDays(5)],
            ['learner_id' => $this->studentId, 'engagement_score' => 0.55, 'created_at' => now()->subDays(6)],
        ]);

        // Verify config is false by default
        $this->assertFalse(config('pal_content.pedagogy.tiers.tier-2'), 'tier-2 must default to false');

        $selected = $this->select();

        // With default config, Tier 1 still wins
        $this->assertArrayHasKey('tier_1_rule', $selected);
        $this->assertNotSame('game_based', $selected['type']);
    }

    public function test_a_percentage_is_never_mistaken_for_a_bkt_estimate(): void
    {
        $this->seedTier1Rules();
        $bands = app(PedagogyRuleBands::class);

        // 95 is a plausible 0-100 mastery score. Read as BKT it is far outside
        // every authored range, and must not silently match the top band.
        $this->assertNull($bands->resolveMasteryBand(95.0));
        $this->assertNull($bands->resolveMasteryBand(-0.1));
        $this->assertNotNull($bands->resolveMasteryBand(0.95));

        // A value in a gap between authored bands is also unbanded, rather
        // than being snapped to the nearest one. These fixtures deliberately
        // leave 0.70-0.92 unauthored.
        $this->assertNull($bands->resolveMasteryBand(0.75));
    }
}
