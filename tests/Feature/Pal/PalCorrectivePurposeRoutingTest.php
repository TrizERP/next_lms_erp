<?php

namespace Tests\Feature\Pal;

use App\Models\PAL\ContentMetadata;
use App\Services\PAL\Content\VariantRouterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Corrective Micro-Lesson step chooses by PURPOSE, not only by format.
 *
 * Before this, the reroute-on-failure ladder filtered by content_type and "a
 * different format". That finds a different modality of the same thing — which
 * is how a learner who has just failed can be re-served the practice item they
 * failed, as a video. `learning_purpose` is what makes "content whose job is to
 * re-explain" expressible, and this is where it is applied.
 *
 * The estate is unclassified today (learning_purpose is nullable and was
 * deliberately not backfilled), so the fallback behaviour matters as much as
 * the filtering, and is tested here in as much detail.
 */
class PalCorrectivePurposeRoutingTest extends TestCase
{
    use DatabaseTransactions;

    private VariantRouterService $router;

    private int $subInstituteId = 0;

    private int $conceptId;

    private int $learnerId = 991001;

    private int $contentCounter = 7700000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->router = app(VariantRouterService::class);
        $this->conceptId = random_int(880000, 889999);
    }

    /** One servable content row on the concept, with a given purpose and format. */
    private function content(?string $purpose, string $format = 'text_diagram'): ContentMetadata
    {
        return ContentMetadata::create([
            'content_master_id' => $this->contentCounter++,
            'sub_institute_id' => $this->subInstituteId,
            'concept_ref_id' => $this->conceptId,
            'content_type' => 'concept',
            'learning_purpose' => $purpose,
            'format' => $format,
            'quality_status' => 'approved',
        ]);
    }

    /** @param array<string,mixed> $context */
    private function route(array $context = []): array
    {
        return $this->router->nextVariant(
            $this->learnerId,
            $this->conceptId,
            $this->subInstituteId,
            $context
        );
    }

    public function test_a_corrective_reroute_prefers_content_whose_job_is_to_re_explain(): void
    {
        $practice = $this->content('practice');
        $explain = $this->content('explain');

        $result = $this->route(['corrective' => true, 'failed_format' => 'video']);

        $this->assertTrue($result['purpose_filtered']);
        $this->assertSame(
            (int) $explain->content_master_id,
            $result['content']['content_master_id'],
            'A learner who has just failed needs the concept re-explained. Serving the practice item again — in any format — is a different modality of the same wrong thing.'
        );
        $this->assertNotSame((int) $practice->content_master_id, $result['content']['content_master_id']);
    }

    public function test_an_assessment_item_is_never_chosen_as_a_corrective(): void
    {
        $this->content('assess');
        $this->content('recall');
        $remediate = $this->content('remediate');

        $result = $this->route(['corrective' => true]);

        $this->assertSame(
            (int) $remediate->content_master_id,
            $result['content']['content_master_id'],
            'Serving an assessment item as remediation shows the learner the thing being measured.'
        );
    }

    public function test_enrichment_is_not_offered_to_a_learner_who_just_failed(): void
    {
        $enrich = $this->content('enrich');
        $demonstrate = $this->content('demonstrate');

        $result = $this->route(['corrective' => true]);

        $this->assertSame((int) $demonstrate->content_master_id, $result['content']['content_master_id']);
        $this->assertNotSame((int) $enrich->content_master_id, $result['content']['content_master_id']);
    }

    public function test_an_unclassified_estate_still_reroutes_and_says_it_was_not_filtered(): void
    {
        // Every row NULL — the live estate today, since learning_purpose was
        // deliberately not backfilled.
        $this->content(null);
        $this->content(null, 'video');

        $result = $this->route(['corrective' => true]);

        $this->assertNotNull(
            $result['content'],
            'A hard purpose filter would empty the reroute ladder for practically every concept on the live estate. The preference has to degrade.'
        );
        $this->assertFalse(
            $result['purpose_filtered'],
            'Falling back is acceptable; doing so silently is not — the caller cannot otherwise tell a corrective micro-lesson from any other content on the concept.'
        );
    }

    public function test_a_partially_classified_concept_uses_what_classification_exists(): void
    {
        $this->content(null);
        $explain = $this->content('explain');

        $result = $this->route(['corrective' => true]);

        $this->assertTrue($result['purpose_filtered']);
        $this->assertSame((int) $explain->content_master_id, $result['content']['content_master_id']);
    }

    public function test_an_explicitly_named_purpose_binds_and_does_not_widen(): void
    {
        $this->content('explain');
        $this->content(null);

        // 'transfer' exists in the vocabulary but no content carries it here.
        $result = $this->route(['purpose' => 'transfer']);

        $this->assertNull(
            $result['content'],
            'A caller that asked for one purpose and is handed another has been given the wrong thing. Empty is the honest answer.'
        );

        // "Nothing authored for this purpose" is NOT "every variant has been
        // served", and must not raise a teacher alert. learning_purpose is
        // deliberately unbackfilled, so reporting exhaustion here would escalate
        // to a teacher on essentially every strict request made today — telling
        // them a learner had run out of content that was never classified.
        $this->assertFalse($result['exhausted']);
        $this->assertFalse($result['teacher_alert']);
        $this->assertSame('no_content_for_purpose', $result['reason']);
        $this->assertTrue($result['purpose_filtered']);
    }

    public function test_an_unregistered_purpose_returns_nothing_rather_than_anything(): void
    {
        $this->content('explain');
        $this->content('practice');

        $result = $this->route(['purpose' => 'revise']);

        $this->assertNull(
            $result['content'],
            'A typo must not quietly widen the search back to the whole concept — that turns a narrow request into "serve anything".'
        );
    }

    public function test_routing_without_a_purpose_context_is_unchanged(): void
    {
        $only = $this->content('practice');

        $result = $this->route();

        $this->assertFalse($result['purpose_filtered']);
        $this->assertSame([], $result['purposes_requested']);
        $this->assertSame(
            (int) $only->content_master_id,
            $result['content']['content_master_id'],
            'Callers that never asked about purpose must behave exactly as before.'
        );
    }

    public function test_the_corrective_set_comes_from_the_vocabulary_not_the_caller(): void
    {
        $this->content('explain');

        $result = $this->route(['corrective' => true]);

        $this->assertEqualsCanonicalizing(
            ['understand', 'prerequisite', 'explain', 'demonstrate', 'remediate'],
            $result['purposes_requested'],
            'The membership rule belongs in config/pal_content.php, so every caller asking for "a corrective" gets the same answer.'
        );
    }

    protected function tearDown(): void
    {
        DB::table('pal_content_metadata')->where('concept_ref_id', $this->conceptId)->delete();

        parent::tearDown();
    }
}
