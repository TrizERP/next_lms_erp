<?php

use Database\Seeders\OnboardingJourneySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills in `fees_menu_categories.onboarding_module_key` for every module bar,
 * so each module's Onboarding tab shows its own journey instead of an empty
 * category.
 *
 * Two things happen here, in order.
 *
 * 1. The journey template is re-seeded. OnboardingJourneySeeder discovers a
 *    module for every distinct `tblmenumaster.menu_title`, and twelve groups
 *    have appeared since it last ran (Platform Services, Talent Management,
 *    Organization Management, Capability Intelligence, HRIT Management, Task
 *    Management and friends) — those had no journey at all. Re-running is the
 *    seeder's documented mode: it updates the template in place and never
 *    touches `onboarding_progress`, so tenant progress survives. It is called
 *    rather than copied because the eight-step spine must have exactly one
 *    definition.
 *
 * 2. Each module bar's Onboarding category is pointed at one of those
 *    journeys.
 *
 * WHY A BAR AND A JOURNEY ARE NOT THE SAME THING. A module bar is a level-2
 * menu; a journey is a `menu_title` group, which is coarser. "Fees" and "Fees
 * Report" are two bars over one business module, and they share one journey —
 * correctly, because a school onboards Fees once and its reports come with it.
 * So 64 bars map onto ~40 journeys, many-to-one.
 *
 * The mapping rule, most specific first:
 *
 *   a. An explicit override below, for the handful the data cannot answer.
 *   b. A journey whose `menu_title` is the bar's own level-2 menu name —
 *      "Utility" -> Utility, "Organization Management" -> Organization
 *      Management.
 *   c. Otherwise the `menu_title` carried by most of the bar's level-3
 *      children — "Fees Report" -> Fees, "Exam" -> Result, "New PAL" -> LMS.
 *
 * A NOTE ON SHELL JOURNEYS. `syncFromMenuMaster` generates a module for every
 * menu_title it finds, so groups whose menus carry no `database_table` come out
 * as eight manual sign-offs with nothing proved from data. It is tempting to
 * make a proof-backed journey outrank one of those, and that was tried — it
 * fixed Transport and broke three others, because the better-evidenced journey
 * next door usually belongs to a *different module*. Showing Engagement the LMS
 * journey, or Platform Services the Communication journey, is a worse answer
 * than showing each its own thin one: a generic checklist for the right module
 * beats a detailed checklist for the wrong one. So the rules stay name-first
 * and the exceptions stay in OVERRIDES, where they are visible.
 *
 * Rows that already carry a key are left alone, so a correction made by hand
 * survives a re-run. This is ordinary configuration: the heuristic is the
 * starting point, not the authority.
 */
return new class extends Migration
{
    /**
     * Bars the two rules get wrong, and why.
     *
     * '' means deliberately unmapped — the bar has no single journey and the
     * page says so rather than picking one of seven at random.
     */
    private const OVERRIDES = [
        // Children are User and Mobile App report screens, but a timetable is
        // part of setting up Attendance — that is where `timetable` is proved.
        'timetable' => 'attendance',
        // Two spellings of one module. A lone level-2 row titled "Transport"
        // produced a shell journey of manual steps, and the bar's own name
        // matches it exactly; the real journey is "Transportation", which
        // proves vehicles, drivers, routes and stops from their tables.
        'transport' => 'transportation',
        // Two near-identical menu_titles exist, 'Talent Management' (level-2
        // rows) and the misspelt 'Talent Managment' (the level-3 screens). The
        // journey wants the one the actual screens carry.
        'talent-management' => 'talent_managment',
        // A grab bag: Complaint, Consent, Front Desk, Petty Cash, PTM, User and
        // Visitor reports under one bar. Each of those modules onboards on its
        // own journey; this bar owns none of them.
        'other-reports' => '',
    ];

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        (new OnboardingJourneySeeder())->run();

        $journeyByTitle = $this->journeysByMenuTitle();
        $journeyKeys = array_flip(
            DB::table('onboarding_module')
                ->where('sub_institute_id', 0)
                ->where('status', 1)
                ->pluck('module_key')
                ->all()
        );
        $rows = DB::table('fees_menu_categories')
            ->where('category_key', 'onboarding')
            ->whereNull('onboarding_module_key')
            ->get(['id', 'module_name', 'level2_menu_id']);

        foreach ($rows as $row) {
            $key = array_key_exists($row->module_name, self::OVERRIDES)
                ? self::OVERRIDES[$row->module_name]
                : $this->resolve($row, $journeyByTitle);

            if ($key === '' || $key === null || ! isset($journeyKeys[$key])) {
                continue;
            }

            DB::table('fees_menu_categories')
                ->where('id', $row->id)
                ->update(['onboarding_module_key' => $key, 'updated_at' => now()]);
        }
    }

    /**
     * Clears only what this migration set, leaving any key edited afterwards —
     * it cannot tell the two apart, so it errs towards not deleting somebody's
     * correction. The journeys the seeder created are left in place; they are
     * template rows every other onboarding surface reads.
     */
    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('category_key', 'onboarding')
            ->whereNotNull('onboarding_module_key')
            ->update(['onboarding_module_key' => null, 'updated_at' => now()]);
    }

    private function ready(): bool
    {
        return Schema::hasTable('fees_menu_categories')
            && Schema::hasColumn('fees_menu_categories', 'onboarding_module_key')
            && Schema::hasColumn('fees_menu_categories', 'level2_menu_id')
            && Schema::hasTable('onboarding_module')
            && Schema::hasTable('tblmenumaster');
    }

    /** @return array<string,string> normalized menu_title => module_key */
    private function journeysByMenuTitle(): array
    {
        $index = [];

        foreach (DB::table('onboarding_module')
            ->where('sub_institute_id', 0)
            ->where('status', 1)
            ->orderBy('sort_order')
            ->get(['module_key', 'menu_title']) as $journey) {
            $title = $this->normalize((string) $journey->menu_title);

            // First wins: the curated modules are seeded ahead of the ones
            // discovered from the menu tree, so a hand-written journey beats a
            // generated one carrying the same title.
            if ($title !== '' && ! isset($index[$title])) {
                $index[$title] = (string) $journey->module_key;
            }
        }

        return $index;
    }

    /** @param  array<string,string>  $journeyByTitle */
    private function resolve(object $row, array $journeyByTitle): ?string
    {
        $level2Id = (int) ($row->level2_menu_id ?? 0);

        if ($level2Id <= 0) {
            return null;
        }

        // Candidate titles in rule order: the bar's own name, then the titles
        // its screens carry, most common first. Ordered by count then title so
        // the answer never depends on row order.
        $titles = DB::table('tblmenumaster')
            ->where('parent_menu_id', $level2Id)
            ->where('level', 3)
            ->where('status', 1)
            ->whereNotNull('menu_title')
            ->where('menu_title', '<>', '')
            ->groupBy('menu_title')
            ->orderByRaw('COUNT(*) DESC')
            ->orderBy('menu_title')
            ->pluck('menu_title')
            ->all();

        array_unshift(
            $titles,
            (string) DB::table('tblmenumaster')->where('id', $level2Id)->value('name')
        );

        foreach ($titles as $title) {
            $normalized = $this->normalize((string) $title);

            if ($normalized !== '' && isset($journeyByTitle[$normalized])) {
                return $journeyByTitle[$normalized];
            }
        }

        return null;
    }

    /** Menu titles are hand-typed: 'PTM ' and 'PTM' are the same group. */
    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
};
