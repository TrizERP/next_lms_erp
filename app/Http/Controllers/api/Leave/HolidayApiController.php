<?php

namespace App\Http\Controllers\api\Leave;

use App\Http\Controllers\api\Leave\Concerns\ResolvesLeaveContext;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HolidayApiController extends Controller
{
    use ResolvesLeaveContext;

    private const WEEKDAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * GET /api/leave/holidays
     */
    public function index(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $calendarYear = $this->activeFilter($request->input('calendar_year'));
        $departmentId = $this->activeFilter($request->input('department_id'));

        $holidays = DB::table('hrms_holidays')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->when($calendarYear, fn ($q) => $q->whereYear('from_date', $calendarYear))
            ->when($departmentId, fn ($q) => $q->whereRaw('FIND_IN_SET(?, department)', [$departmentId]))
            ->orderBy('from_date')
            ->get();

        $departments = DB::table('hrms_departments')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->pluck('department', 'id');

        $data = $holidays->map(function ($holiday) use ($departments) {
            $ids = array_filter(explode(',', (string) $holiday->department));

            return [
                'id'              => (int) $holiday->id,
                'holiday_name'    => $holiday->holiday_name,
                'description'     => $holiday->description ?? null,
                'from_date'       => $holiday->from_date,
                'to_date'         => $holiday->to_date,
                'day'             => $holiday->from_date ? Carbon::parse($holiday->from_date)->format('l') : null,
                'applicable_year' => $holiday->from_date ? Carbon::parse($holiday->from_date)->format('Y') : null,
                'day_type'        => $holiday->day_type,
                'department_ids'  => array_values(array_map('intval', $ids)),
                'department_names'=> array_values(array_filter(array_map(fn ($id) => $departments[$id] ?? null, $ids))),
            ];
        });

        return response()->json([
            'status'  => 1,
            'message' => 'Holidays fetched successfully',
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/leave/holidays
     */
    public function store(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validated = $this->validatePayload($request);
        if (!is_array($validated)) {
            return $validated;
        }

        $duplicate = DB::table('hrms_holidays')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('from_date', $validated['from_date'])
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status'  => 0,
                'message' => 'A holiday already exists on this date',
                'errors'  => ['from_date' => ['A holiday already exists on this date']],
            ], 422);
        }

        $id = DB::table('hrms_holidays')->insertGetId(array_merge($validated, [
            'sub_institute_id' => $context['sub_institute_id'],
            'created_at'       => now(),
            'updated_at'       => now(),
            'created_by'       => $context['user_id'],
            'updated_by'       => $context['user_id'],
        ]));

        return response()->json([
            'status'  => 1,
            'message' => 'Holiday added successfully',
            'data'    => ['id' => (int) $id],
        ], 201);
    }

    /**
     * PUT /api/leave/holidays/{id}
     *
     * HolidayController::update() was an empty stub and its store() upserted on
     * (from_date, to_date, department), so renaming or moving a holiday was
     * impossible. This is a real edit by primary key.
     */
    public function update(Request $request, $id)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $holiday = DB::table('hrms_holidays')
            ->where('id', $id)
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->first();

        if (!$holiday) {
            return response()->json(['status' => 0, 'message' => 'Holiday not found'], 404);
        }

        $validated = $this->validatePayload($request);
        if (!is_array($validated)) {
            return $validated;
        }

        $duplicate = DB::table('hrms_holidays')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->where('from_date', $validated['from_date'])
            ->where('id', '!=', $id)
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status'  => 0,
                'message' => 'Another holiday already exists on this date',
                'errors'  => ['from_date' => ['Another holiday already exists on this date']],
            ], 422);
        }

        DB::table('hrms_holidays')->where('id', $id)->update(array_merge($validated, [
            'updated_at' => now(),
            'updated_by' => $context['user_id'],
        ]));

        return response()->json([
            'status'  => 1,
            'message' => 'Holiday updated successfully',
            'data'    => ['id' => (int) $id],
        ]);
    }

    /**
     * DELETE /api/leave/holidays/{id}   ({id} accepts a comma separated list)
     */
    public function destroy(Request $request, $id)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $id))));

        if (empty($ids)) {
            return response()->json(['status' => 0, 'message' => 'No holiday id supplied'], 422);
        }

        $deleted = DB::table('hrms_holidays')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'deleted_by' => $context['user_id'],
            ]);

        if (!$deleted) {
            return response()->json(['status' => 0, 'message' => 'Holiday not found'], 404);
        }

        return response()->json([
            'status'        => 1,
            'message'       => $deleted . ' holiday(s) deleted successfully',
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * GET /api/leave/weekdays
     *
     * The route GET holiday.weekdays -> HolidayController::getWeekdays has always
     * been registered against a method that does not exist.
     *
     * hrms_weekdays has no sub_institute_id/created_by/updated_by columns in
     * this app (see database/migrations/2023_05_20_002145_create_hrms_weekdays_table.php
     * - just id, day, day_type, deleted_at, timestamps): it's a single
     * institute-wide weekly-off pattern, not per-tenant. Filtering by
     * sub_institute_id here (as the original port did, assuming hp_erp's
     * shape) 500s with "Unknown column 'sub_institute_id'".
     */
    public function weekdays(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $saved = DB::table('hrms_weekdays')
            ->whereNull('deleted_at')
            ->pluck('day_type', 'day');

        $data = collect(self::WEEKDAYS)->map(fn ($day) => [
            'day'      => $day,
            'label'    => ucfirst($day),
            'day_type' => $saved[$day] ?? 'full',
        ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Weekdays fetched successfully',
            'data'    => $data,
        ]);
    }

    /**
     * POST /api/leave/weekdays
     */
    public function storeWeekdays(Request $request)
    {
        $context = $this->leaveContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $rules = [];
        foreach (self::WEEKDAYS as $day) {
            $rules[$day] = 'required|in:full,half,weekend';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::transaction(function () use ($request, $context) {
            foreach (self::WEEKDAYS as $day) {
                $existing = DB::table('hrms_weekdays')
                    ->where('day', $day)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    DB::table('hrms_weekdays')->where('id', $existing->id)->update([
                        'day_type'   => $request->input($day),
                        'updated_at' => now(),
                        'updated_by' => $context['user_id'],
                    ]);
                } else {
                    DB::table('hrms_weekdays')->insert([
                        'day'        => $day,
                        'day_type'   => $request->input($day),
                        'created_at' => now(),
                        'created_by' => $context['user_id'],
                    ]);
                }
            }
        });

        return response()->json(['status' => 1, 'message' => 'Weekly off pattern saved successfully']);
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\JsonResponse
     */
    private function validatePayload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'holiday_name'  => 'required|string|max:191',
            'description'   => 'nullable|string|max:500',
            'from_date'     => 'required|date',
            'to_date'       => 'nullable|date|after_or_equal:from_date',
            'day_type'      => 'nullable|in:full,half',
            'department'    => 'nullable|array',
            'department.*'  => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 0,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $fromDate = Carbon::parse($request->input('from_date'))->toDateString();
        $toDate   = $request->filled('to_date')
            ? Carbon::parse($request->input('to_date'))->toDateString()
            : $fromDate;

        $departments = array_values(array_filter((array) $request->input('department', [])));

        return [
            'holiday_name' => trim($request->input('holiday_name')),
            'description'  => $request->input('description'),
            'from_date'    => $fromDate,
            'to_date'      => $toDate,
            // Empty department list means the holiday applies institute-wide.
            'department'   => empty($departments) ? '' : implode(',', $departments),
            'day_type'     => $request->input('day_type', 'full'),
            'deleted_at'   => null,
        ];
    }
}
