<?php

namespace App\Http\Controllers\lms\pal;

use App\Http\Controllers\Controller;
use App\Services\PAL\Flow\EsoFlowRegistry;
use App\Services\PAL\Flow\EsoFlowResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Teach/Learn → PAL → Learning Flow.
 *
 * The screen a principal uses to choose how their school teaches. Until this
 * existed, the only way to change a school's flow was `php artisan tinker`,
 * which meant it was not really a product feature at all — it was a thing
 * developers could do on request.
 *
 * ---------------------------------------------------------------------------
 * WHAT A SCHOOL MAY AND MAY NOT CHANGE
 * ---------------------------------------------------------------------------
 * A school picks one of four published flows. It cannot invent a fifth, and it
 * cannot change what "mastered" means — that stays 3 demonstrations with at
 * least one unaided at every school on the estate, so attainment is comparable
 * between them and the multi-year career profile means something.
 *
 * The screen says so out loud rather than leaving it implicit. A principal who
 * does not know which dial is missing will ask for it; one who is told why it
 * is missing usually does not.
 *
 * ---------------------------------------------------------------------------
 * ACCESS
 * ---------------------------------------------------------------------------
 *   students            no access at all — this is not a learner surface
 *   staff and teachers  may look
 *   admins, principals  may look and change
 *
 * The writer list is config/pal_flow.php guards.writer_profiles, which is the
 * same list ArchitectureRegistry uses, so the two admin surfaces cannot
 * disagree about who is allowed to reconfigure a school.
 */
class PalFlowAdminController extends Controller
{
    public function __construct(
        protected EsoFlowRegistry $registry,
        protected EsoFlowResolver $resolver,
    ) {
    }

    /** GET /lms/pal/flow */
    public function index(Request $request)
    {
        if (session()->get('is_student')) {
            abort(403, 'The learning flow settings are not available to students.');
        }

        $subInstituteId = (int) session()->get('sub_institute_id');

        if ($subInstituteId === 0) {
            abort(403, 'No institute is associated with this session.');
        }

        // Degrades rather than errors. 408 of this estate's 996 migrations are
        // pending, so a host without the flow tables is an ordinary state, and
        // the honest thing is to say the feature is not installed here rather
        // than to show a broken screen.
        if (! $this->registry->available()) {
            return view('lms.pal.flow-admin', [
                'installed' => false,
                'canWrite' => false,
                'current' => null,
                'profiles' => [],
                'assigned' => null,
                'subInstituteId' => $subInstituteId,
            ]);
        }

        $current = $this->resolver->resolve($subInstituteId);

        return view('lms.pal.flow-admin', [
            'installed' => true,
            'canWrite' => $this->mayWrite(),
            'current' => $current,
            'profiles' => $this->profileCards($current->profileKey()),
            // Null means "never chosen", which is different from "chose the
            // standard one" and the screen shows the difference.
            'assigned' => $this->registry->assignedProfileKey($subInstituteId),
            'subInstituteId' => $subInstituteId,
        ]);
    }

    /** POST /lms/pal/flow */
    public function assign(Request $request): RedirectResponse
    {
        if (session()->get('is_student') || ! $this->mayWrite()) {
            abort(403, 'You do not have permission to change the learning flow.');
        }

        $subInstituteId = (int) session()->get('sub_institute_id');

        if ($subInstituteId === 0) {
            abort(403, 'No institute is associated with this session.');
        }

        $request->validate([
            'profile_key' => ['required', 'string', 'max:64'],
        ]);

        $profileKey = (string) $request->input('profile_key');

        try {
            if ($profileKey === '__default__') {
                $this->registry->unassign($subInstituteId);
                $message = 'Your school is back on the standard learning flow.';
            } else {
                $this->registry->assign($subInstituteId, $profileKey, (int) session()->get('user_id') ?: null);

                $this->resolver->forget();
                $plan = $this->resolver->resolve($subInstituteId);

                $message = 'Learning flow updated. New topics now follow: '
                    . $this->humanPhases($plan->phaseOrder()) . '.';
            }
        } catch (Throwable $e) {
            return back()->with('pal_flow_error', $e->getMessage());
        }

        return back()->with('pal_flow_status', $message);
    }

    /**
     * The four flows, described for someone who is not an engineer.
     *
     * The descriptions come from here rather than from config/pal_flow.php
     * because that file's wording is written for developers reading the
     * catalogue, and a principal choosing how their school teaches needs a
     * different sentence for the same thing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function profileCards(string $currentKey): array
    {
        $plain = [
            'standard' => [
                'title' => 'Standard',
                'blurb' => 'A short test first, then teach, practise, and check understanding before moving on.',
                'best_for' => 'Most schools. This is what you are using now unless you change it.',
            ],
            'no_cfu' => [
                'title' => 'No check step',
                'blurb' => 'Teach, then practise. The separate "check you understood" screen is removed.',
                'best_for' => 'Schools short on lesson time who want fewer screens per topic.',
            ],
            'check_first' => [
                'title' => 'Check before practice',
                'blurb' => 'Teach, check the student understood, and only then practise.',
                'best_for' => 'Schools that want to catch misunderstandings before practice begins.',
            ],
            'diagnostic_free' => [
                'title' => 'No entry test',
                'blurb' => 'Skip the short placement test at the start of a topic. Go straight to teaching.',
                'best_for' => 'Schools that place students using their own entrance exam.',
            ],
        ];

        $cards = [];

        foreach ($this->registry->catalogue() as $profile) {
            $key = $profile['profile_key'];
            $words = $plain[$key] ?? ['title' => $profile['label'], 'blurb' => (string) $profile['description'], 'best_for' => ''];

            $cards[] = [
                'key' => $key,
                'title' => $words['title'],
                'blurb' => $words['blurb'],
                'best_for' => $words['best_for'],
                'is_current' => $key === $currentKey,
                'is_default' => (bool) $profile['is_default'],
                'steps' => $this->humanPhases($this->resolver->profile($key)->phaseOrder()),
                'schools_using' => (int) $profile['institutes'],
            ];
        }

        return $cards;
    }

    /**
     * Phase keys as a sentence a principal can read.
     *
     * "learn > practice > check" is how the engine thinks. It is not how
     * anyone choosing a teaching approach thinks.
     *
     * @param  array<int, string>  $phases
     */
    private function humanPhases(array $phases): string
    {
        $names = [
            'learn' => 'Learn',
            'practice' => 'Practise',
            'check' => 'Check understanding',
        ];

        return implode(' → ', array_map(
            static fn (string $p): string => $names[$p] ?? ucfirst($p),
            $phases
        ));
    }

    /**
     * May this session change the flow?
     *
     * Mirrors ArchitectureRegistry::mayWrite() against the session rather than
     * a token, because this is the browser surface. Same list, same rule.
     */
    private function mayWrite(): bool
    {
        if ((int) session()->get('is_admin', 0) > 0) {
            return true;
        }

        $profile = strtolower(trim((string) session()->get('user_profile_name', '')));

        if ($profile === '') {
            return false;
        }

        foreach ((array) config('pal_flow.guards.writer_profiles', []) as $allowed) {
            if ($profile === strtolower((string) $allowed)) {
                return true;
            }
        }

        return false;
    }
}
