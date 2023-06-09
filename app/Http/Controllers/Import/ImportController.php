<?php

namespace App\Http\Controllers\Import;

use App\Models\CsvData;
use App\Http\Controllers\Controller;
use App\Http\Requests\CsvImportRequest;
use App\Models\student\tblstudentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function getImport()
    {
        $getTables = DB::table('import_table_fields')->groupBy('table_name')->orderBy('id')->get();
        return view('import.import', ['result' => $getTables]);
    }

    public function matchFields(Request $request)
    {
        if ($request->skip_val) {
            DB::table('csv_data')->where('id', $request->csv_file_id)->update(['is_skip' => $request->skip_val]);
        }
        if ($request->customize_is_checked) {
            DB::table('csv_data')->where('id', $request->csv_file_id)->update(['is_customize_checked' => $request->customize_is_checked]);
        }
        if ($request->csv_file_id) {
            $data = json_encode($request->completeArr);
            DB::table('csv_data')->where('id', $request->csv_file_id)->update(['match_fields' => $data]);
        }
    }

    public function parseImport(Request $request)
    {

        $fileUrl = $request->file('csv_file');


        $file = fopen($fileUrl, "r");
        $fileHeader = fgetcsv($file, 0, ',');

        $filePath = 'import';
        $generateFileName = rand('1111111111', '9999999999') . "." . $fileUrl->getClientOriginalExtension();
        $destinationFileUrl = $filePath . "/" . $generateFileName;
        $filePath = $filePath . "/";
        $fileUrl->move($filePath, $generateFileName);
        $csv_header_fields = [];
        foreach ($fileHeader as $header) {
            $csv_header_fields[] = Str::slug($header, ",");
        }
        $fileDetails = [];
        while (!feof($file)) {
            $fileDetail = [];
            if ($file != false) $fileDetails[] = fgetcsv($file, 0, ',');
        }
        array_pop($fileDetails);

        if (count($fileDetails) > 0) {
            $csv_data = $fileDetails[0];
            $csv_data_id = DB::table('csv_data')->insertGetId([
                'csv_filename' => $request->file('csv_file')->getClientOriginalName(),
                'csv_header' => $request->has('header'),
                'csv_data' => json_encode($fileDetails),
            ]);

            if ($request->tablename == 'tblstudent') {
                $table_fields = DB::table('import_table_fields')->select('display_field', 'field', 'is_required')->whereIn('table_name', [$request->tablename, 'tblstudent_enrollment'])->where('display_status', 1)->get();
            }else if ($request->tablename == 'fees_collect') {
                $table_fields = DB::table('import_table_fields')->select('display_field', 'field', 'is_required')->whereIn('table_name', [$request->tablename, 'fees_receipt'])->where('display_status', 1)->get();
            }
            else {
                $table_fields = DB::table('import_table_fields')->select('display_field', 'field', 'is_required')->where([['display_status', 1], ['table_name', $request->tablename]])->get();
            }
            $table_name = $request->tablename;
        } else {
            return redirect()->back();
        }
        return view('import.import_fields', compact('csv_header_fields', 'csv_data', 'table_fields', 'table_name', 'csv_data_id'));

    }

    public function processImport(Request $request)
    {
//         return $request->all();
        $data = DB::table('csv_data')->find($request->csv_data_file_id);
        $match_fields = json_decode($data->match_fields, true);
        $csv_data = json_decode($data->csv_data, true);
        $finalData = [];
        $totalRecordCount = count($csv_data);
        $totalFailedRecordCount = 0;
        $totalOverwiteRecordCount = 0;
        $totalInsertRecordCount = 0;
        foreach ($csv_data as $key => $row) {
            $finalData = $prepareData = [];
            foreach ($request->fields as $key => $field) {
               if($request->fields[$key] != 0) $prepareData[$request->fields[$key]] = $row[$key];
                $finalData[] = $prepareData;
            }
            if (is_array($match_fields) && count($match_fields) > 0 && $is_customize_checked = 1 && $row['is_skip'] = 2) {
                $condition = [];
                foreach ($match_fields as $field) {
                    if (!isset($prepareData[$field])) continue;
                    $condition[$field] = $prepareData[$field];
                }

                if ($request->table_name == 'tblstudent') {
                    if (!isset($prepareData['first_name']) || !isset($prepareData['last_name'])) {
                        $totalFailedRecordCount = $totalFailedRecordCount + 1;
                        continue;
                    }
                    if (isset($prepareData['user_profile_id'])) {
                        $user_profile_id = DB::table('tbluserprofilemaster')->select('id')->where([['name', $prepareData['user_profile_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($user_profile_id) $prepareData['user_profile_id'] = $user_profile_id->id;
                    }
                    $student_enroll_data = [];
                    if (isset($prepareData['grade_id'])) {
                        $grade_id = DB::table('academic_section')->select('id')->where([['title', $prepareData['grade_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($grade_id) $student_enroll_data['grade_id'] = $grade_id->id;
                    }
                    if (isset($prepareData['standard_id'])) {
                        $standard_id = DB::table('standard')->select('id')->where([['name', $prepareData['standard_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($standard_id) $student_enroll_data['standard_id'] = $standard_id->id;
                    }
                    if (isset($prepareData['section_id'])) {
                        $section_id = DB::table('division')->select('id')->where([['name', $prepareData['section_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($section_id) $student_enroll_data['section_id'] = $section_id->id;
                    }
                    if (isset($prepareData['student_quota'])) {
                        $student_quota = DB::table('student_quota')->select('id')->where([['title', $prepareData['student_quota']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($student_quota) $student_enroll_data['student_quota'] = $student_quota->id;
                    }
                    if (isset($prepareData['house_id'])) {
                        $house_id = DB::table('house_master')->select('id')->where([['house_name', $prepareData['house_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($house_id) $student_enroll_data['house_id'] = $house_id->id;
                    }
                    $student_enroll_data['syear'] = $prepareData['syear'] ?? null;
                    $student_enroll_data['start_date'] = $prepareData['start_date'] ?? null;
                    $student_enroll_data['adhar'] = $prepareData['adhar'] ?? null;

                    unset($prepareData['student_id'], $prepareData['grade_id'], $prepareData['standard_id'], $prepareData['section_id'], $prepareData['student_quota'], $prepareData['house_id'], $prepareData['syear'], $prepareData['start_date'], $prepareData['term_id'], $prepareData['adhar']);

                    unset($condition['student_id'], $condition['grade_id'], $condition['standard_id'], $condition['section_id'], $condition['student_quota'], $condition['house_id'], $condition['syear'], $condition['start_date'], $condition['term_id'], $condition['adhar']);

                    $student_id = DB::table($request->table_name)->where($condition)->where('sub_institute_id', '=', session()->get('sub_institute_id'))->first();
                    if (isset($student_id)) {
                        DB::table($request->table_name)->where($condition)->where('sub_institute_id', '=', session()->get('sub_institute_id'))->update($prepareData);
                        $student_enroll_data['student_id'] = $student_id->id;
                        DB::table('tblstudent_enrollment')->where('student_id', $student_id->id)->update($student_enroll_data);
                        $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                    } else {
                        $student_enroll_data['sub_institute_id'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                        $student_id = DB::table($request->table_name)->insertGetId($prepareData);
                        if ($student_id) $student_enroll_data['student_id'] = $student_id;
                        $student_enroll_data['adhar'] = $prepareData['adharnumber'] ?? null;
                        DB::table('tblstudent_enrollment')->insert($student_enroll_data);
                        $totalInsertRecordCount = $totalInsertRecordCount + 1;
                    }
                } elseif ($request->table_name == 'tbluser') {
                    if (isset($prepareData['user_profile_id'])) {
                        $user_profile_id = DB::table('tbluserprofilemaster')->select('id')->where([['name', $prepareData['user_profile_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($user_profile_id) $prepareData['user_profile_id'] = $user_profile_id->id;
                    }
                    DB::table($request->table_name)->where($condition)->where('sub_institute_id', '=', session()->get('sub_institute_id'))->update($prepareData);
                    $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                } else if ($request->table_name == 'fees_collect') {
                    if (!isset($prepareData['enrollment_no'])) {
                        $totalFailedRecordCount = $totalFailedRecordCount + 1;
                        continue;
                    }
                    $student_id = DB::table('tblstudent')->where([['enrollment_no',$prepareData['enrollment_no']],['sub_institute_id',session()->get('sub_institute_id')]])->first();
                    if($student_id) $standard_id = DB::table('tblstudent_enrollment')->select('standard_id')->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                    if ($standard_id){
                        $prepareData['standard_id'] = $standard_id->standard_id;
                        $prepareData['student_id'] = $student_id->id;
                    }
                    unset($prepareData['enrollment_no']);
                    $fees_receipt_data = [];
                    $fees_receipt_data['STANDARD'] = $prepareData['standard_id'] ?? null;
                    $fees_receipt_data['SYEAR'] = $prepareData['syear'] ?? null;


                    $fees_collect = DB::table($request->table_name)->where($condition)->where('sub_institute_id', '=', session()->get('sub_institute_id'))->first();
                    if($fees_collect) {
                        DB::table($request->table_name)->where($condition)->where('sub_institute_id', '=', session()->get('sub_institute_id'))->update($prepareData);
                        DB::table('fees_receipt')->where('FEES_ID', $fees_collect->id)->update($fees_receipt_data);
                        $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                    } else{
                        $fees_receipt_data['SUB_INSTITUTE_ID'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                        $fees_id = DB::table($request->table_name)->insertGetId($prepareData);
                        $fees_receipt_data['FEES_ID'] = $fees_id;
                        DB::table('fees_receipt')->insert($fees_receipt_data);
                        $totalInsertRecordCount = $totalInsertRecordCount + 1;
                    }
                }
            } else {
                if (isset($prepareData['user_profile_id'])) {
                    $user_profile_id = DB::table('tbluserprofilemaster')->select('id')->where([['name', $prepareData['user_profile_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                    if ($user_profile_id) $prepareData['user_profile_id'] = $user_profile_id->id;
                }
                if ($request->table_name == 'tbluser') {
                    $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    DB::table($request->table_name)->insert($prepareData);
                    $totalInsertRecordCount = $totalInsertRecordCount + 1;

                } else if ($request->table_name == 'tblstudent') {
                    if (!isset($prepareData['first_name']) || !isset($prepareData['last_name'])) {
                        $totalFailedRecordCount = $totalFailedRecordCount + 1;
                        continue;
                    }
                    $student_enroll_data = [];
                    if (isset($prepareData['grade_id'])) {
                        $grade_id = DB::table('academic_section')->select('id')->where([['title', $prepareData['grade_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($grade_id) $student_enroll_data['grade_id'] = $grade_id->id;
                    }
                    if (isset($prepareData['standard_id'])) {
                        $standard_id = DB::table('standard')->select('id')->where([['name', $prepareData['standard_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($standard_id) $student_enroll_data['standard_id'] = $standard_id->id;
                    }
                    if (isset($prepareData['section_id'])) {
                        $section_id = DB::table('division')->select('id')->where([['name', $prepareData['section_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($section_id) $student_enroll_data['section_id'] = $section_id->id;
                    }
                    if (isset($prepareData['student_quota'])) {
                        $student_quota = DB::table('student_quota')->select('id')->where([['title', $prepareData['student_quota']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($student_quota) $student_enroll_data['student_quota'] = $student_quota->id;
                    }
                    if (isset($prepareData['house_id'])) {
                        $house_id = DB::table('house_master')->select('id')->where([['house_name', $prepareData['house_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($house_id) $student_enroll_data['house_id'] = $house_id->id;
                    }
                    $student_enroll_data['syear'] = $prepareData['syear'] ?? null;
                    $student_enroll_data['start_date'] = $prepareData['start_date'] ?? null;
                    $student_enroll_data['adhar'] = $prepareData['adhar'] ?? null;

                    unset($prepareData['student_id'], $prepareData['grade_id'], $prepareData['standard_id'], $prepareData['section_id'], $prepareData['student_quota'], $prepareData['house_id'], $prepareData['syear'], $prepareData['start_date'], $prepareData['term_id'], $prepareData['adhar']);
                    $student_enroll_data['sub_institute_id'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    $student_id = DB::table($request->table_name)->insertGetId($prepareData);
                    if ($student_id) $student_enroll_data['student_id'] = $student_id;
                    DB::table('tblstudent_enrollment')->insert($student_enroll_data);
                    $totalInsertRecordCount = $totalInsertRecordCount + 1;
                } else if ($request->table_name == 'fees_collect') {
                    if (!isset($prepareData['enrollment_no'])) {
                        $totalFailedRecordCount = $totalFailedRecordCount + 1;
                        continue;
                    }
                    $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    $prepareData['created_by'] = session()->get('user_id');
                    $student_id = DB::table('tblstudent')->where([['enrollment_no',$prepareData['enrollment_no']],['sub_institute_id',session()->get('sub_institute_id')]])->first();
                   if($student_id) $standard_id = DB::table('tblstudent_enrollment')->select('standard_id')->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                    if ($standard_id){
                        $prepareData['standard_id'] = $standard_id->standard_id;
                        $prepareData['student_id'] = $student_id->id;
                    }
                    unset($prepareData['enrollment_no']);
                    $fees_receipt_data = [];
                    $fees_receipt_data['STANDARD'] = $prepareData['standard_id'] ?? null;
                    $fees_receipt_data['SYEAR'] = $prepareData['syear'] ?? null;
                    $fees_receipt_data['SUB_INSTITUTE_ID'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    $fees_id = DB::table($request->table_name)->insertGetId($prepareData);
                    $fees_receipt_data['FEES_ID'] = $fees_id;
                    DB::table('fees_receipt')->insert($fees_receipt_data);
                    $totalInsertRecordCount = $totalInsertRecordCount + 1;
                }
            }
        }

        return view('import.import_success', compact('totalRecordCount', 'totalFailedRecordCount', 'totalOverwiteRecordCount', 'totalInsertRecordCount'));
    }
}

