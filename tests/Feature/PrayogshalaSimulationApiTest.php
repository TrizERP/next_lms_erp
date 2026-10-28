<?php

namespace Tests\Feature;

use App\Http\Middleware\LimitSimulationBody;
use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\SimulationTestHelpers;
use Tests\TestCase;

/**
 * Prayogshala Simulation Learning: who sees what, who may write, the links to concepts, and the simulation lifecycle (ported from hp_erp's
 * LmsSimulationApiTest, with K12's tenancy instead of hp_erp's per-organisation gallery and rights matrix).
 *
 * RUNS ON IN-MEMORY SQLITE (see SimulationTestHelpers): the default connection is swapped in setUp and the test refuses to continue unless that took.
 */
class PrayogshalaSimulationApiTest extends TestCase
{
    use SimulationTestHelpers;

    private const WRITES = ['store', 'update', 'destroy', 'restore', 'putLinks', 'reviewLink', 'uploadAsset', 'limits', 'versions', 'concepts'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSimulations();
    }

    protected function tearDown(): void
    {
        $this->tearDownSimulations();
        parent::tearDown();
    }

    /** One call of a write action with plausible arguments, as `$tenant`. */
    private function write(string $action, int $tenant, string $profile = 'Teacher', bool $student = false): array
    {
        $sim = DB::table('lms_simulation')->first();
        $id = $sim->id ?? 1;
        $args = match ($action) {
            'update', 'destroy', 'restore', 'putLinks', 'versions' => [$id],
            'reviewLink' => [$id, 1],
            default => [],
        };
        $body = match ($action) {
            'store' => json_encode(['spec' => json_decode($this->specText('new-one'))]),
            'update' => json_encode(['category' => 'x']),
            'putLinks' => ['links' => [['concept_id' => 5]]],
            'reviewLink' => ['status' => 'published'],
            default => null,
        };

        return $this->act($action, $tenant, $args, $body, [], $profile, $student);
    }

    // ── who may write ────────────────────────────────────────────────────────

    public function test_a_non_platform_tenant_gets_403_on_every_write_and_changes_nothing(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        $this->linkTo($sim, [5], 'review');
        $before = [$this->row('lms_simulation'), $this->row('lms_simulation_version'), $this->row('lms_simulation_concept')];

        foreach ([7, 8] as $tenant) {          // 7 is is_Lms (it READS tenant 1), 8 is not; neither may write
            foreach (['Teacher', 'Admin', 'Super Admin'] as $profile) {
                foreach (self::WRITES as $action) {
                    [$status] = $this->write($action, $tenant, $profile);
                    $this->assertSame(403, $status, "{$action} as {$profile} of tenant {$tenant}");
                }
            }
        }

        $this->assertSame($before, [$this->row('lms_simulation'), $this->row('lms_simulation_version'), $this->row('lms_simulation_concept')], 'nothing changed');
        $this->assertSame('review', DB::table('lms_simulation_concept')->value('review_status'));
        $this->assertSame('published', DB::table('lms_simulation')->value('status'));
    }

    public function test_a_tenant_5_session_cannot_edit_a_tenant_1_row_even_naming_it_by_id(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        foreach ([5, 7] as $tenant) {
            [$status] = $this->act('update', $tenant, [$sim->id], json_encode(['spec' => json_decode($this->specText('tiny-api', 'Hijacked')), 'status' => 'archived']));
            $this->assertSame(403, $status);
            [$status] = $this->act('destroy', $tenant, [$sim->id]);
            $this->assertSame(403, $status);
        }
        $row = DB::table('lms_simulation')->first();
        $this->assertSame(['Sim tiny-api', 'published', 1, null], [$row->title, $row->status, (int) $row->current_version, $row->deleted_at]);
    }

    public function test_a_student_or_parent_token_is_refused_on_every_author_endpoint_even_in_the_platform_tenant(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        foreach (self::WRITES as $action) {
            [$status] = $this->write($action, 1, 'Student', true);
            $this->assertSame(403, $status, "{$action} as a student (is_student)");
            [$status] = $this->write($action, 1, 'Student', false);
            $this->assertSame(403, $status, "{$action} as profile Student");
            [$status] = $this->write($action, 1, 'Parent', false);
            $this->assertSame(403, $status, "{$action} as profile Parent");
        }
        $this->assertSame(1, $this->row('lms_simulation'));
        $this->assertSame('published', $sim->status);
    }

    public function test_a_platform_staff_member_can_write(): void
    {
        [$status, $body] = $this->write('store', 1);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertTrue($body['success']);
        $this->assertSame(1, $body['data']['owner_tenant'], 'the owner is the session tenant');
        $this->assertTrue($body['data']['editable']);
        $this->assertSame([], $body['data']['links']);
        foreach (['limits', 'concepts'] as $action) {
            [$status] = $this->write($action, 1);
            $this->assertSame(200, $status, $action);
        }
    }

    public function test_the_routes_put_the_session_and_the_write_gate_in_front_of_the_controller(): void
    {
        $writes = [['POST', ''], ['PUT', '{id}'], ['DELETE', '{id}'], ['POST', '{id}/restore'], ['PUT', '{id}/links'], ['POST', '{id}/links/{linkId}/review'],
            ['POST', 'assets'], ['GET', 'limits'], ['GET', 'concepts'], ['GET', '{id}/versions']];
        $reads = [['GET', ''], ['GET', '{id}'], ['GET', '{id}/assets/{sha256}'], ['GET', '{id}/links'], ['POST', '{id}/progress']];
        $find = function (string $method, string $suffix) {
            $uri = trim('api/lms/prayogshala/simulations/' . $suffix, '/');
            foreach (Route::getRoutes()->getRoutes() as $route) {
                if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                    return $route;
                }
            }

            return null;
        };

        foreach ($writes as [$method, $suffix]) {
            $route = $find($method, $suffix);
            $this->assertNotNull($route, "{$method} {$suffix} is routed");
            $middleware = $route->gatherMiddleware();
            foreach (['api.session', 'lms.auth', 'perm:lms.content,create'] as $needed) {
                $this->assertContains($needed, $middleware, "{$method} {$suffix} runs {$needed}");
            }
            $this->assertLessThan(array_search('lms.auth', $middleware, true), array_search('api.session', $middleware, true), 'the session comes first');
        }
        foreach ($reads as [$method, $suffix]) {
            $route = $find($method, $suffix);
            $this->assertNotNull($route, "{$method} {$suffix} is routed");
            $this->assertContains('api.session', $route->gatherMiddleware());
            $this->assertNotContains('perm:lms.content,create', $route->gatherMiddleware(), 'a read is open to any authenticated role');
        }
        $this->assertContains('throttle:simulation-assets', $find('POST', 'assets')->gatherMiddleware());
    }

    public function test_the_named_upload_limiter_exists_and_allows_thirty_a_minute_per_caller(): void
    {
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('simulation-assets');
        $this->assertNotNull($limiter, 'throttle:simulation-assets is registered');
        $request = Request::create('/x', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer abc']);
        $limit = $limiter($request);
        $this->assertSame(30, $limit->maxAttempts);
        $other = $limiter(Request::create('/x', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer def']));
        $this->assertNotSame($limit->key, $other->key, 'each caller has an allowance of their own');
    }

    public function test_an_oversize_spec_body_is_refused_before_it_is_decoded_and_an_upload_is_not_touched(): void
    {
        $middleware = new LimitSimulationBody();
        $next = fn ($r) => response('passed');
        $big = fn (string $uri, string $method) => Request::create($uri, $method, [], [], [], ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => (string) (SimulationStore::MAX_SPEC_BYTES + 200000)]);

        $refused = $middleware->handle($big('/api/lms/prayogshala/simulations', 'POST'), $next);
        $this->assertSame(413, $refused->getStatusCode());
        $this->assertFalse($refused->getData(true)['success']);
        $this->assertSame(413, $middleware->handle($big('/api/lms/prayogshala/simulations/3', 'PUT'), $next)->getStatusCode());
        $this->assertSame('passed', $middleware->handle($big('/api/lms/prayogshala/simulations/assets', 'POST'), $next)->getContent(), 'an upload has limits of its own');
        $this->assertSame('passed', $middleware->handle($big('/api/lms/other', 'POST'), $next)->getContent(), 'other routes are not looked at');
        $small = Request::create('/api/lms/prayogshala/simulations', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"spec":{}}');
        $this->assertSame('passed', $middleware->handle($small, $next)->getContent());
    }

    // ── who sees what ────────────────────────────────────────────────────────

    /** Five simulations of tenant 1: published through a published link / only a review link / unlinked / a draft with a published link / deleted. */
    private function gallery(): array
    {
        $live = $this->makeSimulation('live-one');
        $this->linkTo($live, [5], 'published');
        $review = $this->makeSimulation('review-one');
        $this->linkTo($review, [6], 'review');
        $loose = $this->makeSimulation('loose-one');
        $draft = $this->makeSimulation('draft-one', 'draft');
        $this->linkTo($draft, [7], 'published');
        $gone = $this->makeSimulation('gone-one');
        $this->linkTo($gone, [5], 'published');
        DB::table('lms_simulation')->where('id', $gone->id)->update(['deleted_at' => now()]);

        return compact('live', 'review', 'loose', 'draft', 'gone');
    }

    private function slugs(array $body): array
    {
        $slugs = array_map(fn ($c) => $c['slug'], $body['data']['simulations']);
        sort($slugs);

        return $slugs;
    }

    public function test_a_learner_sees_every_published_simulation_of_the_tenants_they_may_read_and_the_unmapped_ones_are_flagged(): void
    {
        $this->gallery();

        // tenant 7 is is_Lms = Y, so it reads tenant 1's rows
        [$status, $body] = $this->act('index', 7, [], null, [], 'Student', true);
        $this->assertSame(200, $status);
        $this->assertSame(['live-one', 'loose-one', 'review-one'], $this->slugs($body), 'published ones, mapped or not; not the draft or the deleted one');
        $byslug = collect($body['data']['simulations'])->keyBy('slug');
        $this->assertTrue($byslug['live-one']['mapped'], 'it has a PUBLISHED link');
        $this->assertFalse($byslug['review-one']['mapped'], 'its only link is still in review: not mapped yet');
        $this->assertFalse($byslug['loose-one']['mapped'], 'no link at all: not mapped yet');
        $this->assertSame([], $byslug['review-one']['links'], 'a learner is never shown a link that is still in review');
        $this->assertSame([], $byslug['loose-one']['links']);
        $card = $byslug['live-one'];
        $this->assertFalse($card['editable'], 'another tenant\'s row is never editable');
        $this->assertFalse($body['data']['can_manage']);
        $this->assertCount(1, $card['links']);
        $this->assertSame(['concept_id' => 5, 'concept_name' => 'Concept Five', 'chapter_id' => 100, 'chapter_name' => 'Ch A', 'topic_id' => 11, 'topic_name' => 'Topic One',
            'standard_id' => 42, 'subject_id' => 3975, 'role' => 'primary', 'review_status' => 'published'],
            array_intersect_key($card['links'][0], array_flip(['concept_id', 'concept_name', 'chapter_id', 'chapter_name', 'topic_id', 'topic_name', 'standard_id', 'subject_id', 'role', 'review_status'])));

        // tenant 8 is not linked to the platform: it sees nothing of tenant 1's
        [, $body] = $this->act('index', 8, [], null, [], 'Student', true);
        $this->assertSame([], $this->slugs($body));
        // a parent is a learner too, whatever the is_student flag says
        [, $body] = $this->act('index', 7, [], null, [], 'Parent', false);
        $this->assertSame(['live-one', 'loose-one', 'review-one'], $this->slugs($body));
    }

    public function test_a_learner_can_open_what_the_list_shows_them_and_nothing_else(): void
    {
        $sims = $this->gallery();
        foreach (['draft', 'gone'] as $key) {
            [$status] = $this->act('show', 7, [$sims[$key]->id], null, [], 'Student', true);
            $this->assertSame(404, $status, $key . ' (show)');
            [$status] = $this->act('progress', 7, [$sims[$key]->id], ['percent' => 10], [], 'Student', true);
            $this->assertSame(404, $status, $key . ' (progress)');
            [$status] = $this->act('links', 7, [$sims[$key]->id], null, [], 'Student', true);
            $this->assertSame(404, $status, $key . ' (links)');
        }
        // an unmapped simulation is shown, so it opens, plays and saves progress; it just carries the flag and no review link
        foreach (['review' => 'review-one', 'loose' => 'loose-one'] as $key => $slug) {
            [$status, $shown] = $this->act('show', 7, [$sims[$key]->id], null, [], 'Student', true);
            $this->assertSame(200, $status, $key . ' (show)');
            $this->assertFalse($shown['data']['mapped'], $key . ' is flagged');
            $this->assertSame([], $shown['data']['links'], $key . ' shows a learner no link');
            [$status] = $this->act('progress', 7, [$sims[$key]->id], ['percent' => 10], [], 'Student', true);
            $this->assertSame(200, $status, $key . ' (progress)');
            [$status, $links] = $this->act('links', 7, [$sims[$key]->id], null, [], 'Student', true);
            $this->assertSame(200, $status, $key . ' (links)');
            $this->assertSame([], $links['data']['links']);
        }
        [$status, $body] = $this->act('show', 7, [$sims['live']->id], null, [], 'Student', true);
        $this->assertSame(200, $status);
        $this->assertSame('g2g.sim/1', $body['data']['spec']['schema']);
        $this->assertFalse($body['data']['editable']);
        $this->assertTrue($body['data']['mapped']);
        [$status] = $this->act('show', 8, [$sims['live']->id], null, [], 'Student', true);
        $this->assertSame(404, $status, 'a tenant outside the platform share cannot read it');
    }

    public function test_staff_of_a_reading_tenant_also_see_unlinked_simulations_and_links_in_review_but_not_drafts(): void
    {
        $sims = $this->gallery();
        [, $body] = $this->act('index', 7, [], null, [], 'Teacher');
        $this->assertSame(['live-one', 'loose-one', 'review-one'], $this->slugs($body), 'a draft is the authors\' own');
        $byslug = collect($body['data']['simulations'])->keyBy('slug');
        $this->assertSame('review', $byslug['review-one']['links'][0]['review_status'], 'the link in review is shown, marked');
        $this->assertFalse($byslug['review-one']['editable']);
        $this->assertFalse($body['data']['can_manage']);

        [$status, $show] = $this->act('show', 7, [$sims['review']->id], null, [], 'Teacher');
        $this->assertSame(200, $status);
        $this->assertSame('review', $show['data']['links'][0]['review_status']);
        [$status] = $this->act('show', 7, [$sims['draft']->id], null, [], 'Teacher');
        $this->assertSame(404, $status);
        [, $links] = $this->act('links', 7, [$sims['review']->id], null, [], 'Teacher');
        $this->assertCount(1, $links['data']['links']);
        [$status, $learnerLinks] = $this->act('links', 7, [$sims['review']->id], null, [], 'Student', true);
        $this->assertSame(200, $status, 'a learner reaches an unmapped simulation...');
        $this->assertSame([], $learnerLinks['data']['links'], '...but is never shown the link that is still in review');
    }

    public function test_the_authors_see_every_status_and_may_edit_their_own_rows(): void
    {
        $this->gallery();
        [, $body] = $this->act('index', 1, [], null, [], 'Admin');
        $this->assertSame(['draft-one', 'live-one', 'loose-one', 'review-one'], $this->slugs($body), 'drafts included, deleted not');
        $this->assertTrue($body['data']['can_manage']);
        $this->assertSame([true], array_values(array_unique(array_map(fn ($c) => $c['editable'], $body['data']['simulations']))));
        // but a platform-tenant STUDENT is a learner: published ones only (no draft), and nothing is editable
        [, $body] = $this->act('index', 1, [], null, [], 'Student', true);
        $this->assertSame(['live-one', 'loose-one', 'review-one'], $this->slugs($body));
        $this->assertSame([false], array_values(array_unique(array_map(fn ($c) => $c['editable'], $body['data']['simulations']))));
    }

    public function test_the_list_filters_on_a_link_and_all_filters_must_hold_on_one_link(): void
    {
        $a = $this->makeSimulation('sim-a');
        $this->linkTo($a, [5, 7], 'published');
        $b = $this->makeSimulation('sim-b');
        $this->linkTo($b, [6], 'published');
        $c = $this->makeSimulation('sim-c');
        $this->linkTo($c, [5], 'review');

        $slugs = fn (array $q, string $profile = 'Student', bool $student = true) => $this->slugs($this->act('index', 7, [], null, $q, $profile, $student)[1]);
        $this->assertSame(['sim-a'], $slugs(['concept_id' => 5]), 'a learner: the review link of sim-c does not count');
        $this->assertSame(['sim-a', 'sim-c'], $slugs(['concept_id' => 5], 'Teacher', false), 'staff: it does');
        // a chapter, standard or subject is a BROAD place: sim-c is not mapped yet (its only link is in review), so it shows everywhere, flagged
        $this->assertSame(['sim-a', 'sim-b', 'sim-c'], $slugs(['chapter_id' => 100]));
        $this->assertSame(['sim-a', 'sim-c'], $slugs(['chapter_id' => 200]), 'sim-b is mapped, and not to chapter 200');
        $this->assertSame(['sim-a', 'sim-c'], $slugs(['standard_id' => 43]));
        $this->assertSame(['sim-a', 'sim-b', 'sim-c'], $slugs(['subject_id' => 3975]));
        // a topic or a concept is a PRECISE place: only a simulation linked there matches, never an unmapped one
        $this->assertSame(['sim-b'], $slugs(['topic_id' => 12]));
        $this->assertSame([], $slugs(['chapter_id' => 200, 'topic_id' => 11]), 'the chapter and the topic must be on the SAME link, and a precise filter never matches an unmapped simulation');
        $this->assertSame(['sim-a'], $slugs(['q' => 'sim-a']), 'q searches the card');
        [$status, $body] = $this->act('index', 7, [], null, ['chapter_id' => 'abc'], 'Student', true);
        $this->assertSame(422, $status);
        $this->assertFalse($body['success']);
    }

    // ── the links ────────────────────────────────────────────────────────────

    public function test_put_links_derives_the_hierarchy_from_the_concept_and_ignores_what_the_client_says(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        // the client claims chapter 200, topic 12, standard 43 for concept 5: all of it is ignored
        [$status, $body] = $this->act('putLinks', 1, [$sim->id], ['links' => [
            ['concept_id' => 5, 'chapter_id' => 200, 'topic_id' => 12, 'standard_id' => 43, 'subject_id' => 1, 'review_status' => 'published', 'citation' => 'Section 1.2', 'reason' => 'It shows it.'],
            ['concept_id' => 7, 'role' => 'supporting'],
        ]]);
        $this->assertSame(200, $status, json_encode($body));
        $links = collect($body['data']['links'])->keyBy('concept_id');
        $this->assertSame([100, 11, 42, 3975, 'review', 'primary'], [$links[5]['chapter_id'], $links[5]['topic_id'], $links[5]['standard_id'], $links[5]['subject_id'], $links[5]['review_status'], $links[5]['role']]);
        $this->assertSame(['Section 1.2', 'It shows it.'], [$links[5]['citation'], $links[5]['reason']]);
        $this->assertSame([200, null, 43, 'supporting', 'review'], [$links[7]['chapter_id'], $links[7]['topic_id'], $links[7]['standard_id'], $links[7]['role'], $links[7]['review_status']], 'a concept with no topic gives a link with none');
        $this->assertSame(2, $this->row('lms_simulation_concept'));
    }

    public function test_put_links_rejects_unknown_and_other_tenant_concepts_with_a_422_naming_the_id_and_writes_nothing(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        $this->linkTo($sim, [5], 'published');

        foreach ([[999, 'unknown'], [9, 'another tenant\'s']] as [$conceptId, $why]) {
            [$status, $body] = $this->act('putLinks', 1, [$sim->id], ['links' => [['concept_id' => 6], ['concept_id' => $conceptId]]]);
            $this->assertSame(422, $status, $why);
            $this->assertFalse($body['success']);
            $this->assertStringContainsString("Concept {$conceptId} ", $body['message'], 'the id is named');
        }
        $this->assertSame([5], DB::table('lms_simulation_concept')->pluck('concept_id')->all(), 'the old set is untouched');
        $this->assertSame('published', DB::table('lms_simulation_concept')->value('review_status'));

        foreach ([
            [['links' => 'nope'], '"links" is required'],
            [['links' => [['concept_id' => 'x']]], 'must be a concept id'],
            [['links' => [['concept_id' => 5], ['concept_id' => 5]]], 'listed twice'],
            [['links' => [['concept_id' => 5, 'role' => 'boss']]], 'one of: primary, supporting'],
            [['links' => [['concept_id' => 5, 'citation' => str_repeat('x', 256)]]], '255 characters'],
            [['links' => [['concept_id' => 5, 'reason' => str_repeat('x', 501)]]], '500 characters'],
        ] as [$payload, $expected]) {
            [$status, $body] = $this->act('putLinks', 1, [$sim->id], $payload);
            $this->assertSame(422, $status, $expected);
            $this->assertStringContainsString($expected, implode(' ', $body['problems']));
        }
        $this->assertSame([5], DB::table('lms_simulation_concept')->pluck('concept_id')->all());
        // and an unknown simulation is a 404
        [$status] = $this->act('putLinks', 1, [4242], ['links' => []]);
        $this->assertSame(404, $status);
    }

    public function test_replacing_the_link_set_keeps_the_state_of_an_unchanged_link_and_sends_a_changed_one_back_to_review(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        $this->act('putLinks', 1, [$sim->id], ['links' => [['concept_id' => 5, 'citation' => 'A'], ['concept_id' => 6], ['concept_id' => 7]]]);
        $rows = DB::table('lms_simulation_concept')->get()->keyBy('concept_id');
        foreach ([5, 6, 7] as $cid) {
            $this->act('reviewLink', 1, [$sim->id, $rows[$cid]->id], ['status' => 'published']);
        }

        [, $body] = $this->act('putLinks', 1, [$sim->id], ['links' => [
            ['concept_id' => 5, 'citation' => 'A', 'sort_order' => 3],   // unchanged text: keeps its state
            ['concept_id' => 6, 'citation' => 'new citation'],           // changed: back to review
            // concept 7 is left out: removed
            ['concept_id' => 8],                                         // new: review
        ]]);
        $links = collect($body['data']['links'])->keyBy('concept_id');
        $this->assertSame([5, 6, 8], $links->keys()->sort()->values()->all());
        $this->assertSame(['published', 'review', 'review'], [$links[5]['review_status'], $links[6]['review_status'], $links[8]['review_status']]);
        $this->assertSame(3, $links[5]['sort_order']);
        $this->assertSame($rows[5]->id, $links[5]['id'], 'the same row, not a new one');
        $this->assertNotNull($links[5]['reviewed_by']);
        $this->assertNull($links[6]['reviewed_by'], 'a changed link has no reviewer any more');
        $this->assertSame(3, $this->row('lms_simulation_concept'), 'links are hard-deleted');

        [, $body] = $this->act('putLinks', 1, [$sim->id], ['links' => []]);
        $this->assertSame([], $body['data']['links']);
        $this->assertSame(0, $this->row('lms_simulation_concept'));
    }

    public function test_reviewing_a_link_stamps_the_reviewer_and_what_it_publishes_a_learner_then_sees(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        $this->linkTo($sim, [5], 'review');
        $link = DB::table('lms_simulation_concept')->first();
        $learner = fn (array $q = []) => $this->act('index', 7, [], null, $q, 'Student', true)[1];
        $before = $learner();
        $this->assertSame(['tiny-api'], $this->slugs($before), 'not mapped yet, so it is shown, flagged');
        $this->assertFalse($before['data']['simulations'][0]['mapped']);
        $this->assertSame(['tiny-api'], $this->slugs($learner(['chapter_id' => 999])), 'unmapped: it is in every chapter\'s tab');

        [$status, $body] = $this->act('reviewLink', 1, [$sim->id, $link->id], ['status' => 'published'], [], 'Teacher', false, 77);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(['published', 77], [$body['data']['link']['review_status'], $body['data']['link']['reviewed_by']]);
        $row = DB::table('lms_simulation_concept')->first();
        $this->assertSame(['published', 77], [$row->review_status, (int) $row->reviewed_by]);
        $this->assertNotNull($row->reviewed_at);
        $after = $learner();
        $this->assertSame(['tiny-api'], $this->slugs($after));
        $this->assertTrue($after['data']['simulations'][0]['mapped'], 'a published link maps it: the flag goes');
        $this->assertCount(1, $after['data']['simulations'][0]['links']);
        $this->assertSame(['tiny-api'], $this->slugs($learner(['chapter_id' => 100])), 'it shows in the chapter it is mapped to');
        $this->assertSame([], $this->slugs($learner(['chapter_id' => 999])), 'and leaves every other chapter\'s tab');
        $this->assertSame(['tiny-api'], $this->slugs($learner(['concept_id' => 5])));

        [, $body] = $this->act('reviewLink', 1, [$sim->id, $link->id], ['status' => 'review'], [], 'Admin', false, 88);
        $this->assertSame(['review', 88], [$body['data']['link']['review_status'], $body['data']['link']['reviewed_by']], 'unpublishing is stamped too');
        $gone = $learner();
        $this->assertSame(['tiny-api'], $this->slugs($gone), 'unpublished: shown again, flagged again');
        $this->assertFalse($gone['data']['simulations'][0]['mapped']);
        $this->assertSame([], $this->slugs($learner(['concept_id' => 5])), 'a precise filter does not match an unmapped simulation');
        $this->assertSame(['tiny-api'], $this->slugs($learner(['chapter_id' => 999])));

        foreach (['archived', '', null, ['x']] as $bad) {
            [$status, $body] = $this->act('reviewLink', 1, [$sim->id, $link->id], ['status' => $bad]);
            $this->assertSame(422, $status);
            $this->assertStringContainsString('"status" must be one of: review, published', $body['message']);
        }
        $other = $this->makeSimulation('other-sim');
        [$status] = $this->act('reviewLink', 1, [$other->id, $link->id], ['status' => 'published']);
        $this->assertSame(404, $status, 'a link is reviewed through its own simulation only');
        $this->assertSame('review', DB::table('lms_simulation_concept')->value('review_status'));
        [$status] = $this->act('reviewLink', 1, [$sim->id, 9999], ['status' => 'published']);
        $this->assertSame(404, $status);
    }

    public function test_the_concept_picker_lists_the_platform_tenants_concepts_only(): void
    {
        [, $body] = $this->act('concepts', 1, [], null, ['chapter_id' => 100]);
        $this->assertSame([5, 6], array_column($body['data']['concepts'], 'id'));
        $first = $body['data']['concepts'][0];
        $this->assertSame(['id' => 5, 'name' => 'Concept Five', 'topic_id' => 11, 'topic_name' => 'Topic One', 'chapter_id' => 100, 'review_status' => 'verified'], $first);

        [, $body] = $this->act('concepts', 1, [], null, ['chapter_id' => 200]);
        $this->assertNull($body['data']['concepts'][0]['topic_id']);
        $this->assertNull($body['data']['concepts'][0]['topic_name']);
        [, $body] = $this->act('concepts', 1, [], null, ['chapter_id' => 300]);
        $this->assertSame([], $body['data']['concepts'], 'another tenant\'s chapter has nothing to pick');
        [, $body] = $this->act('concepts', 1, [], null, ['q' => 'B concept']);
        $this->assertSame([], $body['data']['concepts']);
        [, $body] = $this->act('concepts', 1, [], null, ['q' => 'six']);
        $this->assertSame([6], array_column($body['data']['concepts'], 'id'));
    }

    // ── the lifecycle (ported from hp_erp) ───────────────────────────────────

    public function test_a_simulation_is_added_versioned_changed_deleted_and_restored(): void
    {
        [$status, $created] = $this->act('store', 1, [], json_encode(['spec' => json_decode($this->specText('lifecycle', 'Lifecycle')), 'category' => 'Physics', 'tags' => ['a', 'b'], 'cover_url' => '/simulations/covers/x.webp']));
        $this->assertSame(201, $status, json_encode($created));
        $id = $created['data']['id'];
        $this->assertSame([1, 'published', 'Physics', ['a', 'b'], '/simulations/covers/x.webp'], [$created['data']['version'], $created['data']['status'], $created['data']['category'], $created['data']['tags'], $created['data']['cover_url']]);

        [$status, $clash] = $this->act('store', 1, [], json_encode(['spec' => json_decode($this->specText('lifecycle'))]));
        $this->assertSame(409, $status);
        $this->assertStringContainsString('already exists', $clash['message']);

        // a new spec is version 2; the same spec again (any layout) is not a version
        [, $v2] = $this->act('update', 1, [$id], json_encode(['spec' => json_decode($this->specText('lifecycle', 'Lifecycle two')), 'note' => 'retitled']));
        $this->assertSame([2, 'Lifecycle two'], [$v2['data']['version'], $v2['data']['title']]);
        [, $same] = $this->act('update', 1, [$id], json_encode(['spec' => $this->specText('lifecycle', 'Lifecycle two')]));
        $this->assertSame(2, $same['data']['version'], 'the same document, sent as text this time, is not a third version');
        [$status, $moved] = $this->act('update', 1, [$id], json_encode(['spec' => json_decode($this->specText('another-id'))]));
        $this->assertSame(422, $status);
        $this->assertStringContainsString('cannot change', $moved['message']);

        [, $cardOnly] = $this->act('update', 1, [$id], json_encode(['status' => 'archived', 'sort_order' => 5, 'difficulty' => 'advanced']));
        $this->assertSame(['archived', 2, 'advanced'], [$cardOnly['data']['status'], $cardOnly['data']['version'], $cardOnly['data']['difficulty']], 'a card change makes no version');
        [$status] = $this->act('update', 1, [$id], json_encode(['status' => 'nonsense']));
        $this->assertSame(422, $status);

        [, $versions] = $this->act('versions', 1, [$id]);
        $this->assertSame([2, [2, 1]], [$versions['data']['current'], array_column($versions['data']['versions'], 'version')]);
        [, $old] = $this->act('show', 1, [$id], null, ['version' => 1]);
        $this->assertSame(['Lifecycle', 'Lifecycle two'], [$old['data']['spec']['title'], $old['data']['title']], 'staff may name a version: its spec is version 1, the card is the current one');
        [$status] = $this->act('show', 1, [$id], null, ['version' => 9]);
        $this->assertSame(404, $status);

        [, $gone] = $this->act('destroy', 1, [$id]);
        $this->assertSame(['id' => $id, 'deleted' => true], $gone['data']);
        [$status] = $this->act('show', 1, [$id]);
        $this->assertSame(404, $status);
        [$status] = $this->act('destroy', 1, [$id]);
        $this->assertSame(404, $status, 'already deleted');
        [$status, $back] = $this->act('restore', 1, [$id]);
        $this->assertSame(200, $status);
        $this->assertSame($id, $back['data']['id']);
        [$status] = $this->act('show', 1, [$id]);
        $this->assertSame(200, $status);
        [$status] = $this->act('restore', 1, [4242]);
        $this->assertSame(404, $status);
    }

    public function test_a_body_that_is_not_a_specification_is_refused_with_the_problems_listed(): void
    {
        foreach ([['{', 'not valid JSON'], ['[1]', 'must be a JSON object'], ['[]', '"spec" is required'], ['{}', '"spec" is required'], ['{"spec":{}}', '"schema" must be'], ['{"spec":' . json_encode($this->specText()) . ',"tags":{"a":1}}', '"tags" must be a list of words'],
            ['{"spec":' . json_encode($this->specText()) . ',"status":5}', '"status" must be text'], ['{"spec":' . json_encode($this->specText()) . ',"cover_url":"javascript:alert(1)"}', '"cover_url" must be an https address']] as [$json, $expected]) {
            [$status, $body] = $this->act('store', 1, [], $json);
            $this->assertSame(422, $status, $json);
            $this->assertStringContainsString($expected, implode(' ', $body['problems']), $json);
            $this->assertSame(['spec' => $body['problems']], $body['errors']);
        }
        $this->assertSame(0, $this->row('lms_simulation'));
    }

    public function test_the_specification_must_name_only_registered_files_of_its_owner_or_the_platform(): void
    {
        $mine = $this->registerAsset($this->glb('mine'), 1);
        $theirs = $this->registerAsset($this->glb('theirs'), 8);
        $unregistered = hash('sha256', 'never uploaded');

        [$status] = $this->act('store', 1, [], json_encode(['spec' => json_decode($this->specText('ok', 'Ok', [$this->model($mine)]))]));
        $this->assertSame(201, $status);
        foreach ([$theirs => 'another tenant\'s', $unregistered => 'never uploaded'] as $sha => $why) {
            [$status, $body] = $this->act('store', 1, [], json_encode(['spec' => json_decode($this->specText('bad-' . substr($sha, 0, 4), 'Bad', [$this->model($sha)]))]));
            $this->assertSame(422, $status, $why);
            $this->assertStringContainsString('has not been uploaded for this tenant', $body['message']);
        }
        $this->assertSame(1, $this->row('lms_simulation'));
    }

    // ── progress ─────────────────────────────────────────────────────────────

    public function test_progress_is_the_callers_own_with_their_own_tenant_and_never_goes_backwards(): void
    {
        $sim = $this->makeSimulation('tiny-api');
        $this->linkTo($sim, [5], 'published');

        [$status, $body] = $this->act('progress', 7, [$sim->id], ['percent' => 40, 'step' => 3], [], 'Student', true, 901);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(['in_progress', 40, 3], [$body['data']['status'], $body['data']['percent'], $body['data']['step']]);
        $row = DB::table('lms_simulation_progress')->first();
        $this->assertSame([7, 901, 1], [(int) $row->sub_institute_id, (int) $row->user_id, (int) $row->version], 'the learner\'s own tenant and the token\'s user');

        [, $body] = $this->act('progress', 7, [$sim->id], ['percent' => 10, 'step' => 1], [], 'Student', true, 901);
        $this->assertSame([40, 3], [$body['data']['percent'], $body['data']['step']], 'a lower report is ignored');
        [, $body] = $this->act('progress', 7, [$sim->id], ['status' => 'completed', 'result' => ['stars' => 3]], [], 'Student', true, 901);
        $this->assertSame(['completed', 100], [$body['data']['status'], $body['data']['percent']]);
        [, $body] = $this->act('progress', 7, [$sim->id], ['percent' => 5], [], 'Student', true, 901);
        $this->assertSame('completed', $body['data']['status'], 'a completed simulation stays completed');

        $this->act('progress', 7, [$sim->id], ['percent' => 20], [], 'Student', true, 902);
        $this->assertSame(2, $this->row('lms_simulation_progress'), 'one row per learner');
        [, $show] = $this->act('show', 7, [$sim->id], null, [], 'Student', true, 901);
        $this->assertSame(['completed', 100], [$show['data']['progress']['status'], $show['data']['progress']['percent']]);
        $this->assertSame(['stars' => 3], $show['data']['progress']['snapshot']);
        [, $show] = $this->act('show', 7, [$sim->id], null, [], 'Student', true, 903);
        $this->assertNull($show['data']['progress'], 'nobody sees another learner\'s progress');
    }

    // ── asset bytes ──────────────────────────────────────────────────────────

    public function test_asset_bytes_are_served_only_for_a_hash_the_current_spec_names_to_someone_who_may_see_it(): void
    {
        $eye = $this->registerAsset($this->glb('eye'), 1, ['licence' => 'CC BY 4.0', 'credit' => 'Eye by A.']);
        $other = $this->registerAsset($this->glb('other'), 1);
        $theirs = $this->registerAsset($this->glb('theirs'), 8);
        $sim = $this->makeSimulation('eye-lab', 'published', 1, [$this->model($eye)]);
        $this->linkTo($sim, [5], 'published');

        $response = $this->actRaw('asset', 7, [$sim->id, $eye], null, [], 'Student', true);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('model/gltf-binary', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('"' . $eye . '"', $response->headers->get('ETag'));
        ob_start();
        $response->sendContent();
        $this->assertSame($this->glb('eye'), ob_get_clean());

        foreach ([$other => 'a registered file this simulation does not name', $theirs => 'another tenant\'s file', hash('sha256', 'nothing') => 'an unknown hash'] as $sha => $why) {
            $this->assertSame(404, $this->actRaw('asset', 7, [$sim->id, $sha], null, [], 'Student', true)->getStatusCode(), $why);
        }
        $this->assertSame(404, $this->actRaw('asset', 8, [$sim->id, $eye], null, [], 'Student', true)->getStatusCode(), 'a tenant that cannot see the simulation cannot fetch its file');
        \Illuminate\Support\Facades\Storage::disk('digitalocean')->delete('k12_sim_assets/' . $eye . '.glb');
        $this->assertSame(404, $this->actRaw('asset', 7, [$sim->id, $eye], null, [], 'Student', true)->getStatusCode(), 'a registered file missing from the disk is a 404');

        [, $show] = $this->act('show', 7, [$sim->id], null, [], 'Student', true);
        $this->assertSame([['sha256' => $eye, 'kind' => 'model', 'name' => 'A model', 'bytes' => strlen($this->glb('eye')), 'licence' => 'CC BY 4.0', 'credit' => 'Eye by A.', 'source_url' => 'https://example.org/model']], $show['data']['assets'], 'the credits a CC BY file needs');
        $this->assertArrayNotHasKey('storage_path', $show['data']['assets'][0]);

        $unpublished = $this->makeSimulation('hidden', 'published', 1, [$this->model($eye)]);
        $this->assertSame(404, $this->actRaw('asset', 7, [$unpublished->id, $eye], null, [], 'Student', true)->getStatusCode(), 'visibility is the simulation\'s: an unlinked one serves no bytes to a learner');
    }

    public function test_a_deleted_simulation_cannot_be_restored_over_a_live_one_that_took_its_id(): void
    {
        $first = $this->makeSimulation('same-id');
        $this->act('destroy', 1, [$first->id]);
        $second = $this->makeSimulation('same-id');   // allowed: the first is deleted

        [$status, $body] = $this->act('restore', 1, [$first->id]);
        $this->assertSame(409, $status);
        $this->assertStringContainsString("Another simulation (id {$second->id}) now uses the id", $body['message']);
        $this->assertNotNull(DB::table('lms_simulation')->where('id', $first->id)->value('deleted_at'), 'still deleted');
        $this->assertSame(1, DB::table('lms_simulation')->where('slug', 'same-id')->whereNull('deleted_at')->count(), 'never two live simulations with one id');
    }

    public function test_the_migrations_are_idempotent_and_the_database_itself_enforces_one_link_per_concept(): void
    {
        // setUp already ran both; running them again must change nothing and not fail
        (require base_path('database/migrations/2026_10_28_100000_create_lms_simulation_tables.php'))->up();
        (require base_path('database/migrations/2026_10_28_100100_create_lms_simulation_concept_table.php'))->up();
        foreach (['lms_simulation', 'lms_simulation_version', 'lms_simulation_asset', 'lms_simulation_progress', 'lms_simulation_concept'] as $table) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable($table), $table);
        }
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('lms_simulation_tenant'), 'the per-organisation switch of hp_erp is not ported');

        $sim = $this->makeSimulation('tiny-api');
        $row = ['simulation_id' => $sim->id, 'concept_id' => 5, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'created_at' => now(), 'updated_at' => now()];
        DB::table('lms_simulation_concept')->insert($row);
        $link = DB::table('lms_simulation_concept')->first();
        $this->assertSame(['primary', 'review', null, 0], [$link->role, $link->review_status, $link->topic_id, (int) $link->sort_order], 'the column defaults');
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('lms_simulation_concept')->insert($row);
    }
}
