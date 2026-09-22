<?php

namespace Tests\Feature\Pal;

use App\Http\Controllers\lms\pal\PalFlowAdminController;
use App\Services\PAL\Flow\EsoFlowRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The screen a principal uses to choose how their school teaches.
 *
 * Until this existed the only way to change a school's flow was
 * `php artisan tinker`, which meant it was not a product feature — it was a
 * favour developers did on request.
 *
 * ---------------------------------------------------------------------------
 * WHAT THESE TESTS ARE FOR
 * ---------------------------------------------------------------------------
 * Mostly authorisation, because this is the one screen in PAL whose buttons
 * change what real children are taught. The test that matters most is the last
 * one: a teacher POSTing straight at the route is refused. Hiding a button is
 * presentation, not permission, and a screen that relies on the button being
 * absent is not actually protected.
 *
 * Skipped wholesale where the flow tables are absent — 408 of this estate's 996
 * migrations are pending, so a host without them is ordinary, not broken.
 */
class PalFlowAdminScreenTest extends TestCase
{
    use DatabaseTransactions;

    private int $subInstituteId;

    protected function setUp(): void
    {
        parent::setUp();

        // lmslayout reads $_SERVER['REQUEST_URI'] directly to work out which
        // nav item is active. PHPUnit's simulated request never populates the
        // superglobal, so rendering ANY page on this layout throws "Undefined
        // array key REQUEST_URI" before a single assertion runs.
        //
        // Set here rather than worked around, because the alternative is to
        // stop asserting on the rendered page - and what this screen SAYS is
        // most of what it is for.
        $_SERVER['REQUEST_URI'] = '/lms/pal/flow';

        if (! app(EsoFlowRegistry::class)->available()) {
            $this->markTestSkipped('The PAL flow tables are not present on this connection.');
        }

        if (app(EsoFlowRegistry::class)->activeVersion('no_cfu') === null) {
            $this->markTestSkipped('The shipped flow profiles have not been seeded on this connection.');
        }

        $this->subInstituteId = (int) DB::table('school_setup')->insertGetId([
            'SchoolName' => 'Flow Screen School',
            'ShortCode' => 'FSS' . random_int(1000, 9999),
            'ContactPerson' => 'Test Contact',
            'Mobile' => '9999999999',
            'Email' => 'flow-screen@example.com',
            'ReceiptHeader' => 'Test',
            'ReceiptAddress' => 'Test',
            'FeeEmail' => 'flow-screen@example.com',
            'ReceiptContact' => '9999999999',
            'SortOrder' => '1',
            'Logo' => '',
            'created_at' => now(),
        ]);
    }

    // ── who may look ─────────────────────────────────────────────────────

    public function test_a_student_is_refused(): void
    {
        $this->withSession($this->staffSession(['is_student' => 1]))
            ->get('/lms/pal/flow')
            ->assertStatus(403);
    }

    /**
     * What the page offers a principal, asserted on the controller's output
     * rather than on rendered HTML.
     *
     * Rendering means rendering `lmslayout`, which reads a fully hydrated
     * login session straight out of the session store — `name` (and then
     * indexes word TWO of it), `academicYears`, and more past that. A real
     * browser session has all of it; PHPUnit's has none of it, and chasing
     * them one 500 at a time couples this test to a layout it does not own and
     * is not testing.
     *
     * So this asserts the DATA the controller hands the view. That is where
     * everything this screen actually decides lives — which flows to offer,
     * which is current, whether this person may change it, and the plain-words
     * phrasing from humanPhases(). The blade is a presentation of exactly
     * this; the HTTP tests below cover the part that must not be got wrong.
     */
    public function test_a_principal_is_offered_every_flow_in_plain_words(): void
    {
        $view = $this->viewDataFor(['user_profile_name' => 'Principal']);

        $this->assertTrue($view['installed']);
        $this->assertTrue($view['canWrite'], 'A principal may change the flow.');

        // Never chosen is different from chose-the-default, and the screen
        // shows the difference.
        $this->assertNull($view['assigned']);

        $this->assertCount(4, $view['profiles']);

        $current = collect($view['profiles'])->firstWhere('is_current', true);
        $this->assertNotNull($current, 'Exactly one flow must be marked as in use.');
        $this->assertSame('Standard', $current['title']);

        // The engine's vocabulary must not reach a principal. No phase keys,
        // no profile keys, no version ids.
        $this->assertSame('Learn → Practise → Check understanding', $current['steps']);

        foreach ($view['profiles'] as $card) {
            $this->assertNotSame('', $card['blurb'], "Flow '{$card['key']}' must be described in words.");
            $this->assertStringNotContainsString('>', $card['steps'], 'Steps are joined with an arrow, not an operator.');
        }
    }

    /** The four flows are genuinely different, and the screen says how. */
    public function test_each_flow_shows_a_distinct_set_of_steps(): void
    {
        $view = $this->viewDataFor(['user_profile_name' => 'Principal']);

        $byKey = collect($view['profiles'])->keyBy('key');

        $this->assertSame('Learn → Practise → Check understanding', $byKey['standard']['steps']);
        $this->assertSame('Learn → Practise', $byKey['no_cfu']['steps']);
        $this->assertSame('Learn → Check understanding → Practise', $byKey['check_first']['steps']);
    }

    public function test_a_teacher_may_look_but_is_offered_no_controls(): void
    {
        $view = $this->viewDataFor(['user_profile_name' => 'Teacher']);

        $this->assertTrue($view['installed']);
        $this->assertFalse($view['canWrite'], 'A teacher may look and not touch.');
    }

    // ── who may change ───────────────────────────────────────────────────

    public function test_a_principal_can_change_the_schools_flow(): void
    {
        $this->withSession($this->staffSession(['user_profile_name' => 'Principal']))
            ->post('/lms/pal/flow', ['profile_key' => 'no_cfu'])
            ->assertRedirect();

        $this->assertSame(
            'no_cfu',
            app(EsoFlowRegistry::class)->assignedProfileKey($this->subInstituteId)
        );
    }

    public function test_resetting_returns_the_school_to_the_default(): void
    {
        $registry = app(EsoFlowRegistry::class);
        $registry->assign($this->subInstituteId, 'check_first');

        $this->withSession($this->staffSession(['user_profile_name' => 'Principal']))
            ->post('/lms/pal/flow', ['profile_key' => '__default__'])
            ->assertRedirect();

        $registry->forget();

        $this->assertNull(
            $registry->assignedProfileKey($this->subInstituteId),
            'Resetting removes the assignment entirely, rather than assigning the default explicitly.'
        );
    }

    public function test_an_unknown_profile_is_refused_rather_than_stored(): void
    {
        $this->withSession($this->staffSession(['user_profile_name' => 'Principal']))
            ->post('/lms/pal/flow', ['profile_key' => 'mastery_after_learn'])
            ->assertRedirect();

        $this->assertNull(app(EsoFlowRegistry::class)->assignedProfileKey($this->subInstituteId));
    }

    /**
     * The one that actually protects anything.
     *
     * The teacher's page has no button, but a button is presentation. If the
     * route itself is not guarded then the protection is cosmetic and anyone
     * who can read the page source can change how a school teaches.
     */
    public function test_a_teacher_posting_directly_at_the_route_is_refused(): void
    {
        $this->withSession($this->staffSession(['user_profile_name' => 'Teacher']))
            ->post('/lms/pal/flow', ['profile_key' => 'no_cfu'])
            ->assertStatus(403);

        $this->assertNull(app(EsoFlowRegistry::class)->assignedProfileKey($this->subInstituteId));
    }

    public function test_a_student_posting_directly_at_the_route_is_refused(): void
    {
        $this->withSession($this->staffSession(['is_student' => 1, 'user_profile_name' => 'Principal']))
            ->post('/lms/pal/flow', ['profile_key' => 'no_cfu'])
            ->assertStatus(403);

        $this->assertNull(app(EsoFlowRegistry::class)->assignedProfileKey($this->subInstituteId));
    }

    /**
     * The data the controller hands the view, without rendering it.
     *
     * @param  array<string,mixed>  $overrides
     * @return array<string,mixed>
     */
    private function viewDataFor(array $overrides): array
    {
        session()->flush();
        session($this->staffSession($overrides));

        return app(PalFlowAdminController::class)->index(request())->getData();
    }

    /** @param array<string,mixed> $overrides */
    private function staffSession(array $overrides = []): array
    {
        return array_merge([
            'user_id' => 900001,
            'sub_institute_id' => $this->subInstituteId,
            'syear' => '2026',
            'user_profile_id' => 1,
            'user_profile_name' => 'Teacher',
            'is_admin' => 0,
            // lmslayout does explode(" ", Session::get('name')) and then reads
            // $words[1][0], so it needs a name with at least two words or it
            // dies on "Uninitialized string offset 0" before rendering a byte.
            // (That is a real fragility in the shared layout - any user with a
            // single-word name would hit it - but it is not this screen's bug.)
            'name' => 'Test Principal',
            // lmslayout line 122 reads this straight out of the session and
            // then count()s it. A real browser session has it from login;
            // PHPUnit's does not, so without it the layout dies before the
            // page renders. Empty array is enough - the dropdown just renders
            // with no options.
            'academicYears' => [],
        ], $overrides);
    }
}
