<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\TeachLearnIntelligence;
use App\Brain\Intelligence\TeachLearnSignalRules;
use App\Http\Controllers\Brain\BrainTeachLearnIntelligenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Teach/Learn Intelligence — the curriculum content catalogue.
 *
 * ── WHAT THESE TESTS ARE ACTUALLY GUARDING ──────────────────────────────────
 *
 * Teach/Learn is the module the registry's own header has cited since the first
 * audit as "routing to a category page with no intelligence in it". Giving it a
 * contract creates two risks that a rendering test would not catch:
 *
 *  1. FABRICATED ZEROS. Exactly one institute in this database publishes
 *     teaching content at any scale. If the module reports "782 courses, 0%
 *     covered" at the other fifty-six, it has turned "this school does not use
 *     the content library" into "this school's curriculum is empty" — the same
 *     defect HR Intelligence had to correct when it reported 0 leave days at
 *     schools with no leave register.
 *
 *  2. CROSS-TENANT BLEED. 16,192 of the 31,197 `content_master` rows belong to
 *     institute 1, and `courseController` deliberately widens a tenant's reads
 *     to include them when `school_setup.is_Lms = 'Y'`. Intelligence must not:
 *     `standard` is itself tenant-scoped, so that library describes institute
 *     1's classes and counting it would credit a school with material its
 *     learners cannot reach.
 *
 * Business figures are asserted as RELATIONSHIPS, never as literals. A test
 * that hard-codes "344 uncovered courses" fails the day somebody uploads a PDF,
 * which teaches the next reader to edit the number rather than read the
 * assertion.
 */
class TeachLearnIntelligenceTest extends TestCase
{
    /**
     * The institute-year this module was reconciled against: the only one in
     * this database publishing content at a scale a rate can describe.
     */
    private const RICH_TENANT = '195';

    private const RICH_YEAR = '2025';

    /** A real tenant with a full course catalogue and no content at all. */
    private const CATALOGUE_ONLY_TENANT = '254';

    /** @return array<string,mixed> */
    private function payload(string $tenant, ?string $syear = null): array
    {
        $uri = '/api/brain/'.$tenant.'/teach-learn/intelligence'.($syear ? '?syear='.$syear : '');
        $request = Request::create($uri, 'GET');

        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode(
            (new BrainTeachLearnIntelligenceController())->index($request)->getContent(),
            true,
        );
    }

    public function test_payload_carries_canonical_contract_keys(): void
    {
        $payload = $this->payload(self::RICH_TENANT, self::RICH_YEAR);

        foreach ([
            'tenantId', 'organization', 'source', 'academicYear', 'coverage',
            'freshness', 'execution', 'summary', 'position', 'breakdowns',
            'findings', 'priorities', 'recommendations', 'decisionTrail',
            'learning', 'dataQuality', 'ruleStatus',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "Payload is missing '{$key}'");
        }

        $this->assertSame(self::RICH_TENANT, $payload['tenantId']);
        $this->assertSame(self::RICH_YEAR, $payload['academicYear']['syear']);
    }

    /**
     * A school that does not use the content library is not a school with an
     * empty curriculum, and the difference has to survive into the payload.
     */
    public function test_catalogue_without_content_reports_unavailable_rather_than_zero_coverage(): void
    {
        $analytics = new TeachLearnIntelligence(self::CATALOGUE_ONLY_TENANT, self::RICH_YEAR);
        $shape = $analytics->shape();

        // Guard the premise. If this institute ever starts publishing, this
        // test is asserting nothing and should say so rather than pass quietly.
        $this->assertSame(
            0,
            $shape['items'],
            'Premise changed: tenant '.self::CATALOGUE_ONLY_TENANT.' now publishes content, so it no longer '
                .'exercises the catalogue-without-content state. Pick another tenant that holds none.',
        );
        $this->assertGreaterThan(
            0,
            $shape['courses'],
            'Premise changed: tenant '.self::CATALOGUE_ONLY_TENANT.' no longer has a course catalogue.',
        );

        $payload = $this->payload(self::CATALOGUE_ONLY_TENANT, self::RICH_YEAR);

        $this->assertFalse($payload['coverage']['available']);

        // The reason must cite the catalogue it DOES have — that is what makes
        // it an honest absence rather than a generic "no data".
        $this->assertStringContainsString(
            (string) $shape['courses'],
            $payload['coverage']['reason'],
            'An unavailable block must say what the institute actually holds.',
        );

        // NO FABRICATED ZERO AND NO FABRICATED RATE anywhere in the position
        // block. A coverage rate over no published content is undefined.
        foreach ($payload['position']['metrics'] ?? [] as $metric) {
            if (in_array($metric['key'], ['contentCoverage', 'coursesWithContent', 'items', 'formats', 'hidden'], true)) {
                $this->fail(
                    "Metric '{$metric['key']}' was reported for an institute with no published content. ".
                    'Only the course catalogue may be shown in the unavailable state.'
                );
            }
        }

        // NO FINDINGS. An unused module raises nothing rather than raising
        // everything about how unused it is.
        $this->assertSame(
            [],
            $payload['findings'],
            'A module an institute does not use must not raise findings against it.',
        );
    }

    /** Every finding, at every institute, rests on figures. */
    public function test_no_finding_is_raised_without_evidence(): void
    {
        foreach ([[self::RICH_TENANT, self::RICH_YEAR], [self::RICH_TENANT, '2026']] as [$tenant, $year]) {
            $payload = $this->payload($tenant, $year);

            foreach ($payload['findings'] as $finding) {
                $this->assertNotEmpty(
                    $finding['evidence'] ?? [],
                    "Finding '{$finding['id']}' carries no evidence. That is the line between intelligence and a ".
                    'chart caption.'
                );

                foreach ($finding['evidence'] as $point) {
                    $this->assertArrayHasKey('label', $point);
                    $this->assertArrayHasKey('value', $point);
                    $this->assertNotSame('', trim((string) $point['value']));
                }

                $this->assertNotEmpty($finding['whatHappened'] ?? '');
                $this->assertNotEmpty($finding['whyItMatters'] ?? '');
            }
        }
    }

    /**
     * The institute-1 shared library must never be counted as a tenant's own
     * coverage.
     *
     * This is checked at the figure rather than by reading the SQL: the number
     * of courses reported as covered may never exceed the number that are
     * covered by the tenant's OWN rows, computed here independently.
     */
    public function test_shared_library_is_not_counted_as_tenant_coverage(): void
    {
        $analytics = new TeachLearnIntelligence(self::RICH_TENANT, self::RICH_YEAR);
        $shape = $analytics->shape();

        $ownOnly = (int) DB::table('sub_std_map as s')
            ->join('content_master as c', function ($join) {
                $join->on('c.standard_id', '=', 's.standard_id')
                    ->on('c.subject_id', '=', 's.subject_id')
                    ->where('c.sub_institute_id', '=', self::RICH_TENANT)
                    ->where('c.syear', '=', self::RICH_YEAR);
            })
            ->where('s.sub_institute_id', self::RICH_TENANT)
            ->where('s.status', 1)
            ->distinct()
            ->count(DB::raw('CONCAT(s.standard_id, "-", s.subject_id)'));

        $this->assertSame(
            $ownOnly,
            $shape['coursesWithContent'],
            'Reported coverage does not equal coverage from this tenant’s own content rows, which means another '
                .'institute’s library has leaked into the figure.',
        );

        // And the content total itself is strictly this tenant's.
        $tenantItems = (int) DB::table('content_master')
            ->where('sub_institute_id', self::RICH_TENANT)
            ->where('syear', self::RICH_YEAR)
            ->count();

        $this->assertSame($tenantItems, $shape['items']);
    }

    /**
     * The year in the header scopes the CONTENT and not the catalogue, because
     * `sub_std_map` has no `syear` column. Both halves of that have to hold.
     */
    public function test_year_scopes_content_but_not_the_catalogue(): void
    {
        $a = new TeachLearnIntelligence(self::RICH_TENANT, self::RICH_YEAR);
        $b = new TeachLearnIntelligence(self::RICH_TENANT, '2026');

        // The catalogue is the same standing list in both years.
        $this->assertSame(
            $a->shape()['courses'],
            $b->shape()['courses'],
            'The course catalogue changed between years, but sub_std_map carries no syear — so either the scoping '
                .'is wrong or the schema gained a year column and this module needs to use it.',
        );

        // The content differs between them, which is what the year is for.
        $this->assertNotSame(
            $a->shape()['items'],
            $b->shape()['items'],
            'Premise changed: these two years now hold the same number of items, so this no longer demonstrates '
                .'year scoping. Pick two years that differ.',
        );
    }

    /**
     * No chapter-level figure may appear anywhere, because the chapter
     * references do not resolve.
     */
    public function test_reports_no_chapter_figures_while_chapter_references_do_not_resolve(): void
    {
        $analytics = new TeachLearnIntelligence(self::RICH_TENANT, self::RICH_YEAR);
        $shape = $analytics->shape();

        $this->assertGreaterThan(
            0,
            $shape['orphanChapter'],
            'Premise changed: chapter references now resolve at this institute. If chapter_master has been '
                .'populated, this module can finally report chapter-level figures — and should.',
        );

        // The only breakdown keys allowed are the three that do not rest on a
        // chapter join.
        $payload = $this->payload(self::RICH_TENANT, self::RICH_YEAR);
        $keys = array_column($payload['breakdowns'], 'key');

        $this->assertSame(['by_class', 'by_format', 'by_year'], $keys);
    }

    /**
     * Rules that do not fire are RECORDED as having been checked, so a quiet
     * screen is distinguishable from an unchecked one.
     */
    public function test_rule_status_records_every_rule_whether_or_not_it_fired(): void
    {
        $analytics = new TeachLearnIntelligence(self::RICH_TENANT, self::RICH_YEAR);
        $raised = (new TeachLearnSignalRules($analytics, self::RICH_YEAR))->run();

        $this->assertCount(5, $raised['ruleStatus']);

        foreach ($raised['ruleStatus'] as $status) {
            $this->assertTrue($status['checked']);
            $this->assertArrayHasKey('raised', $status);
            $this->assertNotEmpty($status['label']);
        }

        // At least one rule fired and at least one did not, at the institute
        // this module was reconciled against — a module where everything or
        // nothing fires is not discriminating.
        $fired = array_column($raised['ruleStatus'], 'raised');
        $this->assertContains(true, $fired);
        $this->assertContains(false, $fired);
    }

    /**
     * Every rule this module can raise has an entry in `RuleCatalogue`, or its
     * recommendation reaches the reader with no explanation attached.
     */
    public function test_every_rule_is_registered_in_the_catalogue(): void
    {
        $analytics = new TeachLearnIntelligence(self::RICH_TENANT, self::RICH_YEAR);
        $raised = (new TeachLearnSignalRules($analytics, self::RICH_YEAR))->run();

        foreach ($raised['ruleStatus'] as $status) {
            $key = \App\Brain\Intelligence\ModuleSignalBridge::ruleKey('teach-learn', $status['key']);

            $this->assertNotNull(
                \App\Brain\Intelligence\RuleCatalogue::for($key),
                "Rule '{$key}' has no RuleCatalogue entry, so any recommendation it produces carries no cause."
            );
        }
    }
}
