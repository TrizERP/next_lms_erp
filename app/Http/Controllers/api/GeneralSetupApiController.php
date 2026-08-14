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

    /**
     * Template placeholder tags (the `<<tag>>` values you embed inside a
     * template_master html_content that the ERP replaces at render time).
     *
     * Mirrors the data served by the legacy settings/view_all_tag web screen so
     * the Next.js editor can offer the same insertable-tag picker. The set is
     * static, so this is intentionally a read-only, permission-guarded endpoint
     * with no write side-effects.
     */
    public function tags(Request $request, string $module)
    {
        if ($response = $this->context($request)) {
            return $response;
        }

        if (! isset(self::MENU_LINKS[$module])) {
            return $this->failure('Unknown General module.', 404);
        }

        if ($module !== 'templates') {
            return $this->failure('Tags are only available for the templates module.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => ['tags' => $this->templateTags()],
        ]);
    }

    private function templateTags(): array
    {
        return [
            'receipt_logo' => 'Institute/School Logo from fees receipt book master',
            'receipt_line_1' => 'Institute/School Name from fees receipt book master',
            'receipt_line_2' => 'Institute/School Address Line 1 from fees receipt book master',
            'receipt_line_3' => 'Institute/School Address Line 2 from fees receipt book master',
            'receipt_line_4' => 'Institute/School Address Line 3 from fees receipt book master',
            'admission_number_value' => 'Student Admission Number from student profile',
            'previous_syear' => 'Previous academic year number (e.g., 2023)',
            'current_syear' => 'Current academic year number (e.g., 2024)',
            'next_syear' => 'Next academic year number (e.g., 2025)',
            'student_enrollment_value' => 'Student Enrollment Number / GR No.',
            'student_roll_no_value' => 'Student Roll No.',
            'student_name_value' => 'Student Name first and last name',
            'student_full_name' => 'Student Name first, middle and last name',
            'student_first_name_value' => 'Student Name first name',
            'student_middle_name_value' => 'Student Name middle name',
            'student_last_name_value' => 'Student Name last name',
            'student_image_value' => 'Student Image from student Profile',
            'student_division_value' => 'Student division',
            'student_year_value' => 'Student year like 2025-2026',
            'student_dob_value' => 'Student dob from student profile',
            'father_name_value' => 'Father name',
            'mother_name_value' => 'Mother name',
            'aadhar_number' => 'Student Aadhar Number from student profile',
            'student_gender' => 'Student Gender from student profile',
            'admission_date_value' => 'Admission Date',
            'short_standard_name_value' => 'Current Standard Name in Short Form',
            'student_standard_value' => 'Student Current Standard',
            'student_pen_no' => 'Student Pen No',
            'aapar_id' => 'Student Appar ID',
            'short_standard_name_in_word_value' => 'School stream value from standard',
            'next_std_name' => 'Next Standard as per selected year from standard',
            'next_std_stream' => 'Next Standard stream as per selected year from standard',
            'next_std_short_name' => 'Next Standard short name as per selected year from standard',
            'current_medium' => 'current medium as per selected year from standard',
            'next_medium' => 'Next medium as per selected year from standard',
            'student_mobile_value' => 'Student Mobile Number',
            'fees_head_content' => 'Fees Head-wise Content with amount and head type',
            'total_amount_in_words' => 'Total Amount in words',
            'payment_mode' => 'Payment Mode with cash or cheque details',
            'admin_user' => 'Current Logged User',
            'current_date' => 'Current date in 26-Apr-2025 format',
            'current_date_dmy' => 'Current date in 26-04-2025 format',
            'student_dob_word_value' => 'Student dob word from student profile',
            'student_dise_uid_value' => 'Student dise uid from student profile',
            'student_uniqueid_value' => 'Student unique ID',
            'certificate_no' => 'Certificate no',
            'affiliation_no_value' => 'Affiliation no',
            'school_code_value' => 'School code',
            'nationality_value' => 'Nationality',
            'place_of_birth_value' => 'Place of birth',
            'religion_name_value' => 'Religion name',
            'caste_name_value' => 'Caste name',
            'subcast_value' => 'Subcast',
            'candidate_belongs_to_value' => 'Candidate belongs to, from TC information student profile',
            'date_of_first_admission_value' => 'Date of first admission, from TC information student profile',
            'class_in_which_pupil_last_studied_value' => 'Class in which pupil last studied, from TC information student profile',
            'last_school_board_value' => 'Last school board, from TC information student profile',
            'whether_failed_value' => 'Whether failed, from TC information student profile',
            'subjects_studied_value' => 'Subjects studied, from TC information student profile',
            'whether_qualified_value' => 'Whether qualified, from TC information student profile',
            'if_to_which_class_value' => 'If to which class, from TC information student profile',
            'month_up_paid_school_dues_value' => 'Month up paid school dues, from TC information student profile',
            'admission_under_value' => 'Admission under, from TC information student profile',
            'total_working_days_value' => 'Total working days, from TC information student profile',
            'total_working_days_present_value' => 'Total working days present, from TC information student profile',
            'games_played_value' => 'Games played, from TC information student profile',
            'general_conduct_value' => 'General conduct, from TC information student profile',
            'date_of_application_for_certificate_value' => 'Date of application for certificate, from TC information student profile',
            'date_of_issue_of_certificate_value' => 'Date of issue of certificate, from TC information student profile',
            'reason_leaving_school_value' => 'Reason leaving school, from TC information student profile',
            'proof_for_dob_value' => 'Proof for dob, from TC information student profile',
            'whether_school_is_under_goverment_value' => 'Whether school is under government, from TC information student profile',
            'date_on_which_pupil_name_was_struck_value' => 'Date on which pupil name was struck, from TC information student profile',
            'any_fees_concession_value' => 'Any fees concession, from TC information student profile',
            'whether_ncc_cadet_value' => 'Whether ncc cadet, from TC information student profile',
            'any_other_remarks_value' => 'Any other remarks, from TC information student profile',
            'date_on_which_pupil_name_value' => 'Date of application for certificate, from TC information student profile',
            'date_of_issue_of_certificate_new_value' => 'Date of issue of certificate, from TC information student profile',
            'date_application_for_certificate_value' => 'Date of application of certificate, from TC information student profile',
            'HE_SHE' => 'he/she in Upper case',
            'he_she' => 'he/she in lower case',
            'his_her' => 'His/Her in lower case',
            'HIS_HER' => 'His/Her in Upper case',
            'mr_miss' => 'Mr./Miss.',
            'daughter_or_son' => 'daughter/son',
            'certificate_reason' => 'certificate reason',
            'fees_details' => 'Fees Details',
            'teacher_remark_value' => 'Teacher Remark from result_student_attendance_master',
            'month_name' => 'Fees Paid Month Name',
            'activity_tag_marks' => 'Student Activity Report Marks',
            'bank_name' => 'Bank Name in fees receipt',
            'cheque_no' => 'Cheque Number in fees receipt',
            'cheque_date' => 'Cheque Date in fees receipt',
            'bank_branch' => 'Bank Branch in fees receipt',
            'payment_mode_type' => 'Payment Mode in fees receipt',
            'parent_pan_card' => 'Parent Pan Card No in fees receipt',
            'subjects_studied_system' => 'Main and Optional subject as per student selected',
            'subjects_studied_2_line' => 'Main and Optional subject as per student selected break between 2 lines',
            'annual_value_result' => 'Remarks from remarks master',
            'total_working_days_system' => 'Count days from start date and end date academic year',
            'total_working_days_present_system' => 'Count days as per attendance taken',
            'student_dise_uid_plus' => 'udise + number of student',
            'admission_standard' => 'Student Admission standard from student profile',
        ];
    }
}
