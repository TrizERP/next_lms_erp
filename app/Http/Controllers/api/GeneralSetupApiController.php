<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GeneralSetupApiController extends Controller
{
    use GetsJwtToken;

    private const MENU_LINKS = [
        'templates' => ['templatemaster.index'],
        'forms' => ['formbuild.list'],
        'user-profiles' => ['add_user_profile.index'],
        'implementations' => ['add_implementation.index'],
        'bulk-upload' => ['bulk_chapter_upload.index'],
    ];

    private function failure(string $message, int $status = 422, $errors = null)
    {
        return response()->json(['status_code' => 0, 'message' => $message, 'errors' => $errors, 'data' => []], $status);
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

        $actor = DB::table('tbluser as user')
            ->join('tbluserprofilemaster as profile', 'profile.id', '=', 'user.user_profile_id')
            ->select('user.id', 'user.user_profile_id', 'user.sub_institute_id', 'profile.name as profile_name')
            ->where('user.id', $actorId)->where('user.sub_institute_id', $tenantId)->where('user.status', 1)->first();
        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }
        $request->attributes->set('general_actor', $actor);

        return null;
    }

    private function guard(Request $request, string $module, string $action)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        if (! isset(self::MENU_LINKS[$module])) {
            return $this->failure('Unknown General module.', 404);
        }
        $actor = $request->attributes->get('general_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return null;
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->whereIn('link', self::MENU_LINKS[$module])->value('id');
        $individual = $menuId ? DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)->where('sub_institute_id', $actor->sub_institute_id)->first() : null;
        $rights = $individual ?: ($menuId ? DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)->first() : null);
        $column = ['view' => 'can_view', 'add' => 'can_add', 'edit' => 'can_edit', 'delete' => 'can_delete'][$action];
        if (! (bool) ($rights->{$column} ?? false)) {
            return $this->failure("You do not have permission to {$action} this General module.", 403);
        }
        return null;
    }

    private function options(Request $request): array
    {
        $tenantId = $request->integer('sub_institute_id');
        return [
            'profiles' => DB::table('tbluserprofilemaster')->select('id', 'name')->where('sub_institute_id', $tenantId)->where('status', 1)->orderBy('sort_order')->get(),
            'grades' => DB::table('academic_section')->select('id', 'title as name')->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get(),
            'standards' => DB::table('standard')->select('id', 'name', 'grade_id')->where('sub_institute_id', $tenantId)->orderBy('sort_order')->get(),
            'subjects' => DB::table('sub_std_map as mapping')->join('subject', 'subject.id', '=', 'mapping.subject_id')
                ->select('subject.id', 'mapping.display_name as name', 'mapping.standard_id')
                ->where('mapping.sub_institute_id', $tenantId)->where('mapping.status', 1)->orderBy('mapping.display_name')->get(),
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
        switch ($module) {
            case 'templates':
                $records = DB::table('template_master as template')
                    ->leftJoin('tbluser as user', 'user.id', '=', 'template.created_by')
                    ->select('template.*', DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as created_by_name"))
                    ->where('template.sub_institute_id', $tenantId)->orderByDesc('template.id')->get();
                break;
            case 'forms':
                $records = DB::table('form_builder')->orderByDesc('id')->get();
                break;
            case 'user-profiles':
                $records = DB::table('tbluserprofilemaster as profile')
                    ->leftJoin('tbluserprofilemaster as parent', 'parent.id', '=', 'profile.parent_id')
                    ->select('profile.*', 'parent.name as parent_name')
                    ->where('profile.sub_institute_id', $tenantId)->where('profile.status', 1)->orderBy('profile.sort_order')->get();
                break;
            case 'implementations':
                $records = DB::table('implementation_master as implementation')
                    ->leftJoin('standard', 'standard.id', '=', 'implementation.standard_id')
                    ->select('implementation.*', 'standard.name as standard_name')
                    ->where('implementation.sub_institute_id', $tenantId)->where('implementation.syear', $syear)->orderBy('standard.sort_order')->get();
                break;
            case 'bulk-upload':
                $records = DB::table('chapter_master as chapter')
                    ->leftJoin('standard', 'standard.id', '=', 'chapter.standard_id')
                    ->leftJoin('subject', 'subject.id', '=', 'chapter.subject_id')
                    ->select('chapter.id', 'chapter.chapter_name', 'chapter.chapter_desc', 'chapter.availability', 'chapter.show_hide', 'standard.name as standard_name', 'subject.subject_name')
                    ->where('chapter.sub_institute_id', $tenantId)->where('chapter.syear', $syear)->orderByDesc('chapter.id')->limit(100)->get();
                break;
        }
        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => array_merge(['records' => $records], $this->options($request)),
        ]);
    }

    public function store(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'add')) {
            return $response;
        }
        return $this->save($request, $module);
    }

    public function update(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'edit')) {
            return $response;
        }
        return $this->save($request, $module, $id);
    }

    private function save(Request $request, string $module, ?int $id = null)
    {
        switch ($module) {
            case 'templates':
                return $this->saveTemplate($request, $id);
            case 'forms':
                return $this->saveForm($request, $id);
            case 'user-profiles':
                return $this->saveProfile($request, $id);
            case 'implementations':
                return $this->saveImplementation($request);
            case 'bulk-upload':
                return $this->saveBulkChapters($request);
        }
        return $this->failure('Unknown General module.', 404);
    }

    private function saveTemplate(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'module_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'html_content' => 'required|string',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $values = [
            'module_name' => $request->input('module_name'), 'title' => $request->input('title'),
            'html_content' => $request->input('html_content'), 'status' => 1,
            'created_by' => $request->integer('user_id'), 'sub_institute_id' => $request->integer('sub_institute_id'),
        ];
        if ($id) {
            $affected = DB::table('template_master')->where('id', $id)->where('sub_institute_id', $request->integer('sub_institute_id'))->update($values);
            if (! $affected && ! DB::table('template_master')->where('id', $id)->where('sub_institute_id', $request->integer('sub_institute_id'))->exists()) return $this->failure('Template not found.', 404);
        } else {
            $values['created_on'] = now();
            DB::table('template_master')->insert($values);
        }
        return response()->json(['status_code' => 1, 'message' => $id ? 'Template Updated Successfully' : 'Template Added Successfully', 'data' => []]);
    }

    private function saveForm(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'form_name' => 'required|string|max:255',
            'form_json' => 'required|json',
            'form_xml' => 'nullable|string',
            'form_active' => 'nullable|boolean',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $duplicate = DB::table('form_builder')->where('form_name', $request->input('form_name'))->when($id, fn ($query) => $query->where('id', '<>', $id))->exists();
        if ($duplicate) return $this->failure('Form name already exists.');
        $values = [
            'form_name' => $request->input('form_name'), 'form_xml' => $request->input('form_xml', ''),
            'form_json' => $request->input('form_json'), 'form_active' => $request->boolean('form_active') ? 1 : 0,
            'updated_at' => now(),
        ];
        if ($id) DB::table('form_builder')->where('id', $id)->update($values);
        else DB::table('form_builder')->insert(array_merge($values, ['created_at' => now()]));
        return response()->json(['status_code' => 1, 'message' => $id ? 'Form Updated Successfully' : 'Form Added Successfully', 'data' => []]);
    }

    private function saveProfile(Request $request, ?int $id)
    {
        $tenantId = $request->integer('sub_institute_id');
        $validator = Validator::make($request->all(), [
            'parent_id' => ['nullable', 'integer', Rule::exists('tbluserprofilemaster', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenantId))],
            'profile_name' => 'required|string|max:255',
            'profile_description' => 'nullable|string',
            'sort_order' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        if ($id && $request->integer('parent_id') === $id) return $this->failure('A profile cannot be its own parent.');
        $duplicate = DB::table('tbluserprofilemaster')->where('sub_institute_id', $tenantId)
            ->whereRaw('UPPER(name) = ?', [strtoupper($request->input('profile_name'))])
            ->when($id, fn ($query) => $query->where('id', '<>', $id))->exists();
        if ($duplicate) return $this->failure('User profile already exists.');
        $values = [
            'parent_id' => $request->input('parent_id') ?: 0, 'name' => $request->input('profile_name'),
            'description' => $request->input('profile_description'), 'sort_order' => $request->integer('sort_order'),
            'status' => 1, 'sub_institute_id' => $tenantId,
        ];
        if ($id) DB::table('tbluserprofilemaster')->where('id', $id)->where('sub_institute_id', $tenantId)->update($values);
        else DB::table('tbluserprofilemaster')->insert($values);
        return response()->json(['status_code' => 1, 'message' => $id ? 'User Profile updated successfully' : 'User Profile created successfully', 'data' => []]);
    }

    private function saveImplementation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'total_boys' => 'required|integer|min:0', 'total_girls' => 'required|integer|min:0',
            'total_strenght' => 'required|integer|min:0', 'total_male' => 'required|integer|min:0',
            'total_female' => 'required|integer|min:0', 'standard_totals' => 'required|json',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        if ($request->integer('total_strenght') !== $request->integer('total_boys') + $request->integer('total_girls')) {
            return $this->failure('Total strength must equal total boys plus total girls.');
        }
        $standardTotals = json_decode($request->input('standard_totals'), true);
        if (! is_array($standardTotals) || ! $standardTotals) return $this->failure('Standard-wise totals must contain at least one row.');
        $tenantId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $allowedStandards = DB::table('standard')->where('sub_institute_id', $tenantId)->pluck('id')->map(fn ($value) => (int) $value)->all();
        $rows = [];
        foreach ($standardTotals as $standardId => $totals) {
            if (! in_array((int) $standardId, $allowedStandards, true) || ! is_array($totals)) continue;
            $boys = max(0, (int) ($totals['boys'] ?? $totals['std_wise_total_boys'] ?? 0));
            $girls = max(0, (int) ($totals['girls'] ?? $totals['std_wise_total_girls'] ?? 0));
            $rows[] = [
                'sub_institute_id' => $tenantId, 'syear' => $syear, 'standard_id' => (int) $standardId,
                'std_wise_total_boys' => $boys, 'std_wise_total_girls' => $girls, 'std_wise_total' => $boys + $girls,
                'total_boys' => $request->integer('total_boys'), 'total_girls' => $request->integer('total_girls'),
                'total_strenght' => $request->integer('total_strenght'), 'total_male' => $request->integer('total_male'),
                'total_female' => $request->integer('total_female'),
                'final_std_total_boys' => $request->integer('final_std_total_boys'),
                'final_std_total_girls' => $request->integer('final_std_total_girls'),
                'final_std_total' => $request->integer('final_std_total'), 'created_on' => now(),
            ];
        }
        if (! $rows) return $this->failure('No valid institute standards were supplied.');
        DB::transaction(function () use ($tenantId, $rows) {
            DB::table('implementation_master')->where('sub_institute_id', $tenantId)->delete();
            DB::table('implementation_master')->insert($rows);
        });
        return response()->json(['status_code' => 1, 'message' => 'Implementation created successfully', 'data' => []]);
    }

    private function saveBulkChapters(Request $request)
    {
        $tenantId = $request->integer('sub_institute_id');
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|integer', 'standard_id' => 'required|integer', 'subject_id' => 'required|integer',
            'chapters' => 'required|json',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $mapped = DB::table('sub_std_map')->where('sub_institute_id', $tenantId)
            ->where('standard_id', $request->integer('standard_id'))->where('subject_id', $request->integer('subject_id'))->exists();
        if (! $mapped) return $this->failure('The selected subject is not mapped to this standard.');
        $chapters = json_decode($request->input('chapters'), true);
        if (! is_array($chapters) || ! $chapters) return $this->failure('At least one chapter is required.');
        $rows = [];
        foreach ($chapters as $chapter) {
            if (! is_array($chapter) || trim((string) ($chapter['chapter_name'] ?? '')) === '') continue;
            $rows[] = [
                'grade_id' => $request->integer('grade_id'), 'standard_id' => $request->integer('standard_id'),
                'subject_id' => $request->integer('subject_id'), 'chapter_name' => trim($chapter['chapter_name']),
                'chapter_desc' => $chapter['chapter_desc'] ?? '', 'availability' => $chapter['availability'] ?? '',
                'show_hide' => $chapter['show_hide'] ?? '', 'created_by' => $request->integer('user_id'),
                'sub_institute_id' => $tenantId, 'syear' => $request->integer('syear'), 'created_at' => now(),
            ];
        }
        if (! $rows) return $this->failure('No valid chapter rows were supplied.');
        DB::table('chapter_master')->insert($rows);
        return response()->json(['status_code' => 1, 'message' => 'Chapters Added Successfully', 'data' => []]);
    }

    public function destroy(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'delete')) return $response;
        $tenantId = $request->integer('sub_institute_id');
        $table = ['templates' => 'template_master', 'forms' => 'form_builder', 'user-profiles' => 'tbluserprofilemaster'][$module] ?? null;
        if (! $table) return $this->failure('This General module does not support record deletion.', 405);
        $query = DB::table($table)->where('id', $id);
        if ($module !== 'forms') $query->where('sub_institute_id', $tenantId);
        if ($module === 'user-profiles' && DB::table('tbluser')->where('sub_institute_id', $tenantId)->where('user_profile_id', $id)->exists()) {
            return $this->failure('This profile is assigned to users and cannot be deleted.');
        }
        if (! $query->delete()) return $this->failure('Record not found.', 404);
        return response()->json(['status_code' => 1, 'message' => 'Record deleted successfully.', 'data' => []]);
    }
}
