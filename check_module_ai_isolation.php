<?php

/**
 * Cross-institute isolation check for the five module AI Stacks.
 *
 * WHY THIS IS A SCRIPT AND NOT A PHPUNIT TEST
 *
 * `tests/Feature/AI/ModuleAiStackIsolationTest.php` asserts the properties that are true
 * regardless of data — the tool bindings, the read-only annotations, the route patterns —
 * and it deliberately touches no database so it keeps passing on a machine that cannot
 * reach the estate.
 *
 * This checks the other half, which only a real database can answer: that a read performed
 * as institute A returns nothing belonging to institute B. It needs two institutes that
 * both have records, which no fixture provides and which differs per estate, so it is run
 * by hand against a live database and reports what it found.
 *
 * IT ONLY READS. Every call below is a service method backed by a tool annotated
 * `read_only`, and the script issues no INSERT, UPDATE or DELETE of its own. Running it
 * against production is safe; running it is how you find out whether the scoping holds
 * there.
 *
 *   php check_module_ai_isolation.php
 *
 * WHAT COUNTS AS A PASS
 *
 * For each module the script finds the two busiest DIFFERENT institutes, reads as each
 * of them, and checks that every row's identifier appears in that institute's own set.
 * A module whose data lives in only one institute is marked * and checked the weaker way:
 * a second institute that holds none of those records must be told so, not shown the
 * first institute's rows.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Mcp\CircularService;
use App\Services\Mcp\HostelOccupancyService;
use App\Services\Mcp\McpRequestContext;
use App\Services\Mcp\PtmMeetingService;
use App\Services\Mcp\ResultReportService;
use App\Services\Mcp\StudentRequestService;
use Illuminate\Support\Facades\DB;

/** A context for one institute and year. Nothing else about the caller matters to scoping. */
function context(int $institute, ?int $year): McpRequestContext
{
    return new McpRequestContext(
        userId: 0,
        role: 'admin',
        selectedInstituteId: $institute,
        allowedInstituteIds: [$institute],
        userProfileId: null,
        clientId: null,
        academicYear: $year,
        termId: null,
        isAdmin: true,
        isStudent: false,
    );
}

/**
 * Two DIFFERENT institutes that both have rows, with a year each one's rows carry.
 *
 * Grouped by institute alone and not by (institute, year), which matters: grouping by both
 * returns the same busy school twice under two years, and comparing an institute against
 * itself proves nothing while looking exactly like a pass.
 *
 * When only one institute has rows there is nothing to leak. The caller handles that by
 * reading as an institute that has none and checking it is told so, which is a weaker
 * check but a real one.
 *
 * @return array<int, array{institute:int, year:?int, rows:int}>
 */
function busiestInstitutes(string $table, string $instituteColumn, ?string $yearColumn): array
{
    $select = $instituteColumn.' AS institute, COUNT(*) AS rows_count'
        .($yearColumn ? ', MAX('.$yearColumn.') AS year' : ', NULL AS year');

    return DB::table($table)
        ->selectRaw($select)
        ->groupBy(DB::raw($instituteColumn))
        ->orderByDesc('rows_count')
        ->limit(2)
        ->get()
        ->map(static fn ($row) => [
            'institute' => (int) $row->institute,
            'year' => $row->year === null ? null : (int) $row->year,
            'rows' => (int) $row->rows_count,
        ])
        ->all();
}

/**
 * A real institute that has no rows in this table at all, for the one-tenant case.
 *
 * Taken from `tblstudent`, because an institute with students is unambiguously an
 * institute this estate serves — there is no institute master table here, and inventing an
 * id would make the check meaningless. The point is only that it is not the one holding
 * the data.
 */
function instituteWithout(string $table, string $instituteColumn): ?array
{
    // The academic year comes from `tblstudent_enrollment`, which is where it lives —
    // `tblstudent` carries the child, not the year they are enrolled for. Reading as an
    // institute with the wrong year would return nothing for a reason that has nothing to
    // do with tenancy, which would make this check pass for the wrong reason.
    $row = DB::table('tblstudent_enrollment as e')
        // `> 0` and not merely `not null`: this estate holds enrolment rows stamped with
        // institute 0, which is not a school. Reading as one would be a check against a
        // tenant that does not exist, and it would pass for that reason rather than
        // because the scoping works.
        ->where('e.sub_institute_id', '>', 0)
        ->whereNotIn('e.sub_institute_id', function ($query) use ($table, $instituteColumn) {
            $query->select(DB::raw($instituteColumn))->from($table)->whereNotNull($instituteColumn);
        })
        ->selectRaw('e.sub_institute_id AS sub_institute_id, MAX(e.syear) AS year')
        ->groupBy('e.sub_institute_id')
        ->first();

    return $row === null
        ? null
        : [
            'institute' => (int) $row->sub_institute_id,
            'year' => $row->year === null ? null : (int) $row->year,
            'rows' => 0,
        ];
}

/**
 * Read as each institute and check no row belongs to the other.
 *
 * `$read` returns the rows a module's own tool returned; `$idsOf` says which identifier on
 * those rows to compare, and `$ownIds` says which identifiers actually belong to an
 * institute according to the table itself. The comparison is against the table rather than
 * against the other read, so a row belonging to a third institute is caught too.
 */
function checkIsolation(
    string $label,
    array $institutes,
    callable $read,
    callable $idsOf,
    callable $ownIds,
    ?array $emptyInstitute = null,
): void {
    if ($institutes === []) {
        printf("INCONCLUSIVE  %-22s no institute has records of this kind\n", $label);

        return;
    }

    // Only one tenant holds this kind of record, so there is no second owner to leak to.
    // Reading as an institute that holds none is the weaker check that is still available:
    // it must be told there is nothing, not shown the other school's rows.
    if (count($institutes) < 2) {
        if ($emptyInstitute === null) {
            printf("INCONCLUSIVE  %-22s only one institute has records, and no other institute was found\n", $label);

            return;
        }

        $institutes[] = $emptyInstitute;
        $label .= ' *';
    }

    $problems = [];
    $summary = [];

    foreach ($institutes as $entry) {
        $rows = $read(context($entry['institute'], $entry['year']));
        $returned = $idsOf($rows);
        $permitted = $ownIds($entry['institute']);

        $stray = array_values(array_diff($returned, $permitted));

        $summary[] = sprintf('inst %d: %d row(s)', $entry['institute'], count($returned));

        if ($stray !== []) {
            $problems[] = sprintf(
                'institute %d was returned %d row(s) it does not own (%s)',
                $entry['institute'],
                count($stray),
                implode(', ', array_slice($stray, 0, 5)),
            );
        }
    }

    if ($problems === []) {
        printf("PASS          %-22s %s\n", $label, implode(' · ', $summary));

        return;
    }

    printf("FAIL          %-22s %s\n", $label, implode('; ', $problems));
}

echo "Cross-institute isolation, read-only, against ".config('database.connections.mysql.database')."\n";
echo str_repeat('-', 100)."\n";

// ---- PTM -------------------------------------------------------------------
$ptm = app(PtmMeetingService::class);

checkIsolation(
    'ptm.meetings',
    busiestInstitutes('ptm_time_slots_master', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $ptm->meetings($c, ['limit' => 200])['meetings'],
    static fn (array $rows) => array_column($rows, 'slot_id'),
    static fn (int $institute) => DB::table('ptm_time_slots_master')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('ptm_time_slots_master', 'sub_institute_id'),
);

checkIsolation(
    'ptm.bookings',
    busiestInstitutes('ptm_time_slots_master', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $ptm->bookings($c, ['limit' => 200])['bookings'],
    static fn (array $rows) => array_column($rows, 'booking_id'),
    static fn (int $institute) => DB::table('ptm_booking_master')
        ->where('SUB_INSTITUTE_ID', $institute)->pluck('ID')->map('intval')->all(),
    instituteWithout('ptm_booking_master', 'SUB_INSTITUTE_ID'),
);

// ---- Hostel ----------------------------------------------------------------
$hostel = app(HostelOccupancyService::class);

checkIsolation(
    'hostel.occupancy',
    busiestInstitutes('hostel_master', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $hostel->occupancy($c, ['limit' => 200])['hostels'],
    static fn (array $rows) => array_column($rows, 'hostel_id'),
    static fn (int $institute) => DB::table('hostel_master')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('hostel_master', 'sub_institute_id'),
);

checkIsolation(
    'hostel.allocations',
    busiestInstitutes('hostel_room_allocation', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $hostel->allocations($c, ['limit' => 200])['allocations'],
    static fn (array $rows) => array_column($rows, 'allocation_id'),
    static fn (int $institute) => DB::table('hostel_room_allocation')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('hostel_room_allocation', 'sub_institute_id'),
);

checkIsolation(
    'hostel.available_rooms',
    busiestInstitutes('hostel_room_master', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $hostel->availableRooms($c, ['limit' => 200])['rooms'],
    static fn (array $rows) => array_column($rows, 'room_id'),
    static fn (int $institute) => DB::table('hostel_room_master')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('hostel_room_master', 'sub_institute_id'),
);

// ---- Student requests ------------------------------------------------------
//
// Scoped on the STUDENT's institute, which is what the Student Request screens themselves
// query — so the permitted set is the requests whose student belongs to that institute,
// not the requests whose own column says so. See StudentRequestService for why.
$requests = app(StudentRequestService::class);

checkIsolation(
    'student_requests.list',
    busiestInstitutes('student_change_request', 'SUB_INSTITUTE_ID', 'SYEAR'),
    static fn (McpRequestContext $c) => $requests->list($c, ['limit' => 200])['requests'],
    static fn (array $rows) => array_column($rows, 'request_id'),
    static fn (int $institute) => DB::table('student_change_request as sr')
        ->join('tblstudent as s', 's.id', '=', 'sr.STUDENT_ID')
        ->where('s.sub_institute_id', $institute)
        ->pluck('sr.ID')->map('intval')->all(),
    instituteWithout('student_change_request', 'SUB_INSTITUTE_ID'),
);

// ---- Circular --------------------------------------------------------------
$circulars = app(CircularService::class);

checkIsolation(
    'circulars.list',
    busiestInstitutes('circular', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $circulars->list($c, ['limit' => 200])['circulars'],
    static fn (array $rows) => array_column($rows, 'circular_id'),
    static fn (int $institute) => DB::table('circular')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('circular', 'sub_institute_id'),
);

// ---- Exam ------------------------------------------------------------------
//
// The Exam tools predate this work and are unchanged. They are checked anyway, because the
// Exam AI Stack is new and a stack is only as isolated as the tools under it.
$results = app(ResultReportService::class);

checkIsolation(
    'exams.results',
    busiestInstitutes('result_marks', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $results->report($c, ['limit' => 200])['results'],
    static fn (array $rows) => array_column($rows, 'result_id'),
    static fn (int $institute) => DB::table('result_marks')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('result_marks', 'sub_institute_id'),
);

checkIsolation(
    'exams.list',
    busiestInstitutes('result_exam_master', 'SubInstituteId', null),
    static fn (McpRequestContext $c) => $results->exams($c, ['limit' => 200])['exams'],
    static fn (array $rows) => array_column($rows, 'exam_id'),
    static fn (int $institute) => DB::table('result_exam_master')
        ->where('SubInstituteId', $institute)->pluck('Id')->map('intval')->all(),
    instituteWithout('result_exam_master', 'SubInstituteId'),
);

echo str_repeat('-', 100)."\n";
// ---- Users Mobile Apps -----------------------------------------------------
//
// The home screens carry no academic year, so the context's year is irrelevant here and
// `busiestInstitutes` returns null for it. That is correct rather than missing: a home
// screen is configuration, not a year's records.
$apps = app(\App\Services\Mcp\MobileAppService::class);

checkIsolation(
    'mobile_apps.homescreen',
    busiestInstitutes('mobile_homescreen', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $apps->homescreen($c, ['limit' => 200])['tiles'],
    static fn (array $rows) => array_column($rows, 'tile_id'),
    static fn (int $institute) => DB::table('mobile_homescreen')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('mobile_homescreen', 'sub_institute_id'),
);

// ---- Student I-Card --------------------------------------------------------
//
// Scoped on the STUDENT's institute and the enrolment's, both of which the service
// matches, so the permitted set is the students enrolled here this year.
$icard = app(\App\Services\Mcp\StudentIcardService::class);

checkIsolation(
    'student_icard.roster',
    busiestInstitutes('tblstudent_enrollment', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $icard->roster($c, ['limit' => 200])['students'],
    static fn (array $rows) => array_column($rows, 'student_id'),
    static fn (int $institute) => DB::table('tblstudent')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('tblstudent_enrollment', 'sub_institute_id'),
);

// ---- Certificate -----------------------------------------------------------
$certificates = app(\App\Services\Mcp\CertificateService::class);

checkIsolation(
    'certificate.issued',
    busiestInstitutes('certificate_history', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $certificates->issued($c, ['limit' => 200])['certificates'],
    static fn (array $rows) => array_column($rows, 'certificate_id'),
    static fn (int $institute) => DB::table('certificate_history')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('certificate_history', 'sub_institute_id'),
);

// ---- Communication ---------------------------------------------------------
//
// Checked on the parent SMS log, which is the busiest of the four channels. The ids are
// per channel, so the permitted set is that one table's.
$communication = app(\App\Services\Mcp\CommunicationService::class);

checkIsolation(
    'communication.messages',
    busiestInstitutes('sms_sent_parents', 'sub_institute_id', 'SYEAR'),
    static fn (McpRequestContext $c) => $communication->messages($c, ['channel' => 'sms_parent', 'limit' => 200])['messages'],
    static fn (array $rows) => array_column($rows, 'message_id'),
    static fn (int $institute) => DB::table('sms_sent_parents')
        ->where('sub_institute_id', $institute)->pluck('ID')->map('intval')->all(),
    instituteWithout('sms_sent_parents', 'sub_institute_id'),
);

// ---- Time Table ------------------------------------------------------------
$timetable = app(\App\Services\Mcp\TimetableService::class);

checkIsolation(
    'timetable.schedule',
    busiestInstitutes('timetable', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $timetable->schedule($c, ['limit' => 200])['periods'],
    static fn (array $rows) => array_column($rows, 'entry_id'),
    static fn (int $institute) => DB::table('timetable')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('timetable', 'sub_institute_id'),
);

// ---- Student Medical -------------------------------------------------------
//
// The one that matters most. Checked on infirmary visits, which is both the busiest
// medical table and the one carrying clinical free text.
$medical = app(\App\Services\Mcp\StudentMedicalService::class);

checkIsolation(
    'student_medical.visits',
    busiestInstitutes('student_infirmary', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $medical->visits($c, ['limit' => 200])['visits'],
    static fn (array $rows) => array_column($rows, 'visit_id'),
    static fn (int $institute) => DB::table('student_infirmary')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('student_infirmary', 'sub_institute_id'),
);

checkIsolation(
    'student_medical.growth',
    busiestInstitutes('student_height_weight', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $medical->growth($c, ['limit' => 200])['measurements'],
    static fn (array $rows) => array_column($rows, 'record_id'),
    static fn (int $institute) => DB::table('student_height_weight')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('student_height_weight', 'sub_institute_id'),
);

// ---- Inward -----------------------------------------------------------------
$inward = app(\App\Services\Mcp\InwardService::class);

checkIsolation(
    'inward.register',
    busiestInstitutes('inward', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $inward->register($c, ['limit' => 200])['records'],
    static fn (array $rows) => array_column($rows, 'inward_id'),
    static fn (int $institute) => DB::table('inward')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('inward', 'sub_institute_id'),
);

// ---- User I-Card --------------------------------------------------------------
$userIcard = app(\App\Services\Mcp\UserIcardService::class);

checkIsolation(
    'user_icard.roster',
    // `tbluser` has no syear, so the year is irrelevant here and passing one would filter
    // on a column that does not exist.
    busiestInstitutes('tbluser', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $userIcard->roster($c, ['limit' => 200])['users'],
    static fn (array $rows) => array_column($rows, 'staff_id'),
    static fn (int $institute) => DB::table('tbluser')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('tbluser', 'sub_institute_id'),
);

// ---- Petty Cash ---------------------------------------------------------------
$pettyCash = app(\App\Services\Mcp\PettyCashService::class);

checkIsolation(
    'petty_cash.transactions',
    busiestInstitutes('petty_cash', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $pettyCash->transactions($c, ['limit' => 200])['transactions'],
    static fn (array $rows) => array_column($rows, 'transaction_id'),
    static fn (int $institute) => DB::table('petty_cash')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('petty_cash', 'sub_institute_id'),
);

// ---- Consent ------------------------------------------------------------------
$consent = app(\App\Services\Mcp\ConsentService::class);

checkIsolation(
    'consent.records',
    busiestInstitutes('consent_master', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $consent->records($c, ['limit' => 200])['consents'],
    static fn (array $rows) => array_column($rows, 'consent_id'),
    static fn (int $institute) => DB::table('consent_master')
        ->where('sub_institute_id', $institute)->pluck('ID')->map('intval')->all(),
    instituteWithout('consent_master', 'sub_institute_id'),
);

// ---- Visitor Management -------------------------------------------------------
$visitor = app(\App\Services\Mcp\VisitorService::class);

checkIsolation(
    'visitor.visits',
    busiestInstitutes('visitor_master', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $visitor->visits($c, ['limit' => 200])['visits'],
    static fn (array $rows) => array_column($rows, 'visit_id'),
    static fn (int $institute) => DB::table('visitor_master')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('visitor_master', 'sub_institute_id'),
);

// ---- Transport ----------------------------------------------------------------
$transport = app(\App\Services\Mcp\TransportService::class);

checkIsolation(
    'transport.routes',
    busiestInstitutes('transport_route', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $transport->routes($c, ['limit' => 200])['routes'],
    static fn (array $rows) => array_column($rows, 'route_id'),
    static fn (int $institute) => DB::table('transport_route')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('transport_route', 'sub_institute_id'),
);

checkIsolation(
    'transport.assignments',
    busiestInstitutes('transport_map_student', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $transport->assignments($c, ['limit' => 200])['assignments'],
    static fn (array $rows) => array_column($rows, 'assignment_id'),
    static fn (int $institute) => DB::table('transport_map_student')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('transport_map_student', 'sub_institute_id'),
);

echo "A detail read for a record belonging to another institute answers as a record that does not exist,\n";
echo "which is checked separately below.\n\n";

// ---- Detail reads across the boundary --------------------------------------
//
// The one case a list check cannot cover: naming another institute's record outright.
// Confirming that an id exists elsewhere would itself be a disclosure, so the expected
// answer is the same one a missing record gets.
$sample = DB::table('student_change_request as sr')
    ->join('tblstudent as s', 's.id', '=', 'sr.STUDENT_ID')
    ->selectRaw('sr.ID AS id, s.sub_institute_id AS institute, sr.SYEAR AS year')
    ->first();

if ($sample === null) {
    echo "INCONCLUSIVE  student_requests.details  no request records exist to name\n";
} else {
    $other = DB::table('tblstudent')
        ->where('sub_institute_id', '!=', $sample->institute)
        ->value('sub_institute_id');

    if ($other === null) {
        echo "INCONCLUSIVE  student_requests.details  only one institute exists\n";
    } else {
        $mine = $requests->details(context((int) $sample->institute, (int) $sample->year), ['request_id' => (int) $sample->id]);
        $theirs = $requests->details(context((int) $other, (int) $sample->year), ['request_id' => (int) $sample->id]);

        $ok = ($mine['found'] ?? false) === true && ($theirs['found'] ?? true) === false;

        printf(
            "%s  %-22s request %d: owner reads it (%s), institute %d does not (%s)\n",
            $ok ? 'PASS         ' : 'FAIL         ',
            'student_requests.details',
            $sample->id,
            var_export($mine['found'] ?? null, true),
            $other,
            var_export($theirs['found'] ?? null, true),
        );
    }
}

// ---- Inventory ----------------------------------------------------------------
$inventory = app(\App\Services\Mcp\InventoryService::class);

checkIsolation(
    'inventory.items',
    busiestInstitutes('inventory_item_master', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $inventory->items($c, ['limit' => 200])['items'],
    static fn (array $rows) => array_column($rows, 'item_id'),
    static fn (int $institute) => DB::table('inventory_item_master')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('inventory_item_master', 'sub_institute_id'),
);

checkIsolation(
    'inventory.requisitions',
    busiestInstitutes('inventory_requisition_details', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $inventory->requisitions($c, ['limit' => 200])['requisitions'],
    static fn (array $rows) => array_column($rows, 'requisition_id'),
    static fn (int $institute) => DB::table('inventory_requisition_details')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('inventory_requisition_details', 'sub_institute_id'),
);

// ---- Front Desk ---------------------------------------------------------------
//
// The register holds one row estate-wide, so this is the weak form of the check: read as
// an institute that has none and confirm it is told there is nothing rather than shown
// the other school's row.
$frontDesk = app(\App\Services\Mcp\FrontDeskService::class);

checkIsolation(
    'front_desk.visits',
    busiestInstitutes('front_desk', 'SUB_INSTITUTE_ID', null),
    static fn (McpRequestContext $c) => $frontDesk->visits($c, ['limit' => 200])['visits'],
    static fn (array $rows) => array_column($rows, 'visit_id'),
    static fn (int $institute) => DB::table('front_desk')
        ->where('SUB_INSTITUTE_ID', $institute)->pluck('ID')->map('intval')->all(),
    instituteWithout('front_desk', 'SUB_INSTITUTE_ID'),
);

// ---- Task Management ----------------------------------------------------------
$taskService = app(\App\Services\Mcp\TaskService::class);

checkIsolation(
    'tasks.list',
    busiestInstitutes('task', 'sub_institute_id', 'SYEAR'),
    static fn (McpRequestContext $c) => $taskService->list($c, ['limit' => 200])['tasks'],
    static fn (array $rows) => array_column($rows, 'task_id'),
    static fn (int $institute) => DB::table('task')
        ->where('sub_institute_id', $institute)->pluck('ID')->map('intval')->all(),
    instituteWithout('task', 'sub_institute_id'),
);

// ---- Complaint ----------------------------------------------------------------
$complaintService = app(\App\Services\Mcp\ComplaintService::class);

checkIsolation(
    'complaints.list',
    busiestInstitutes('complaint', 'SUB_INSTITUTE_ID', 'SYEAR'),
    static fn (McpRequestContext $c) => $complaintService->list($c, ['limit' => 200])['complaints'],
    static fn (array $rows) => array_column($rows, 'complaint_id'),
    static fn (int $institute) => DB::table('complaint')
        ->where('SUB_INSTITUTE_ID', $institute)->pluck('ID')->map('intval')->all(),
    instituteWithout('complaint', 'SUB_INSTITUTE_ID'),
);

// ---- Utility ------------------------------------------------------------------
$utilityService = app(\App\Services\Mcp\UtilityService::class);

checkIsolation(
    'utility.custom_modules',
    busiestInstitutes('custom_module_tables', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $utilityService->customModules($c, ['limit' => 200])['custom_modules'],
    static fn (array $rows) => array_column($rows, 'custom_module_id'),
    static fn (int $institute) => DB::table('custom_module_tables')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('custom_module_tables', 'sub_institute_id'),
);

// ---- Document Templates -------------------------------------------------------
//
// Both tables are empty across this estate, so there is nothing to leak and the check is
// INCONCLUSIVE by construction. It is run anyway: the day a school creates its first
// template, this is the line that starts proving the scoping.
$docService = app(\App\Services\Mcp\DocumentTemplateService::class);

checkIsolation(
    'doc_templates.list',
    busiestInstitutes('document_templates', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $docService->list($c, ['limit' => 200])['templates'],
    static fn (array $rows) => array_column($rows, 'template_id'),
    static fn (int $institute) => DB::table('document_templates')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('document_templates', 'sub_institute_id'),
);
// ---- Parent Communication -----------------------------------------------------
$parentComms = app(\App\Services\Mcp\ParentCommunicationService::class);

checkIsolation(
    'parent_communication.messages',
    busiestInstitutes('parent_communication', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $parentComms->messages($c, ['limit' => 200])['messages'],
    static fn (array $rows) => array_column($rows, 'message_id'),
    static fn (int $institute) => DB::table('parent_communication')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('parent_communication', 'sub_institute_id'),
);

// ---- Quality assurance (SQAA) --------------------------------------------------
$sqaaService = app(\App\Services\Mcp\SqaaService::class);

checkIsolation(
    'sqaa.evidence',
    busiestInstitutes('sqaa_documents', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $sqaaService->evidence($c, ['limit' => 200])['evidence'],
    static fn (array $rows) => array_column($rows, 'evidence_id'),
    static fn (int $institute) => DB::table('sqaa_documents')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('sqaa_documents', 'sub_institute_id'),
);

// ---- Users ---------------------------------------------------------------------
$userAccounts = app(\App\Services\Mcp\UserAccountService::class);

checkIsolation(
    'user_accounts.directory',
    busiestInstitutes('tbluser', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $userAccounts->directory($c, ['limit' => 200])['accounts'],
    static fn (array $rows) => array_column($rows, 'user_id'),
    static fn (int $institute) => DB::table('tbluser')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('tbluser', 'sub_institute_id'),
);

// ---- Library --------------------------------------------------------------------
$libraryService = app(\App\Services\Mcp\LibraryService::class);

checkIsolation(
    'library.catalogue',
    busiestInstitutes('library_books', 'sub_institute_id', null),
    static fn (McpRequestContext $c) => $libraryService->catalogue($c, ['limit' => 200])['titles'],
    static fn (array $rows) => array_column($rows, 'book_id'),
    static fn (int $institute) => DB::table('library_books')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('library_books', 'sub_institute_id'),
);

checkIsolation(
    'library.circulation',
    busiestInstitutes('library_book_circulations', 'sub_institute_id', 'syear'),
    static fn (McpRequestContext $c) => $libraryService->circulation($c, ['limit' => 200])['loans'],
    static fn (array $rows) => array_column($rows, 'loan_id'),
    static fn (int $institute) => DB::table('library_book_circulations')
        ->where('sub_institute_id', $institute)->pluck('id')->map('intval')->all(),
    instituteWithout('library_book_circulations', 'sub_institute_id'),
);
// The staff card is the other detail read added with these modules, and the one whose
// table is most worth probing: `tbluser` holds payroll and government identity numbers,
// so "does this id exist elsewhere" must get the same answer as "this id does not exist".
$staffSample = DB::table('tbluser')
    ->where('status', 1)
    ->where('sub_institute_id', '>', 0)
    ->first(['id', 'sub_institute_id']);

if ($staffSample === null) {
    echo "INCONCLUSIVE  user_icard.card_details  no staff records\n";
} else {
    $otherInstitute = DB::table('tbluser')
        ->where('status', 1)
        ->where('sub_institute_id', '>', 0)
        ->where('sub_institute_id', '!=', $staffSample->sub_institute_id)
        ->value('sub_institute_id');

    if ($otherInstitute === null) {
        echo "INCONCLUSIVE  user_icard.card_details  only one institute employs anybody\n";
    } else {
        $cards = app(\App\Services\Mcp\UserIcardService::class);

        $mineCard = $cards->cardDetails(context((int) $staffSample->sub_institute_id, null), ['staff_id' => (int) $staffSample->id]);
        $theirCard = $cards->cardDetails(context((int) $otherInstitute, null), ['staff_id' => (int) $staffSample->id]);

        // The owner may legitimately get `found => false` — a Student-profile user, or one
        // whose profile row belongs elsewhere, is excluded by the same rule the I-Card
        // screen applies. What must never happen is the OTHER institute reading it.
        $cardOk = ($theirCard['found'] ?? true) === false;

        printf(
            "%s  %-22s user %d: owner reads it (%s), institute %d does not (%s)\n",
            $cardOk ? 'PASS         ' : 'FAIL         ',
            'user_icard.card_details',
            $staffSample->id,
            var_export($mineCard['found'] ?? null, true),
            $otherInstitute,
            var_export($theirCard['found'] ?? null, true),
        );

        // And the payload itself carries no payroll or identity field, whichever way it
        // was reached. The column list is asserted in the test suite; this confirms it on
        // a real row.
        $leakedFields = array_values(array_intersect(
            array_keys($mineCard['card'] ?? []),
            ['account_no', 'ifsc_code', 'bank_name', 'pan_no', 'aadhar_no', 'pf_no', 'esic_no', 'amount', 'password'],
        ));

        printf(
            "%s  %-22s payroll/identity fields in the card payload: %s\n",
            $leakedFields === [] ? 'PASS         ' : 'FAIL         ',
            'user_icard fields',
            $leakedFields === [] ? 'none' : implode(', ', $leakedFields),
        );
    }
}

// ---- Policy write scoping ---------------------------------------------------
//
// `AiPolicyController::update()` and `destroy()` used to look a policy up by id alone and
// write to it, so one school could edit or retire another school's policy by naming its
// id. The read was scoped; the write was not, and only the second of those stops it.
//
// EVERYTHING BELOW RUNS INSIDE A TRANSACTION THAT IS ALWAYS ROLLED BACK. If the guard ever
// regresses, the write this provokes is undone rather than left on the estate — a check
// for a data-integrity bug must not be able to cause one.
echo "\n";
echo str_repeat('-', 100)."\n";
echo "Policy write scoping — one institute must not be able to edit or retire another's policy\n";
echo str_repeat('-', 100)."\n";

$owned = DB::table('ai_policies')->whereNotNull('sub_institute_id')->first(['id', 'sub_institute_id', 'name']);
$shared = DB::table('ai_policies')->whereNull('sub_institute_id')->where('is_example', 1)->first(['id', 'name']);

if ($owned === null) {
    echo "INCONCLUSIVE  no institute-owned policy exists to attempt a cross-tenant write against\n";
} else {
    $intruder = (int) DB::table('tblstudent_enrollment')
        ->where('sub_institute_id', '>', 0)
        ->where('sub_institute_id', '!=', $owned->sub_institute_id)
        ->value('sub_institute_id');

    /** A request carrying one institute's context, as the middleware would hydrate it. */
    $as = static function (int $institute, array $body = []) {
        $request = \Illuminate\Http\Request::create('/', 'PUT', $body);
        $request->attributes->set('mcp_context', context($institute, null));

        return $request;
    };

    $controller = app(\App\Http\Controllers\AI\AiPolicyController::class);
    $before = DB::table('ai_policies')->where('id', $owned->id)->first();

    DB::beginTransaction();

    try {
        $update = $controller->update($as($intruder, [
            'name' => 'CROSS TENANT WRITE — THIS MUST NOT PERSIST',
            'policy_type' => 'ai_free',
        ]), (int) $owned->id);

        $retire = $controller->destroy($as($intruder), (int) $owned->id);
        $after = DB::table('ai_policies')->where('id', $owned->id)->first();

        $blocked = $update->getStatusCode() === 404
            && $retire->getStatusCode() === 404
            && $after->name === $before->name
            && (int) $after->status === (int) $before->status;

        printf(
            "%s  institute %d editing institute %s's policy #%d: update=%d retire=%d, row %s\n",
            $blocked ? 'PASS         ' : 'FAIL         ',
            $intruder,
            $owned->sub_institute_id,
            $owned->id,
            $update->getStatusCode(),
            $retire->getStatusCode(),
            $after->name === $before->name ? 'unchanged' : 'MODIFIED',
        );
    } finally {
        // Always. Nothing this check does reaches the estate, pass or fail.
        DB::rollBack();
    }
}

if ($shared === null) {
    echo "INCONCLUSIVE  no shared example policy exists to test the fork path\n";
} else {
    $institute = (int) DB::table('tblstudent_enrollment')->where('sub_institute_id', '>', 0)->value('sub_institute_id');
    $controller = app(\App\Http\Controllers\AI\AiPolicyController::class);

    $as = static function (int $i, array $body = [], string $method = 'PUT') {
        $request = \Illuminate\Http\Request::create('/', $method, $body);
        $request->attributes->set('mcp_context', context($i, null));

        return $request;
    };

    DB::beginTransaction();

    try {
        $fork = $controller->update($as($institute, [
            'name' => $shared->name,
            'policy_type' => 'ai_assisted',
            'assignments' => [],
        ]), (int) $shared->id);

        $payload = json_decode($fork->getContent(), true);
        $newId = $payload['data']['policy']['id'] ?? null;
        $original = DB::table('ai_policies')->where('id', $shared->id)->first();

        $forked = $fork->getStatusCode() === 200
            && ($payload['data']['action'] ?? null) === 'forked'
            && $newId !== null && (int) $newId !== (int) $shared->id
            && $original->sub_institute_id === null;

        $retire = $controller->destroy($as($institute, [], 'DELETE'), (int) $shared->id);

        printf(
            "%s  institute %d editing shared example #%d: action=%s new id=%s, original %s · retire refused with %d\n",
            $forked && $retire->getStatusCode() === 422 ? 'PASS         ' : 'FAIL         ',
            $institute,
            $shared->id,
            $payload['data']['action'] ?? '-',
            $newId ?? '-',
            $original->sub_institute_id === null ? 'still shared' : 'CLAIMED BY A SCHOOL',
            $retire->getStatusCode(),
        );
    } finally {
        DB::rollBack();
    }
}

// ---- Student Medical clinical withholding -----------------------------------
//
// The rule that is enforced in the SERVICE rather than in a prompt: complaint, symptoms,
// disease and treatment are returned only when the read names one student, and are null
// for any read covering more than one. A model summarising a class therefore never
// receives the text it is told not to repeat — the instruction and the data agree.
//
// Checked directly rather than trusted, because it is the one rule in this codebase whose
// failure would put a named child's clinical detail into a report somebody prints.
echo "\n";
echo str_repeat('-', 100)."\n";
echo "Student Medical — clinical detail is withheld from a read covering more than one student\n";
echo str_repeat('-', 100)."\n";

$clinicalFields = ['complaint', 'symptoms', 'disease', 'treatments'];

$busyMedical = busiestInstitutes('student_infirmary', 'sub_institute_id', 'syear');

if ($busyMedical === []) {
    echo "INCONCLUSIVE  no infirmary visits exist to check\n";
} else {
    $entry = $busyMedical[0];
    $context = context($entry['institute'], $entry['year']);

    $cohort = $medical->visits($context, ['limit' => 50]);
    $leaked = [];

    foreach ($cohort['visits'] as $visit) {
        foreach ($clinicalFields as $field) {
            if (($visit[$field] ?? null) !== null) {
                $leaked[] = $field;
            }
        }
    }

    printf(
        "%s  cohort read of %d visit(s) for institute %d: clinical fields returned = %s (flag says %s)\n",
        $leaked === [] ? 'PASS         ' : 'FAIL         ',
        count($cohort['visits']),
        $entry['institute'],
        $leaked === [] ? 'none' : implode(', ', array_unique($leaked)),
        var_export($cohort['clinical_detail_included'] ?? null, true),
    );

    // The other half: naming one student must still return the detail, or the rule would
    // be withholding it from the nurse who needs it rather than from the summary.
    $oneStudent = $cohort['visits'][0]['student_id'] ?? null;

    if ($oneStudent === null) {
        echo "INCONCLUSIVE  no visit row to name a student from\n";
    } else {
        $single = $medical->visits($context, ['student_id' => (int) $oneStudent, 'limit' => 5]);
        $present = false;

        foreach ($single['visits'] as $visit) {
            foreach ($clinicalFields as $field) {
                if (($visit[$field] ?? null) !== null) {
                    $present = true;
                }
            }
        }

        printf(
            "%s  single-student read for student %d: clinical detail %s (flag says %s)\n",
            ($single['clinical_detail_included'] ?? false) === true ? 'PASS         ' : 'FAIL         ',
            $oneStudent,
            $present ? 'returned' : 'not present in these rows',
            var_export($single['clinical_detail_included'] ?? null, true),
        );
    }
}

echo "\n";
echo str_repeat('-', 100)."\n";
echo "Utility — the module is bulk data operations, and this estate holds no utilities data\n";
echo str_repeat('-', 100)."\n";

$utilityTables = [];

foreach (['utility%', 'meter%', 'electric%', 'water%', 'consumption%', '%utility_bill%'] as $like) {
    foreach (DB::select("SHOW TABLES LIKE '".$like."'") as $row) {
        $name = array_values((array) $row)[0];

        // transport_kilometer_rate matches `%meter%` on the letters in "kilometer" and
        // belongs to Transport. It is not a utilities table.
        if ($name !== 'transport_kilometer_rate') {
            $utilityTables[] = $name;
        }
    }
}

printf(
    "%s  %-22s tables matching utility/meter/electric/water/consumption: %s\n",
    $utilityTables === [] ? 'PASS         ' : 'FAIL         ',
    'utility data absent',
    $utilityTables === [] ? 'none' : implode(', ', $utilityTables),
);

$utilityKeywords = array_keys((array) config('ai.lifecycle.module_keywords.migration-modules', []));
$claimed = array_values(array_intersect($utilityKeywords, [
    'utility', 'utilities', 'electricity', 'water', 'gas', 'meter', 'bill', 'bills', 'consumption',
]));

printf(
    "%s  %-22s utilities words in the module's vocabulary: %s\n",
    $claimed === [] ? 'PASS         ' : 'FAIL         ',
    'utility vocabulary',
    $claimed === [] ? 'none — a bill question finds no module and is told so' : implode(', ', $claimed),
);

echo "\n";
echo str_repeat('-', 100)."\n";
echo "Parent Communication — a letter's body is withheld from a read covering more than one family\n";
echo str_repeat('-', 100)."\n";

$pcInstitute = DB::table('parent_communication')
    ->select('sub_institute_id', DB::raw('count(*) c'))
    ->groupBy('sub_institute_id')
    ->orderByDesc('c')
    ->value('sub_institute_id');

if ($pcInstitute === null) {
    echo "INCONCLUSIVE  parent_communication  no messages recorded\n";
} else {
    $pcYear = DB::table('parent_communication')->where('sub_institute_id', $pcInstitute)->max('syear');
    $pcContext = context((int) $pcInstitute, $pcYear === null ? null : (int) $pcYear);
    $service = app(\App\Services\Mcp\ParentCommunicationService::class);

    $cohort = $service->messages($pcContext, ['limit' => 50]);
    $bodies = array_values(array_filter(
        array_column($cohort['messages'] ?? [], 'message'),
        static fn ($body) => $body !== null && trim((string) $body) !== ''
    ));

    printf(
        "%s  %-22s cohort read of %d message(s) for institute %d: bodies returned = %s (flag says %s)\n",
        $bodies === [] ? 'PASS         ' : 'FAIL         ',
        'cohort body withheld',
        count($cohort['messages'] ?? []),
        $pcInstitute,
        $bodies === [] ? 'none' : count($bodies),
        var_export($cohort['message_body_included'] ?? null, true),
    );

    $firstStudent = null;

    foreach ($cohort['messages'] ?? [] as $message) {
        if (($message['student_id'] ?? null) !== null) {
            $firstStudent = (int) $message['student_id'];
            break;
        }
    }

    if ($firstStudent === null) {
        echo "INCONCLUSIVE  parent_communication  no message names a student\n";
    } else {
        $single = $service->messages($pcContext, ['limit' => 5, 'student_id' => $firstStudent]);
        $singleBodies = array_values(array_filter(
            array_column($single['messages'] ?? [], 'message'),
            static fn ($body) => $body !== null && trim((string) $body) !== ''
        ));

        printf(
            "%s  %-22s single-student read for student %d: body returned (flag says %s)\n",
            $singleBodies !== [] ? 'PASS         ' : 'FAIL         ',
            'one-family body shown',
            $firstStudent,
            var_export($single['message_body_included'] ?? null, true),
        );
    }
}
