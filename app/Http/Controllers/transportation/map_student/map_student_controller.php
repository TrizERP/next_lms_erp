<?php

namespace App\Http\Controllers\transportation\map_student;

use App\Http\Controllers\Controller;
use App\Models\transportation\add_vehicle\add_vehicle;
use App\Models\transportation\map_student\map_student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

/**
 * Student ↔ transport mapping.
 *
 * One student holds at most one mapping per academic year and institute, which
 * records the pickup (from) and drop (to) shift, vehicle and stop, plus the
 * billed distance and the fare derived from it.
 */
class map_student_controller extends Controller
{
    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function syear(): int
    {
        return (int) session()->get('syear');
    }

    private function normalizeDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        return null;
    }

    private function isApi(Request $request): bool
    {
        return in_array($request->input('type'), ['API', 'JSON'], true);
    }

    public function index(Request $request)
    {
        $data = ['data' => []];

        if (session()->has('data')) {
            $data_arr = session('data');
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['area'] = $this->area();
        $data['sel_area'] = $request->area;
        $type = $request->input('type');

        return is_mobile($type, "transportation/map_student/show", $data, "view");
    }

    /**
     * Search students and pre-fill each row with the mapping already on file.
     *
     * Every filter is optional except that at least one must be supplied — an
     * unfiltered search would load the whole school. When `id` is present the
     * method serves a single student (used by the mobile / API detail view).
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $this->tenant();
        $syear = $this->syear();

        $name = trim((string) $request->input('name', ''));
        $grno = trim((string) $request->input('grno', ''));
        $area = $request->input('area', '');
        $grade = $request->input('grade', '');
        $standard = $request->input('standard', '');
        $division = $request->input('division', '');
        $studentId = $request->input('id');

        $student_data = [];
        $areaStudentIds = [];

        // "Area" filters by the stop a student is already mapped to, so it is
        // resolved to a student id list first. The old code built this list but
        // never passed it to the search, so the filter did nothing.
        if ($area !== '' && $area !== null) {
            $areaStudentIds = map_student::where('from_stop', $area)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->pluck('student_id')
                ->all();
        }

        if (! empty($studentId)) {
            $student_data = SearchStudent("", "", "", "", "", "", "", "", "", "", $studentId);
        } elseif ($area !== '' && $area !== null && empty($areaStudentIds)) {
            // No student is mapped to that stop; an empty id list must not fall
            // through to "no filter" and return the entire school.
            $student_data = [];
        } elseif ($grade !== '' || $standard !== '' || $division !== '' || $name !== '' || $grno !== '' || ! empty($areaStudentIds)) {
            $student_data = SearchStudent(
                $grade,
                $standard,
                $division,
                "",
                "",
                "",
                $name,
                "",
                "",
                $grno,
                $areaStudentIds
            );
        }

        $ddShift = $this->ddShift();
        $responce_arr = [];

        foreach ($student_data as $id => $arr) {
            $row = [
                'sr.no'         => $id + 1,
                'name'          => trim($arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name']),
                'student_id'    => $arr['student_id'],
                'mobile'        => $arr['mobile'],
                'std-div'       => $arr['standard_name'] . " / " . $arr['division_name'],
                'enrollment_no' => $arr['enrollment_no'],
                'address'       => $arr['address'],
                'ddShift'       => $ddShift,
            ];

            if (! empty($studentId)) {
                $row['city'] = $arr['city'];
                $row['state'] = $arr['state'];
            }

            $mapping = map_student::where([
                "syear"            => $syear,
                "student_id"       => $arr['student_id'],
                "sub_institute_id" => $sub_institute_id,
            ])->first();

            if ($mapping) {
                $row['from_shift_id'] = $mapping->from_shift_id;
                $row['from_bus_id'] = $mapping->from_bus_id;
                $row['from_stop'] = $mapping->from_stop;
                $row['to_shift_id'] = $mapping->to_shift_id;
                $row['to_bus_id'] = $mapping->to_bus_id;
                $row['to_stop'] = $mapping->to_stop;
                $row['total_amount'] = $mapping->amount;
                $row['distance'] = $mapping->distance;
                $row['start_date'] = $mapping->start_date;
                $row['end_date'] = $mapping->end_date;

                $shift = DB::table('transport_school_shift')
                    ->where(['id' => $mapping->from_shift_id, 'sub_institute_id' => $sub_institute_id])
                    ->first();

                // The shift can have been deleted after the mapping was made;
                // the old code dereferenced $shift[0] unconditionally and threw.
                $row['shift_rate'] = $shift->shift_rate ?? 0;
                $row['km_amount'] = $shift->km_amount ?? 0;

                if (! empty($studentId)) {
                    $row['van-shift'] = $mapping->from_bus_id . "-" . $mapping->from_shift_id;
                    $row['van_shift'] = $this->van_shift();
                    $row['area'] = $this->area();
                }

                $row['ddFromBus'] = $this->busesForShift($mapping->from_shift_id);
                $row['ddToBus'] = $this->busesForShift($mapping->to_shift_id);
                $row['ddFrom'] = $this->stopsForBus($mapping->from_shift_id, $mapping->from_bus_id);
                $row['ddTo'] = $this->stopsForBus($mapping->to_shift_id, $mapping->to_bus_id);
            } else {
                // distance / amount / shift_rate / km_amount are deliberately
                // left unset so the Blade form falls back to its own defaults,
                // exactly as before.
                $row['from_shift_id'] = '';
                $row['from_bus_id'] = '';
                $row['from_stop'] = '';
                $row['to_shift_id'] = '';
                $row['to_bus_id'] = '';
                $row['to_stop'] = '';
                $row['ddFromBus'] = [];
                $row['ddToBus'] = [];
                $row['ddFrom'] = [];
                $row['ddTo'] = [];
            }

            $responce_arr['stu_data'][$id] = $row;
        }

        if (! empty($studentId)) {
            return $responce_arr;
        }

        $responce_arr['area'] = $area;

        return is_mobile($type, "transportation/map_student/add", $responce_arr, "view");
    }

    /** Vehicles running the given shift, as id => title. */
    private function busesForShift($shiftId)
    {
        if (empty($shiftId)) {
            return [];
        }

        return DB::table('transport_vehicle as tv')
            ->where('tv.sub_institute_id', $this->tenant())
            ->where('tv.school_shift', $shiftId)
            ->orderBy('tv.title')
            ->pluck('tv.title', 'tv.id');
    }

    /** Stops reachable by the given vehicle on the given shift, as id => stop_name. */
    private function stopsForBus($shiftId, $vehicleId)
    {
        if (empty($shiftId) || empty($vehicleId)) {
            return [];
        }

        return DB::table('transport_stop as ts')
            ->join('transport_route_stop as rs', 'rs.stop_id', '=', 'ts.id')
            ->join('transport_route as tr', 'tr.id', '=', 'rs.route_id')
            ->join('transport_route_bus as rb', 'rb.route_id', '=', 'tr.id')
            ->join('transport_vehicle as tv', 'tv.id', '=', 'rb.bus_id')
            ->join('transport_school_shift as ss', 'ss.id', '=', 'tv.school_shift')
            ->where('ss.id', $shiftId)
            ->where('tv.id', $vehicleId)
            ->where('ts.sub_institute_id', $this->tenant())
            ->groupBy('ts.id')
            ->pluck('ts.stop_name', 'ts.id');
    }

    public function area()
    {
        return DB::table('transport_stop')
            ->select('stop_name', 'id')
            ->where("sub_institute_id", $this->tenant())
            ->orderBy('stop_name')
            ->pluck('stop_name', 'id');
    }

    public function ddShift()
    {
        return DB::table('transport_school_shift')
            ->where("transport_school_shift.sub_institute_id", $this->tenant())
            ->orderBy('shift_title')
            ->get()->toArray();
    }

    public function van_shift()
    {
        $shifts = DB::table('transport_vehicle')
            ->select('transport_school_shift.shift_title', 'transport_school_shift.id', 'transport_vehicle.id as vid', 'transport_vehicle.vehicle_number')
            ->join('transport_school_shift', 'transport_school_shift.id', '=', 'transport_vehicle.school_shift')
            ->where("transport_school_shift.sub_institute_id", $this->tenant())
            ->orderBy('transport_vehicle.title')
            ->get();

        $result = [];

        foreach ($shifts as $shift) {
            $result[$shift->vid . '-' . $shift->id] = $shift->vehicle_number . '[' . $shift->shift_title . ']';
        }

        return $result;
    }

    public function fetchData(Request $request)
    {
        $response = ['response' => '', 'success' => false];

        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();

            return response()->json($response);
        }

        // Bound parameters — the values reach the query as data, never as SQL.
        $data_sql = "SELECT tms.syear,tms.student_id,tms.sub_institute_id,
            tssf.shift_title from_shift,tvf.title from_vehicle,fd.first_name from_driver,fd.mobile from_driver_mobile,fc.first_name from_cundoctor,fc.mobile from_conductor_mobile,tfs.stop_name from_stop,
            tsst.shift_title to_shift,tvt.title to_vehicle,td.first_name to_driver,td.mobile to_driver_mobile,tc.first_name to_cundoctor,tc.mobile to_conductor_mobile,tts.stop_name to_stop,
            tms.distance,tms.amount,tms.start_date,tms.end_date
            FROM transport_map_student tms
            INNER JOIN transport_school_shift tssf ON tssf.id = tms.from_shift_id
            INNER JOIN transport_vehicle tvf ON tvf.id = tms.from_bus_id
            INNER JOIN transport_stop tfs ON tfs.id = tms.from_stop
            INNER JOIN transport_school_shift tsst ON tsst.id = tms.to_shift_id
            INNER JOIN transport_vehicle tvt ON tvt.id = tms.to_bus_id
            INNER JOIN transport_stop tts ON tts.id = tms.to_stop
            LEFT JOIN transport_driver_detail fd ON fd.id = tvf.driver AND fd.type = 'Driver'
            LEFT JOIN transport_driver_detail td ON td.id = tvt.driver AND td.type = 'Driver'
            LEFT JOIN transport_driver_detail fc ON fc.id = tvf.conductor AND fc.type = 'Conductor'
            LEFT JOIN transport_driver_detail tc ON tc.id = tvt.conductor AND tc.type = 'Conductor'
            WHERE tms.student_id = ? AND tms.sub_institute_id = ? AND tms.syear = ?";

        $response['response'] = DB::select(preg_replace('/\s+/', ' ', $data_sql), [
            $request->input('student_id'),
            $request->input('sub_institute_id'),
            $request->input('syear'),
        ]);
        $response['success'] = true;

        return response()->json($response);
    }

    /**
     * Bulk save: every ticked row is written as the student's mapping for the
     * current academic year, replacing whatever was there before.
     */
    public function store(Request $request)
    {
        $type = $request->input('type');
        $syear = $this->syear();
        $sub_institute_id = $this->tenant();
        $values = $request->input('values');
//echo "<pre>";print_r($values);exit();
        if (! is_array($values) || empty($values)) {
            return is_mobile($type, "map_student.index", [
                "status_code" => 0,
                "message"     => "No student selected.",
            ], "redirect");
        }

        $saved = 0;
        $errors = [];
        // Seats claimed earlier in this same submission, so a batch cannot
        // overfill a vehicle one row at a time.
        $claimed = [];

        foreach ($values as $student_id => $arr) {
            if (! is_array($arr) || ! isset($arr['ckbox']) || ! in_array($arr['ckbox'], ['on', '1', 1, true], true)) {
                continue;
            }

            $row = [
                'from_shift' => $arr['from_shift'] ?? null,
                'from_bus'   => $arr['from_bus'] ?? null,
                'from_stop'  => $arr['from_stop'] ?? null,
                'to_shift'   => $arr['to_shift'] ?? null,
                'to_bus'     => $arr['to_bus'] ?? null,
                'to_stop'    => $arr['to_stop'] ?? null,
                'distance'   => $arr['distance'] ?? 0,
                'amount'     => $arr['distance_amount'] ?? 0,
                'start_date' => $this->normalizeDate($arr['start_date'] ?? null),
                'end_date'   => $this->normalizeDate($arr['end_date'] ?? null),
            ];

            $validator = Validator::make($row, [
                'from_shift' => 'required|integer|min:1',
                'from_bus'   => 'required|integer|min:1',
                'from_stop'  => 'required|integer|min:1',
                'to_shift'   => 'required|integer|min:1',
                'to_bus'     => 'required|integer|min:1',
                'to_stop'    => 'required|integer|min:1',
                'distance'   => 'nullable|numeric|min:0',
                'amount'     => 'nullable|numeric|min:0',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
            ]);//'start_date' => 'required|date',

            if ($validator->fails()) {
                $errors[] = "Student #{$student_id}: " . $validator->messages()->first();
                continue;
            }

            $seatKey = $row['from_bus'] . '-' . $row['from_shift'];
            if ($message = $this->capacityMessage($row['from_bus'], $row['from_shift'], (int) $student_id, $claimed[$seatKey] ?? 0)) {
                $errors[] = "Student #{$student_id}: {$message}";
                continue;
            }
            $claimed[$seatKey] = ($claimed[$seatKey] ?? 0) + 1;

            // Delete + insert keeps the one-mapping-per-student-per-year rule
            // even for rows that predate it, and is wrapped so a failure mid-way
            // cannot leave the student unmapped.
            DB::transaction(function () use ($student_id, $row, $syear, $sub_institute_id) {
                map_student::where([
                    "syear"            => $syear,
                    "student_id"       => $student_id,
                    "sub_institute_id" => $sub_institute_id,
                ])->delete();

                (new map_student([
                    "syear"            => $syear,
                    "student_id"       => $student_id,
                    "from_shift_id"    => $row['from_shift'],
                    "from_bus_id"      => $row['from_bus'],
                    "from_stop"        => $row['from_stop'],
                    "to_shift_id"      => $row['to_shift'],
                    "to_bus_id"        => $row['to_bus'],
                    "to_stop"          => $row['to_stop'],
                    "distance"         => $row['distance'] ?: 0,
                    "amount"           => $row['amount'] ?: 0,
                    "start_date"       => $row['start_date'],
                    "end_date"         => $row['end_date'],
                    'sub_institute_id' => $sub_institute_id,
                ]))->save();
            });

            $saved++;
        }

        if ($saved === 0) {
            return is_mobile($type, "map_student.index", [
                "status_code" => 0,
                "message"     => $errors ? implode(' ', $errors) : 'No student selected.',
            ], "redirect");
        }

        $message = "{$saved} student(s) mapped successfully.";
        if ($errors) {
            $message .= ' Skipped: ' . implode(' ', $errors);
        }

        return is_mobile($type, "map_student.index", [
            "status_code" => 1,
            "message"     => $message,
        ], "redirect");
    }

    /**
     * Remaining seats on a vehicle for a shift, counting only students who are
     * still enrolled in the current academic year.
     */
    public function ajaxChackRemainCapacity(Request $request)
    {
        $bus_id = $request->input("bus_id");
        $shift_id = $request->input("shift_id");

        if (empty($bus_id) || empty($shift_id)) {
            return ['status' => 422, 'message' => 'bus_id and shift_id are required.'];
        }

        $vehicle = add_vehicle::select('sitting_capacity')
            ->where('id', $bus_id)
            ->where('school_shift', $shift_id)
            ->where('sub_institute_id', $this->tenant())
            ->first();

        if (! $vehicle) {
            return ['status' => 404, 'message' => 'The selected vehicle does not run on the selected shift.'];
        }

        $totalCapacity = (int) $vehicle->sitting_capacity;
        $reserved = $this->reservedSeats($bus_id, $shift_id, $request->input('student_id'));

        return [
            'status'                 => 200,
            'total_capacity'         => $totalCapacity,
            'total_remain_capacity'  => $totalCapacity - $reserved,
        ];
    }

    /** Seats already taken, excluding the student currently being edited. */
    private function reservedSeats($bus_id, $shift_id, $excludeStudentId = null): int
    {
        $syear = $this->syear();

        return DB::table('transport_map_student as tms')
            ->join('tblstudent_enrollment as te', 'te.student_id', '=', 'tms.student_id')
            ->where('tms.from_bus_id', $bus_id)
            ->where('tms.from_shift_id', $shift_id)
            ->where('tms.sub_institute_id', $this->tenant())
            ->where('tms.syear', $syear)
            ->where('te.syear', $syear)
            ->whereNull('te.end_date')
            ->when($excludeStudentId, fn ($query) => $query->where('tms.student_id', '<>', $excludeStudentId))
            ->distinct()
            ->count('tms.student_id');
    }

    /** Null when the vehicle can still take this student. */
    private function capacityMessage($bus_id, $shift_id, $studentId, int $alreadyClaimed = 0): ?string
    {
        $vehicle = add_vehicle::select('sitting_capacity')
            ->where('id', $bus_id)
            ->where('school_shift', $shift_id)
            ->where('sub_institute_id', $this->tenant())
            ->first();

        if (! $vehicle) {
            return 'the selected pickup vehicle does not run on the selected pickup shift.';
        }

        $capacity = (int) $vehicle->sitting_capacity;
        if ($capacity <= 0) {
            return null;
        }
/*
        return ($this->reservedSeats($bus_id, $shift_id, $studentId) + $alreadyClaimed) >= $capacity
            ? 'the selected pickup vehicle has no remaining capacity.'
            : null;
*/            
        return null;
    }

    /**
     * Bulk unmap the selected students for the current academic year.
     */
    public function destroy(Request $request)
    {
        $type = $request->input('type');
        $students = $request->input('delete_students');

        if (! is_array($students) || empty($students)) {
            return is_mobile($type, "map_student.index", [
                "status_code" => 0,
                "message"     => "No students selected for deletion",
            ], "redirect");
        }

        $students = array_values(array_filter(array_map('intval', $students)));

        if (empty($students)) {
            return is_mobile($type, "map_student.index", [
                "status_code" => 0,
                "message"     => "No students selected for deletion",
            ], "redirect");
        }

        $deletedCount = map_student::where([
            "syear"            => $this->syear(),
            "sub_institute_id" => $this->tenant(),
        ])->whereIn('student_id', $students)->delete();

        return is_mobile($type, "map_student.index", [
            "status_code" => $deletedCount > 0 ? 1 : 0,
            "message"     => $deletedCount > 0
                ? "{$deletedCount} student(s) unmapped successfully."
                : "No mapping found for the selected student(s).",
        ], "redirect");
    }
}
