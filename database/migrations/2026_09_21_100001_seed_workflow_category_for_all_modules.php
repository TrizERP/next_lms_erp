<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every module bar the Workflow category Fees has, and points each one at
 * the platform-services module whose approvals it configures.
 *
 * Fees was the only bar with a Workflow tab: one category row, and a screen
 * that renders the central workflow console pinned to `module=fees`. Every
 * other module's administrators had to go to Platform services and find their
 * module in the rail, which is the same gap the Onboarding rollout closed —
 * the screen existed, the way in from the module did not.
 *
 * WHAT IS SEEDED. One `workflow` category per bar, sort_order 11 so it lands
 * after AI Stack exactly as it does in Fees, routed at
 * /modules/<slug>/workflow — the dynamic category page, so no new frontend
 * route per module. The Fees row predates this and is left untouched, keeping
 * its own route (/fees/workflow), label and description.
 *
 * A BAR IS NOT A REGISTRY MODULE. The 64 bars come from tblmenumaster;
 * config/platform_services.php declares 16 modules, the ones whose components
 * actually raise approval points. The relation is many-to-one and partial:
 * "Fees" and "Fees Report" share `fees`, while "SQAA Report" has no
 * counterpart at all. Two rules fill the column, and neither of them guesses:
 *
 *   a. The bar's slug, with '-' read as '_', naming a declared module —
 *      'transport' -> transport, 'front-desk' -> front_desk.
 *   b. An explicit entry in MAP below, for the rest.
 *
 * Anything else stays NULL, which the category page reads as "this module has
 * no declared approval points" and answers with a link to Platform services.
 * That is the deliberate answer: pinning Donation Management to some
 * neighbouring module's approvals would be a confident wrong one.
 *
 * Rows that already carry a key are left alone, so a correction made by hand
 * survives a re-run.
 */
return new class extends Migration
{
    private const CATEGORY_KEY = 'workflow';

    /** After AI Stack (10), which is where Fees puts it. */
    private const SORT_ORDER = 11;

    /**
     * Bars whose slug does not name a registry module, and the module each one
     * actually configures. Justified against the points that module declares —
     * see `workflows` in config/platform_services.php — rather than by the
     * names sounding alike.
     *
     * A bar missing from here has no counterpart worth naming.
     */
    private const MAP = [
        // admissions.confirmation.flow — the seat offer these bars work on.
        'admission' => 'admissions',
        'admission-report' => 'admissions',

        // students.transfer.flow and students.identity. The record bars and the
        // two I-card bars all configure the student record's approvals; the
        // Certificate bar is where the transfer certificate is actually issued.
        'student' => 'students',
        'student-report' => 'students',
        'student-request' => 'students',
        'student-medical' => 'students',
        'student-i-card' => 'students',
        'user-i-card' => 'students',
        'certificate' => 'students',

        // attendance.staff — staff attendance is a component of Attendance, not
        // of HR, so the bar that marks it pins there.
        'user-attendance' => 'attendance',

        // academics.lesson_plan.flow, over timetable and syllabus.
        'timetable' => 'academics',
        'curriculum-planning' => 'academics',

        // examination.result.flow and examination.marks.flow.
        'exam' => 'examination',
        'exam-report' => 'examination',

        // lms.content.flow. Every Teach/Learn-side bar publishes content.
        'lms-report' => 'lms',
        'new-pal' => 'lms',
        'teach_learn' => 'lms',
        'test' => 'lms',
        'interactions' => 'lms',

        // hr.leave.flow, hr.payroll.flow, hr.staff.flow.
        'payroll' => 'hr',
        'leave' => 'hr',
        'hrms-report' => 'hr',

        // front_desk.complaint.flow and front_desk.gate_pass.flow.
        'front-desk' => 'front_desk',
        'complaint' => 'front_desk',
        'visitor-management' => 'front_desk',

        // library.catalogue / circulation / fine. No approval point is declared
        // for Library yet; the console says so, which is the true answer for a
        // librarian asking what needs signing off.
        'books' => 'library',
        'library-report' => 'library',
        'stock-verification' => 'library',

        'hostel-report' => 'hostel',
        'inventory-report' => 'inventory',
        'communication-report' => 'communication',
        'fees-report' => 'fees',

        // compliance.retention.flow, over the document repository.
        'document-templates' => 'compliance',

        // reports.scheduled and reports.export.
        'institute-report' => 'reports',
        'other-reports' => 'reports',
    ];

    public function up(): void
    {
        if (! $this->ready()) {
            return;
        }

        $declared = array_flip(array_keys((array) config('platform_services.modules', [])));
        $now = now();

        foreach ($this->bars() as $bar) {
            $exists = DB::table('fees_menu_categories')
                ->where('module_name', $bar->module_name)
                ->where('category_key', self::CATEGORY_KEY)
                ->exists();

            if (! $exists) {
                DB::table('fees_menu_categories')->insert([
                    'module_name' => $bar->module_name,
                    'level2_menu_id' => $bar->level2_menu_id,
                    'category_key' => self::CATEGORY_KEY,
                    'label' => 'Workflow',
                    'description' => sprintf('Approval chains for %s.', $bar->label),
                    'route' => '/modules/'.$bar->module_name.'/'.self::CATEGORY_KEY,
                    'sort_order' => self::SORT_ORDER,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $key = self::MAP[$bar->module_name] ?? str_replace('-', '_', $bar->module_name);

            if (! isset($declared[$key])) {
                continue;
            }

            DB::table('fees_menu_categories')
                ->where('module_name', $bar->module_name)
                ->where('category_key', self::CATEGORY_KEY)
                ->whereNull('workflow_module_key')
                ->update(['workflow_module_key' => $key, 'updated_at' => $now]);
        }
    }

    /**
     * Removes the categories this migration created, leaving the Fees row that
     * predates it. Keys are cleared on the rows that stay, the same way the
     * onboarding mapping reverses.
     */
    public function down(): void
    {
        if (! $this->ready()) {
            return;
        }

        DB::table('fees_menu_categories')
            ->where('category_key', self::CATEGORY_KEY)
            ->where('module_name', '<>', 'fees')
            ->delete();

        DB::table('fees_menu_categories')
            ->where('category_key', self::CATEGORY_KEY)
            ->update(['workflow_module_key' => null, 'updated_at' => now()]);
    }

    private function ready(): bool
    {
        return Schema::hasTable('fees_menu_categories')
            && Schema::hasColumn('fees_menu_categories', 'workflow_module_key')
            && Schema::hasColumn('fees_menu_categories', 'level2_menu_id')
            && Schema::hasTable('tblmenumaster');
    }

    /**
     * Every module bar, named the way the existing categories name it.
     *
     * Read from the category table rather than rediscovered from
     * tblmenumaster: the slugs are already settled there, including the
     * id-suffixed ones a duplicate menu name produced ('task-management-551'),
     * and a second derivation would be a second chance to disagree.
     *
     * The label comes from the level-2 menu so the seeded description reads
     * "Approval chains for Front Desk." rather than repeating the slug.
     */
    private function bars(): Collection
    {
        return DB::table('fees_menu_categories as c')
            ->leftJoin('tblmenumaster as m', 'm.id', '=', 'c.level2_menu_id')
            ->where('c.status', 1)
            ->groupBy('c.module_name', 'c.level2_menu_id', 'm.name')
            ->orderBy('c.module_name')
            ->get([
                'c.module_name',
                'c.level2_menu_id',
                DB::raw('COALESCE(m.name, c.module_name) as label'),
            ]);
    }
};
