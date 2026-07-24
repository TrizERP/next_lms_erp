<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AcademicSetupApiController extends Controller
{
    use GetsJwtToken;

    private const MODULE_MENU_LINKS = [
        'standard-division-mapping' => ['std_div_map.index', 'school_setup/std_div_map'],
        'subject-standard-mapping' => ['sub_std_map.index', 'school_setup/sub_std_map'],
        'periods' => ['period_master.index', 'school_setup/period_master'],
        'batches' => ['batch_master.index', 'school_setup/batch_master'],
        'division-capacities' => ['division_capacity_master.index', 'school_setup/division_capacity_master'],
        'subjects' => ['subject_master.index', 'school_setup/subject_master'],
    ];

    private function failure(string $message, int $httpStatus = 422, $errors = null)
    {
        return response()->json([
            'status_code' => 0,
            'message' => $message,
            'errors' => $errors,
            'data' => [],
        ], $httpStatus);
    }

    private function context(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json(['status_code' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
            }
        } catch (\Exception $exception) {
            return response()->json(['status_code' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'user_id' => 'required|integer',
            'syear' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $token = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $parts = explode('.', $token);
        $payload = [];
        if (count($parts) === 3) {
            $decoded = base64_decode(strtr($parts[1], '-_', '+/'));
            $payload = json_decode($decoded ?: '{}', true) ?: [];
        }

        $actorId = (int) ($payload['id'] ?? 0);
        $tenantId = (int) ($payload['sub_institute_id'] ?? 0);
        if ($actorId !== $request->integer('user_id') || $tenantId !== $request->integer('sub_institute_id')) {
            return response()->json(['status_code' => 2, 'message' => 'Token context does not match the request.', 'data' => []], 403);
        }

        $actor = DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->select('u.id', 'u.user_profile_id', 'u.sub_institute_id', 'p.name as profile_name')
            ->where('u.id', $actorId)
            ->where('u.sub_institute_id', $tenantId)
            ->where('u.status', 1)
            ->first();
        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }

        $request->attributes->set('academic_setup_actor', $actor);

        return null;
    }

    private function authorizeModule(Request $request, string $module, string $action)
    {
        if (! isset(self::MODULE_MENU_LINKS[$module])) {
            return $this->failure('Unknown academic setup module.', 404);
        }

        $actor = $request->attributes->get('academic_setup_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return null;
        }

        $menuId = DB::table('tblmenumaster')
            ->where('status', 1)
            ->whereIn('link', self::MODULE_MENU_LINKS[$module])
            ->value('id');
        $individual = $menuId ? DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)
            ->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->first() : null;
        $rights = $individual ?: ($menuId ? DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)
            ->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)
            ->first() : null);
        $column = [
            'view' => 'can_view',
            'add' => 'can_add',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
        ][$action];

        if (! (bool) ($rights->{$column} ?? false)) {
            return $this->failure("You do not have permission to {$action} this academic setup module.", 403);
        }

        return null;
    }

    private function guard(Request $request, string $module, string $action)
    {
        if ($response = $this->context($request)) {
            return $response;
        }

        return $this->authorizeModule($request, $module, $action);
    }

    private function optionData(Request $request): array
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');

        return [
            'grades' => DB::table('academic_section')
                ->select('id', 'title', 'short_name')
                ->where('sub_institute_id', $tenantId)
                ->orderBy('sort_order')
                ->get(),
            'standards' => DB::table('standard')
                ->select('id', 'name', 'short_name', 'grade_id')
                ->where('sub_institute_id', $tenantId)
                ->orderBy('sort_order')
                ->get(),
            'divisions' => DB::table('division')
                ->select('division.id', 'division.name', 'std_div_map.standard_id')
                ->leftJoin('std_div_map', function ($join) use ($tenantId) {
                    $join->on('std_div_map.division_id', '=', 'division.id')
                        ->where('std_div_map.sub_institute_id', $tenantId);
                })
                ->where('division.sub_institute_id', $tenantId)
                ->orderBy('division.name')
                ->get(),
            'subjects' => DB::table('subject')
                ->select('id', 'subject_name', 'subject_code')
                ->where('sub_institute_id', $tenantId)
                ->orderBy('subject_name')
                ->get(),
            'academic_years' => DB::table('academic_year')
                ->select('id', 'title', 'syear')
                ->where('sub_institute_id', $tenantId)
                ->where('syear', $syear)
                ->orderBy('sort_order')
                ->get(),
            'categories' => DB::table('lms_content_category')
                ->select('id', 'category_name')
                ->where('status', 1)
                ->where(function ($query) use ($tenantId) {
                    $query->where('sub_institute_id', $tenantId)->orWhere('sub_institute_id', 0);
                })
                ->orderBy('category_name')
                ->get(),
        ];
    }

    public function index(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'view')) {
            return $response;
        }

        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $records = [];
        $mappings = [];

        switch ($module) {
            case 'standard-division-mapping':
                DB::table('std_div_map')
                    ->where('sub_institute_id', $tenantId)
                    ->orderBy('standard_id')
                    ->get()
                    ->each(function ($mapping) use (&$mappings) {
                        $mappings[(string) $mapping->standard_id][] = (int) $mapping->division_id;
                    });
                break;
            case 'subject-standard-mapping':
                $records = DB::table('sub_std_map as mapping')
                    ->join('standard', 'standard.id', '=', 'mapping.standard_id')
                    ->join('subject', 'subject.id', '=', 'mapping.subject_id')
                    ->select('mapping.*', 'standard.name as standard_name', 'subject.subject_name', 'subject.subject_code')
                    ->where('mapping.sub_institute_id', $tenantId)
                    ->orderBy('mapping.standard_id')
                    ->orderBy('mapping.sort_order')
                    ->get();
                break;
            case 'periods':
                $records = DB::table('period')
                    ->where('sub_institute_id', $tenantId)
                    ->orderBy('sort_order')
                    ->get();
                break;
            case 'batches':
                $records = DB::table('batch')
                    ->join('standard', 'standard.id', '=', 'batch.standard_id')
                    ->join('division', 'division.id', '=', 'batch.division_id')
                    ->select('batch.*', 'batch.title as titles', 'standard.name as standard_name', 'division.name as division_name')
                    ->where('batch.sub_institute_id', $tenantId)
                    ->where('batch.syear', $syear)
                    ->orderBy('batch.standard_id')
                    ->orderBy('batch.title')
                    ->get();
                break;
            case 'division-capacities':
                $records = DB::table('division_capacity_master as capacity')
                    ->join('academic_section', 'academic_section.id', '=', 'capacity.grade_id')
                    ->join('standard', 'standard.id', '=', 'capacity.standard_id')
                    ->join('division', 'division.id', '=', 'capacity.division_id')
                    ->select('capacity.*', 'academic_section.title as academic_section_name', 'standard.name as standard_name', 'division.name as division_name')
                    ->where('capacity.sub_institute_id', $tenantId)
                    ->where('capacity.syear', $syear)
                    ->get();
                break;
            case 'subjects':
                $records = DB::table('subject')
                    ->where('sub_institute_id', $tenantId)
                    ->orderBy('subject_name')
                    ->get();
                break;
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => array_merge([
                'records' => $records,
                'mappings' => $mappings,
            ], $this->optionData($request)),
        ]);
    }

    public function store(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'add')) {
            return $response;
        }

        switch ($module) {
            case 'standard-division-mapping':
                return $this->saveStandardDivisionMappings($request);
            case 'subject-standard-mapping':
                return $this->saveSubjectStandardMapping($request);
            case 'periods':
                return $this->savePeriod($request);
            case 'batches':
                return $this->saveBatch($request);
            case 'division-capacities':
                return $this->saveDivisionCapacity($request);
            case 'subjects':
                return $this->saveSubject($request);
        }

        return $this->failure('Unknown academic setup module.', 404);
    }

    public function update(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'edit')) {
            return $response;
        }

        switch ($module) {
            case 'subject-standard-mapping':
                return $this->saveSubjectStandardMapping($request, $id);
            case 'periods':
                return $this->savePeriod($request, $id);
            case 'batches':
                return $this->saveBatch($request, $id);
            case 'division-capacities':
                return $this->saveDivisionCapacity($request, $id);
            case 'subjects':
                return $this->saveSubject($request, $id);
        }

        return $this->failure('This module does not support record updates.', 405);
    }

    public function destroy(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'delete')) {
            return $response;
        }

        $tenantId = $request->integer('sub_institute_id');
        $table = [
            'subject-standard-mapping' => 'sub_std_map',
            'periods' => 'period',
            'batches' => 'batch',
            'division-capacities' => 'division_capacity_master',
            'subjects' => 'subject',
        ][$module] ?? null;
        if (! $table) {
            return $this->failure('This module does not support record deletion.', 405);
        }

        $record = DB::table($table)->where('id', $id)->where('sub_institute_id', $tenantId)->first();
        if (! $record) {
            return $this->failure('Record not found.', 404);
        }

        DB::transaction(function () use ($table, $id, $tenantId) {
            if ($table === 'period') {
                DB::table('period_details')->where('period_id', $id)->where('sub_institute_id', $tenantId)->delete();
            }
            DB::table($table)->where('id', $id)->where('sub_institute_id', $tenantId)->delete();
        });

        return response()->json(['status_code' => 1, 'message' => 'Record deleted successfully.', 'data' => []]);
    }

    private function saveStandardDivisionMappings(Request $request)
    {
        $validator = Validator::make($request->all(), ['division_id' => 'required|array']);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $tenantId = $request->integer('sub_institute_id');
        $standardIds = DB::table('standard')->where('sub_institute_id', $tenantId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $divisionIds = DB::table('division')->where('sub_institute_id', $tenantId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $insert = [];
        foreach ($request->input('division_id', []) as $standardId => $selectedDivisions) {
            if (! in_array((int) $standardId, $standardIds, true) || ! is_array($selectedDivisions)) {
                continue;
            }
            foreach (array_unique(array_map('intval', $selectedDivisions)) as $divisionId) {
                if (in_array($divisionId, $divisionIds, true)) {
                    $insert[] = [
                        'standard_id' => (int) $standardId,
                        'division_id' => $divisionId,
                        'sub_institute_id' => $tenantId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        DB::transaction(function () use ($tenantId, $insert) {
            DB::table('std_div_map')->where('sub_institute_id', $tenantId)->delete();
            if ($insert) {
                DB::table('std_div_map')->insert($insert);
            }
        });

        return response()->json(['status_code' => 1, 'message' => 'Division Mapped Successfully.', 'data' => []]);
    }

    private function saveSubjectStandardMapping(Request $request, ?int $id = null)
    {
        $tenantId = $request->integer('sub_institute_id');
        $standardInput = $request->input('standard_id');
        $standardIds = is_array($standardInput) ? $standardInput : [$standardInput];
        $request->merge(['standard_id_list' => $standardIds]);
        $validator = Validator::make($request->all(), [
            'standard_id_list' => 'required|array|min:1',
            'standard_id_list.*' => [
                'required',
                'integer',
                Rule::exists('standard', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId)),
            ],
            'subject_id' => [
                'required',
                'integer',
                Rule::exists('subject', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId)),
            ],
            'display_name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'load' => 'nullable|numeric|min:0',
            'subject_category' => 'nullable|string|max:255',
            'add_content' => 'nullable|in:chapterwise,topicwise',
            'optional_type' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $values = [
            'subject_id' => $request->integer('subject_id'),
            'display_name' => $request->input('display_name'),
            'allow_grades' => $request->boolean('allow_grades') ? 'Yes' : '',
            'elective_subject' => $request->boolean('elective_subject') ? 'Yes' : 'No',
            'allow_content' => $request->boolean('allow_content') ? 'Yes' : '',
            'subject_category' => $request->input('subject_category'),
            'sort_order' => $request->input('sort_order'),
            'load' => $request->input('load'),
            'optional_type' => $request->boolean('elective_subject') ? $request->input('optional_type') : null,
            'add_content' => $request->input('add_content', 'chapterwise'),
            'sub_institute_id' => $tenantId,
            'status' => 1,
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($request, $id, $tenantId, $standardIds, $values) {
            foreach ($standardIds as $standardId) {
                $record = array_merge($values, ['standard_id' => (int) $standardId]);
                if ($id) {
                    DB::table('sub_std_map')->where('id', $id)->where('sub_institute_id', $tenantId)->update($record);
                } else {
                    DB::table('sub_std_map')->updateOrInsert(
                        ['standard_id' => (int) $standardId, 'subject_id' => $request->integer('subject_id'), 'sub_institute_id' => $tenantId],
                        array_merge($record, ['created_at' => now()])
                    );
                }
            }
        });

        return response()->json([
            'status_code' => 1,
            'message' => $id ? 'Subject-Standard Mapping Updated Successfully' : 'Subject-Standard Mapping Added Successfully',
            'data' => [],
        ]);
    }

    private function savePeriod(Request $request, ?int $id = null)
    {
        $tenantId = $request->integer('sub_institute_id');
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'short_name' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:0',
            'academic_section_id' => 'nullable|integer',
            'academic_year_id' => 'nullable|integer',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'standards' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $duplicate = DB::table('period')
            ->where('sub_institute_id', $tenantId)
            ->whereRaw('UPPER(title) = ?', [strtoupper($request->input('title'))])
            ->when($id, fn ($query) => $query->where('id', '<>', $id))
            ->exists();
        if ($duplicate) {
            return $this->failure('Period Already Exist');
        }

        $values = [
            'title' => $request->input('title'),
            'short_name' => $request->input('short_name'),
            'sort_order' => $request->integer('sort_order'),
            'used_for_attendance' => $request->boolean('used_for_attendance') ? 'Yes' : '',
            'academic_section_id' => $request->input('academic_section_id') ?: null,
            'academic_year_id' => $request->input('academic_year_id') ?: 0,
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'length' => abs(strtotime($request->input('end_time')) - strtotime($request->input('start_time'))) / 60,
            'sub_institute_id' => $tenantId,
            'status' => 1,
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($request, $id, $tenantId, $values, &$savedId) {
            if ($id) {
                DB::table('period')->where('id', $id)->where('sub_institute_id', $tenantId)->update($values);
                $savedId = $id;
            } else {
                $savedId = DB::table('period')->insertGetId(array_merge($values, ['created_at' => now()]));
            }
            DB::table('period_details')->where('period_id', $savedId)->where('sub_institute_id', $tenantId)->delete();
            foreach (array_unique(array_map('intval', $request->input('standards', []))) as $standardId) {
                DB::table('period_details')->insert([
                    'period_id' => $savedId,
                    'standard_id' => $standardId,
                    'start_time' => $request->input('start_time'),
                    'end_time' => $request->input('end_time'),
                    'length' => $values['length'],
                    'sub_institute_id' => $tenantId,
                    'created_by' => $request->integer('user_id'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['status_code' => 1, 'message' => $id ? 'Period Updated Successfully' : 'Period Added Successfully', 'data' => []]);
    }

    private function saveBatch(Request $request, ?int $id = null)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $validator = Validator::make($request->all(), [
            'standard_id' => 'required|integer',
            'division_id' => 'required|integer',
            'title' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $mapped = DB::table('std_div_map')
            ->where('sub_institute_id', $tenantId)
            ->where('standard_id', $request->integer('standard_id'))
            ->where('division_id', $request->integer('division_id'))
            ->exists();
        if (! $mapped) {
            return $this->failure('The selected division is not mapped to this standard.');
        }
        $duplicate = DB::table('batch')
            ->where('sub_institute_id', $tenantId)
            ->where('syear', $syear)
            ->where('standard_id', $request->integer('standard_id'))
            ->where('division_id', $request->integer('division_id'))
            ->whereRaw('UPPER(title) = ?', [strtoupper($request->input('title'))])
            ->when($id, fn ($query) => $query->where('id', '<>', $id))
            ->exists();
        if ($duplicate) {
            return $this->failure('Batch Already Exist');
        }

        $values = [
            'title' => $request->input('title'),
            'standard_id' => $request->integer('standard_id'),
            'division_id' => $request->integer('division_id'),
            'sub_institute_id' => $tenantId,
            'syear' => $syear,
            'updated_at' => now(),
        ];
        if ($id) {
            DB::table('batch')->where('id', $id)->where('sub_institute_id', $tenantId)->where('syear', $syear)->update($values);
        } else {
            DB::table('batch')->insert(array_merge($values, ['created_at' => now()]));
        }

        return response()->json(['status_code' => 1, 'message' => $id ? 'Batch Updated Successfully' : 'Batch Added Successfully', 'data' => []]);
    }

    private function saveDivisionCapacity(Request $request, ?int $id = null)
    {
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|integer',
            'standard_id' => 'required|integer',
            'division_id' => 'required|integer',
            'capacity' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $values = [
            'grade_id' => $request->integer('grade_id'),
            'standard_id' => $request->integer('standard_id'),
            'division_id' => $request->integer('division_id'),
            'capacity' => $request->integer('capacity'),
            'sub_institute_id' => $tenantId,
            'syear' => $syear,
        ];
        if ($id) {
            DB::table('division_capacity_master')->where('id', $id)->where('sub_institute_id', $tenantId)->where('syear', $syear)->update(array_merge($values, [
                'updated_by' => $request->integer('user_id'),
                'updated_on' => now(),
            ]));
        } else {
            DB::table('division_capacity_master')->insert(array_merge($values, [
                'created_by' => $request->integer('user_id'),
                'created_on' => now(),
                'created_ip' => $request->ip(),
            ]));
        }

        return response()->json(['status_code' => 1, 'message' => $id ? 'Division Capacity Updated Successfully' : 'Division Capacity Added Successfully', 'data' => []]);
    }

    private function saveSubject(Request $request, ?int $id = null)
    {
        $tenantId = $request->integer('sub_institute_id');
        $validator = Validator::make($request->all(), [
            'subject_name' => 'required|string|max:255',
            'subject_code' => 'required|string|max:255',
            'short_name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $duplicate = DB::table('subject')
            ->where('sub_institute_id', $tenantId)
            ->whereRaw('UPPER(subject_name) = ?', [strtoupper($request->input('subject_name'))])
            ->when($id, fn ($query) => $query->where('id', '<>', $id))
            ->exists();
        if ($duplicate) {
            return $this->failure('Subject Already Exist');
        }

        $values = [
            'subject_name' => $request->input('subject_name'),
            'subject_code' => $request->input('subject_code'),
            'short_name' => $request->input('short_name'),
            'subject_type' => $request->boolean('subject_type') ? 'Major' : '',
            'sub_institute_id' => $tenantId,
            'marking_period_id' => $request->input('term_id') ?: null,
            'status' => 1,
            'updated_at' => now(),
        ];
        if ($id) {
            DB::table('subject')->where('id', $id)->where('sub_institute_id', $tenantId)->update($values);
        } else {
            DB::table('subject')->insert(array_merge($values, ['created_at' => now()]));
        }

        return response()->json(['status_code' => 1, 'message' => $id ? 'Subject Updated Successfully' : 'Subject Added Successfully', 'data' => []]);
    }
}
