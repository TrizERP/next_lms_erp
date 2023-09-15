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
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportController extends Controller
{
    public function getImport()
    {
        $getTables = DB::table('import_table_fields')->groupBy('table_name')->orderBy('id')->get();
//        return $getTables;
        return view('import.import', ['result' => $getTables]);
    }

    public function Import()
    {
        $getTables = ["result_personalize_marks -sheet 1","result_personalize_marks -sheet 2"];
        return view('import.custom-import', ['result' => $getTables]);
    }
    public function customParseImport(Request $request) {

        $request->validate([
            'csv_file' => 'required|file|mimes:csv',
            'tablename' => 'required',
        ]);

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $fileUrl = $request->file('csv_file');
        $file = fopen($fileUrl, "r");
        $fileHeader = fgetcsv($file, 0, ',');

        $filePath = 'import';
        $generateFileName = $sub_institute_id . "_" . $syear . "_" . rand('11111', '99999') . "." . $fileUrl->getClientOriginalExtension();
        $destinationFileUrl = $filePath . "/" . $generateFileName;
        $filePath = $filePath . "/";
        $fileUrl->move($filePath, $generateFileName);
        $csv_header_fields = [];

        $fileDetails = [];
        while (!feof($file)) {
            $fileDetail = [];
            if ($file != false) $fileDetails[] = fgetcsv($file, 0, ',');
        }
        array_pop($fileDetails);

        if(is_array($fileDetails)) {
            foreach ($fileDetails as $fileDetail) {
                if ($request->tablename == 'result_personalize_marks -sheet 1') {
                    $array1 = array_slice($fileDetail, 0, 5);
                    $array2 = array_slice($fileDetail, 5, count($fileDetail));
                    $exam_data = array_chunk($array2, 3);
                    foreach ($exam_data as $exam) {
                        DB::table('result_personalize_marks')->insert([
                            "syear" => $array1[0],
                            "sub_institute_id" => $sub_institute_id,
                            "enrollment_no" => $array1[1],
                            "student_name" => $array1[2],
                            "standard" => $array1[3],
                            "subject" => $array1[4],
                            "exam" => $exam[0],
                            "total" => $exam[1],
                            "obtain" => $exam[2],
                        ]);
                    }
                } else if ($request->tablename == 'result_personalize_marks -sheet 2') {
                    $array1 = array_slice($fileDetail, 0, 6);
                    if ($array1[2] == '' || $array1[3] == '') continue; // added by rajesh as per watsapp from darshan 13-Sep-2023
                    $subjectNameWithTotal = array_slice($fileHeader, 6, count($fileDetail));
                    $obtainData = array_slice($fileDetail, 6, count($fileDetail));
                    foreach ($subjectNameWithTotal as $key => $subject) {
                        $subjectName = preg_replace('/\s*\([^)]*\)/', '', $subject);
                        preg_match('/\(([^)]+)\)$/', $subject, $matches);
                        $total = isset($matches[1]) ? $matches[1] : '';
                        DB::table('result_personalize_marks')->insert([
                            "sub_institute_id" => $sub_institute_id,
                            "enrollment_no" => $array1[2],
                            "student_name" => $array1[3],
                            "standard" => $array1[4],
                            "syear" => $array1[5],
                            "subject" =>  strtok($subjectName, " "),
                            "exam" => $subjectName,
                            "total" => $total,
                            "obtain" => $obtainData[$key],
                        ]);
                    }
                }
            }

            return view('import.custom_import_success', ['result' => 'Data Saved Successfully']);
        }
    }

    public function matchFields(Request $request)
    {
        if ($request->skip_val) {
            DB::table('csv_data')->where('id', $request->csv_file_id)->update(['is_skip' => $request->skip_val]);
        }
        if ($request->customize_is_checked) {
            DB::table('csv_data')->where('id', $request->csv_file_id)->update(['is_customize_checked' => $request->customize_is_checked]);
        }
        if ($request->csv_file_id && count($request->completeArr)) {
            $data = json_encode($request->completeArr);
            DB::table('csv_data')->where('id', $request->csv_file_id)->update(['match_fields' => $data]);
        }
    }

    public function parseImport(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $fileUrl = $request->file('csv_file');
        $filePath = 'import';
        $generateFileName = $sub_institute_id . "_" . $syear . "_" . rand('11111', '99999') . "." . $fileUrl->getClientOriginalExtension();
        $destinationFileUrl = $filePath . "/" . $generateFileName;
        $filePath = $filePath . "/";
        $fileUrl->move($filePath, $generateFileName);
        $extension = $fileUrl->getClientOriginalExtension();

        if ($extension == "xlsx") {
            $spreadsheet = IOFactory::load($destinationFileUrl);
            $worksheet = $spreadsheet->getActiveSheet();
            $fileHeader = $worksheet->toArray()[0];
        } else {
            $file = fopen($destinationFileUrl, "r");
            $fileHeader = fgetcsv($file, 0, ',');
            fclose($file);
        }

        $csv_header_fields = [];
        foreach ($fileHeader as $header) {
            $csv_header_fields[] = Str::slug($header, ",");
        }

        $fileDetails = [];

        if ($extension != 'xlsx') {
            // $file = fopen($destinationFileUrl, "r");
            // while (!feof($file)) {
            //     $fileDetails[] = fgetcsv($file, 0, ',');
            // }
            // fclose($file);
            $file = fopen($destinationFileUrl, "r");
            $headerSkipped = false; // Keep track of whether the header has been skipped
            while (!feof($file)) {
                $rowData = fgetcsv($file, 0, ',');
                if (!$headerSkipped) {
                    $headerSkipped = true; // Skip the first row (header)
                    continue;
                }
                $fileDetails[] = $rowData;
            }
            fclose($file);
        } else {
            $fileDetails = $worksheet->toArray();
            array_shift($fileDetails);
        }

        if (!empty($fileDetails) && empty(end($fileDetails))) {
            array_pop($fileDetails);
        }

        // array_pop($fileDetails);

        if (count($fileDetails) > 0) {
            $csv_data = $fileDetails[0];
            $csv_data_id = DB::table('csv_data')->insertGetId([
                'csv_filename' => $request->file('csv_file')->getClientOriginalName(),
                'csv_header' => $request->has('header'),
                'csv_data' => json_encode($fileDetails),
            ]);

            if ($request->tablename == 'tblstudent') {
                $table_fields = DB::table('import_table_fields')->select('display_field', 'field', 'is_required')->whereIn('table_name', [$request->tablename, 'tblstudent_enrollment'])->where('display_status', 1)->get();
            } else if ($request->tablename == 'fees_collect') {
                $table_fields = DB::table('import_table_fields')->select('display_field', 'field', 'is_required')->whereIn('table_name', [$request->tablename, 'fees_receipt'])->where('display_status', 1)->get();
            } else if ($request->tablename == 'result_marks') {
                $table_fields = DB::table('import_table_fields')->select('display_field', 'field', 'is_required')->whereIn('table_name', [$request->tablename, 'tblstudent', 'result_create_exam'])->where('display_status', 1)->get();
            } else {
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
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $finalData = [];
        $totalFailedRecordCount = 0;
        $totalFailedRecordArray = [];
        $totalOverwiteRecordCount = 0;
        $totalOverwiteRecordArray = [];
        $totalSkipRecordCount = 0;
        $totalSkipRecordArray = [];
        $totalInsertRecordCount = 0;
        $totalRecordCount = 0;
        $data = DB::table('csv_data')->find($request->csv_data_file_id);
        $match_fields = json_decode($data->match_fields, true);
        $csv_data = json_decode($data->csv_data, true);
        $tableFields = $request->fields;
        $import_fields = DB::table('import_table_fields')->where([['table_name', $request->table_name], ['display_status', 1], ['is_required', 1]])->pluck('field');
        $import_fields = $import_fields->toArray();
        $failedFields = [];
        if (is_array($import_fields)) {
            foreach ($import_fields as $re_field) {
                if (!in_array($re_field, $tableFields)) {
                    $failedFields[] = $re_field;
                }
            }
            if (count($failedFields) > 0) return view('import.import_success', compact('totalRecordCount', 'totalFailedRecordCount', 'totalOverwiteRecordCount', 'totalInsertRecordCount', 'failedFields', 'totalSkipRecordCount', 'totalFailedRecordArray', 'totalOverwiteRecordArray', 'totalSkipRecordArray'));
        }
        if (is_array($csv_data)) {
            $totalRecordCount = count($csv_data);
            foreach ($csv_data as $rowKey => $row) {

                $finalData = $prepareData = [];
                if (is_array($request->fields)) {
                    foreach ($request->fields as $key => $field) {
                        if ($request->fields[$key] != 0) $prepareData[$request->fields[$key]] = $request->custom_text[$key] ?? $row[$key];
                        $finalData[] = $prepareData;
                    }
                }
                $condition = [];
                if (isset($match_fields) && isset($data->is_skip) && !empty($match_fields) && $data->is_skip !== null) {
                    if ($data->is_skip === 1) {
                        foreach ($match_fields as $field) {
                            if (!isset($prepareData[$field])) continue;
                            $condition[$field] = $prepareData[$field];
                        }
                    } else if ($data->is_skip === 2) {
                        foreach ($match_fields as $field) {
                            if (!isset($prepareData[$field])) continue;
                            $condition[$field] = $prepareData[$field];
                        }
                    }
                }
                $condition['sub_institute_id'] = session()->get('sub_institute_id');

                if (isset($prepareData['user_profile_id'])) {
                    $user_profile_id = DB::table('tbluserprofilemaster')->select('id')->where([['name', $prepareData['user_profile_id']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                    if ($user_profile_id) $prepareData['user_profile_id'] = $user_profile_id->id;
                }

                if ($request->table_name == 'tbluser') {
                    $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    $found = false;
                    $tbluser = DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->first();
                    if (isset($data->is_skip) && $data->is_skip !== null) {
                        if ($data->is_skip == 1) {
                            if ($tbluser) {
                                $found = true;
                                $totalSkipRecordCount = $totalSkipRecordCount + 1;
                                $totalSkipRecordArray[] = $rowKey + 1;
                            }
                        } else if ($data->is_skip == 2) {
                            $found = true;
                            $overwriteFound = DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->first();
                            if ($overwriteFound) {
                                DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->update($prepareData);
                                $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                                $totalOverwiteRecordArray[] = $rowKey + 1;
                            }
                        }
                    }
                    if (!$found) {
                        DB::table($request->table_name)->insert($prepareData);
                        $totalInsertRecordCount = $totalInsertRecordCount + 1;
                    }

                }

                if ($request->table_name == 'result_personalize_marks') {
                    $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    $found = false;
                    $tbluser = DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->first();
                    if (isset($daqta->is_skip) && $data->is_skip !== null) {
                        if ($data->is_skip == 1) {
                            if ($tbluser) {
                                $found = true;
                                $totalSkipRecordCount = $totalSkipRecordCount + 1;
                                $totalSkipRecordArray[] = $rowKey + 1;
                            }
                        } else if ($data->is_skip == 2) {
                            $found = true;
                            $overwriteFound = DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->first();
                            if ($overwriteFound) {
                                DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->update($prepareData);
                                $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                                $totalOverwiteRecordArray[] = $rowKey + 1;
                            }
                        }
                    }
                    if (!$found) {
                        DB::table($request->table_name)->insert($prepareData);
                        $totalInsertRecordCount = $totalInsertRecordCount + 1;
                    }

                }

                if ($request->table_name == 'fees_collect') {
                    $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    $prepareData['created_by'] = session()->get('user_id');
                    $student_id = DB::table('tblstudent')->where([['enrollment_no', $prepareData['enrollment_no']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                    if ($student_id) {
                        $standard_id = DB::table('tblstudent_enrollment')->select('standard_id')->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')], ['syear', session()->get('syear')]])->first();
                        if ($standard_id) {
                            $prepareData['standard_id'] = $standard_id;
                            $prepareData['standard_id'] = $standard_id->standard_id;
                            $prepareData['student_id'] = $student_id->id;
                        }
                        unset($prepareData['enrollment_no'],$condition['enrollment_no'],$condition['standard_id']);
                        $fees_receipt_data = [];
                        $fees_receipt_data['STANDARD'] = $prepareData['standard_id'] ?? null;
                        $fees_receipt_data['SYEAR'] = $prepareData['syear'] = session()->get('syear');
                        $fees_receipt_data['SUB_INSTITUTE_ID'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                        $found = false;
                        if (isset($data->is_skip) && $data->is_skip !== null) {
                            if ($data->is_skip == 1) {
                                $is_found = DB::table($request->table_name)->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')]])->where('syear', $syear)->where($condition)->first();
                                if ($is_found) {
                                    $found = true;
                                    $totalSkipRecordCount = $totalSkipRecordCount + 1;
                                    $totalSkipRecordArray[] = $rowKey + 1;
                                }
                            } else if ($data->is_skip == 2) {
                                $found = true;
                                $overwriteFound = DB::table($request->table_name)->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')]])->where('syear', $syear)->where($condition)->first();
                                if ($overwriteFound) {
                                    DB::table($request->table_name)->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')]])->where('syear', $syear)->where($condition)->update($prepareData);
                                    DB::table('fees_receipt')->where([['FEES_ID', $overwriteFound->id], ['sub_institute_id', session()->get('sub_institute_id')]])->update($fees_receipt_data);
                                    $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                                    $totalOverwiteRecordArray[] = $rowKey + 1;
                                }
                            }
                        }
                        if (!$found) {
                            $fees_id = DB::table($request->table_name)->insertGetId($prepareData);
                            $fees_receipt_data['FEES_ID'] = $fees_id;
                            DB::table('fees_receipt')->insert($fees_receipt_data);
                            $totalInsertRecordCount = $totalInsertRecordCount + 1;
                        }
                    } else {
                        $totalFailedRecordCount = $totalFailedRecordCount + 1;
                        $totalFailedRecordArray[] = $rowKey + 1;
                    }
                }

                if ($request->table_name == 'tblstudent') {
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
                    $student_enroll_data['syear'] = $prepareData['syear'] ?? session()->get('syear');
                    $student_enroll_data['start_date'] = $prepareData['start_date'] ?? date('Y-m-d');
                    $student_enroll_data['adhar'] = $prepareData['adhar'] ?? 0;

                    unset($prepareData['student_id'], $prepareData['grade_id'], $prepareData['standard_id'], $prepareData['section_id'], $prepareData['student_quota'], $prepareData['house_id'], $prepareData['syear'], $prepareData['start_date'], $prepareData['term_id'], $prepareData['adhar']);
                    $student_enroll_data['sub_institute_id'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');

                    $found = false;
                    $student_id = DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->first();
                    if($student_id){
                        $enroll_det = DB::table('tblstudent_enrollment')->where('student_id',$student_id->id)->where(['sub_institute_id'=> $sub_institute_id,'syear'=>session()->get('syear')])->first();
                    }
                    if (isset($data->is_skip) && $data->is_skip !== null) {
                        if ($data->is_skip == 1) {
                            if($student_id && !$enroll_det){
                                $found = true;
                                $student_enroll_data['student_id'] = $student_id->id;
                                DB::table('tblstudent_enrollment')->insert($student_enroll_data);
                                $totalInsertRecordCount = $totalInsertRecordCount + 1;
                            }
                                else if ($student_id) {
                                $found = true;
                                $totalSkipRecordCount = $totalSkipRecordCount + 1;
                                $totalSkipRecordArray[] = $rowKey + 1;
                            }
                        } else if ($data->is_skip == 2) {
                            if ($student_id) {
                                $found = true;
                                DB::table($request->table_name)->where($condition)->where('sub_institute_id', $sub_institute_id)->update($prepareData);
                                $student_enroll_data['student_id'] = $student_id->id;
                                DB::table('tblstudent_enrollment')->where('student_id', $student_id->id)->where('sub_institute_id', $sub_institute_id)->where('syear', $syear)->update($student_enroll_data);
                                $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                                $totalOverwiteRecordArray[] = $rowKey + 1;
                            }
                        }
                    }
                    if (!$found) {
                        $student_id = DB::table($request->table_name)->insertGetId($prepareData);
                        if ($student_id) $student_enroll_data['student_id'] = $student_id;
                        DB::table('tblstudent_enrollment')->insert($student_enroll_data);
                        $totalInsertRecordCount = $totalInsertRecordCount + 1;
                    }
                }

                if ($request->table_name == 'result_marks') {
                    $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                    $found = false;

                    $result_marks = DB::table("result_marks as rs")->join('result_create_exam as rce', 'rce.id', '=', 'rs.exam_id')->join('tblstudent as s', 's.id', '=', 'rs.student_id')->selectRaw('s.id as student_id,rce.id as exam_id,rs.points,rce.standard_id')->where(['rs.sub_institute_id' => $prepareData['sub_institute_id'], 'rce.title' => $prepareData['exam_id'], 'rce.standard_id' => $prepareData['standard_id'], 's.enrollment_no' => $prepareData['student_id']])->first();

                    if (!$result_marks) {
                        $new_record = DB::table('tblstudent as s')->join('result_create_exam as rce', 's.sub_institute_id', '=', 'rce.sub_institute_id')->selectRaw('s.id as student_id,rce.id as exam_id')->where(['s.sub_institute_id' => $prepareData['sub_institute_id'], 'rce.title' => $prepareData['exam_id'], 'rce.standard_id' => $prepareData['standard_id'], 's.enrollment_no' => $prepareData['student_id']])->groupBy('s.id')->first();

                        if ($new_record) {
                            DB::table($request->table_name)->insert([
                                "student_id" => $new_record->student_id,
                                "exam_id" => $new_record->exam_id,
                                "points" => $prepareData['points'] ?? 0,
                                "sub_institute_id" => $prepareData['sub_institute_id'],
                                "created_at" => now(),
                            ]);
                            $totalInsertRecordCount = $totalInsertRecordCount + 1;
                        }
                    } else {
                        $found = true;

                        if ($result_marks) {

                            DB::table($request->table_name)->where([
                                "student_id" => $result_marks->student_id,
                                "exam_id" => $result_marks->exam_id,
                                "sub_institute_id" => $prepareData['sub_institute_id'],
                            ])->update([
                                "student_id" => $result_marks->student_id,
                                "exam_id" => $result_marks->exam_id,
                                "points" => $prepareData['points'] ?? 0,
                                "sub_institute_id" => $prepareData['sub_institute_id'],
                                "updated_at" => now(),
                            ]);
                            $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                            $totalOverwiteRecordArray[] = $rowKey + 1;
                        }
                    }


                }

            }
        }
    //    exit;
        /*if (is_array($csv_data)) {
            $totalRecordCount = count($csv_data);
            foreach ($csv_data as $key => $row) {
                $finalData = $prepareData = [];
                foreach ($request->fields as $key => $field) {
                    if ($request->fields[$key] != 0) $prepareData[$request->fields[$key]] = $row[$key];
                    $finalData[] = $prepareData;
                }
                return $csv_data;
                if (is_array($match_fields) && count($match_fields) > 0 && $row['is_customize_checked'] == 1 && ($row['is_skip'] == 1 || $row['is_skip'] == 2)) {
                    $condition = [];
                    foreach ($match_fields as $field) {
                        if (!isset($prepareData[$field])) continue;
                        $condition[$field] = $prepareData[$field];
                    }
                    $condition['sub_institute_id'] = session()->get('sub_institute_id');

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
                        $student_enroll_data['syear'] = $prepareData['syear'] ?? null;
                        $student_enroll_data['start_date'] = $prepareData['start_date'] ?? null;
                        $student_enroll_data['adhar'] = $prepareData['adhar'] ?? null;

                        unset($prepareData['student_id'], $prepareData['grade_id'], $prepareData['standard_id'], $prepareData['section_id'], $prepareData['student_quota'], $prepareData['syear'], $prepareData['start_date'], $prepareData['term_id'], $prepareData['adhar']);

                        unset($condition['student_id'], $condition['grade_id'], $condition['standard_id'], $condition['section_id'], $condition['student_quota'], $condition['syear'], $condition['start_date'], $condition['term_id'], $condition['adhar']);

                        $student_id = DB::table($request->table_name)->where($condition)->first();
                        if ($student_id && $row['is_skip'] == 2) {
                            DB::table($request->table_name)->where($condition)->update($prepareData);
                            $student_enroll_data['student_id'] = $student_id->id;
                            DB::table('tblstudent_enrollment')->where('student_id', $student_id->id)->update($student_enroll_data);
                            $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                        } else if (!$student_id) {
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
                        $tbluser = DB::table($request->table_name)->where($condition)->first();
                        if ($tbluser && $row['is_skip'] == 2) {
                            DB::table($request->table_name)->where($condition)->update($prepareData);
                            $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                        } else if (!$tbluser) {
                            $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                            DB::table($request->table_name)->insert($prepareData);
                            $totalInsertRecordCount = $totalInsertRecordCount + 1;
                        }

                    } else if ($request->table_name == 'fees_collect') {
                        return $request->all();
                        if (!isset($prepareData['enrollment_no'])) {
                            $totalFailedRecordCount = $totalFailedRecordCount + 1;
                            continue;
                        }
                        $student_id = DB::table('tblstudent')->where([['enrollment_no', $prepareData['enrollment_no']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($student_id) {
                            $standard_id = DB::table('tblstudent_enrollment')->select('standard_id')->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                            if ($standard_id) {
                                $prepareData['standard_id'] = $standard_id->standard_id;
                                $prepareData['student_id'] = $student_id->id;
                            }
                            unset($prepareData['enrollment_no']);
                            $fees_receipt_data = [];
                            $fees_receipt_data['STANDARD'] = $prepareData['standard_id'] ?? null;
                            $fees_receipt_data['SYEAR'] = $prepareData['syear'] ?? null;


                            $fees_collect = DB::table($request->table_name)->where($condition)->first();
                            if ($fees_collect && $row['is_skip'] == 2) {
                                DB::table($request->table_name)->where($condition)->update($prepareData);
                                DB::table('fees_receipt')->where('FEES_ID', $fees_collect->id)->update($fees_receipt_data);
                                $totalOverwiteRecordCount = $totalOverwiteRecordCount + 1;
                            } else if (!$fees_collect) {
                                $fees_receipt_data['SUB_INSTITUTE_ID'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                                $fees_id = DB::table($request->table_name)->insertGetId($prepareData);
                                $fees_receipt_data['FEES_ID'] = $fees_id;
                                DB::table('fees_receipt')->insert($fees_receipt_data);
                                $totalInsertRecordCount = $totalInsertRecordCount + 1;
                            }
                        } else {
                            $totalFailedRecordCount = $totalFailedRecordCount + 1;
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
                        $student_enroll_data['syear'] = $prepareData['syear'] ?? null;
                        $student_enroll_data['start_date'] = $prepareData['start_date'] ?? null;
                        $student_enroll_data['adhar'] = $prepareData['adhar'] ?? null;

                        unset($prepareData['student_id'], $prepareData['grade_id'], $prepareData['standard_id'], $prepareData['section_id'], $prepareData['student_quota'], $prepareData['syear'], $prepareData['start_date'], $prepareData['term_id'], $prepareData['adhar']);
                        $student_enroll_data['sub_institute_id'] = $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                        $student_id = DB::table($request->table_name)->insertGetId($prepareData);
                        if ($student_id) $student_enroll_data['student_id'] = $student_id;
                        DB::table('tblstudent_enrollment')->insert($student_enroll_data);
                        $totalInsertRecordCount = $totalInsertRecordCount + 1;
                    } else if ($request->table_name == 'fees_collect') {
//                        return $request->all();
                        if (!isset($prepareData['enrollment_no'])) {

                            $totalFailedRecordCount = $totalFailedRecordCount + 1;
                            continue;
                        }
                        $prepareData['sub_institute_id'] = session()->get('sub_institute_id');
                        $prepareData['created_by'] = session()->get('user_id');
                        //return $prepareData['enrollment_no'];
                        $student_id = DB::table('tblstudent')->where([['enrollment_no', $prepareData['enrollment_no']], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                        if ($student_id) {
                            $standard_id = DB::table('tblstudent_enrollment')->select('standard_id')->where([['student_id', $student_id->id], ['sub_institute_id', session()->get('sub_institute_id')]])->first();
                            if ($standard_id) {
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
                        } else {
                            $totalFailedRecordCount = $totalFailedRecordCount + 1;
                        }
                    }
                }
            }
        }*/

        return view('import.import_success', compact('totalRecordCount', 'totalFailedRecordCount', 'totalOverwiteRecordCount', 'totalInsertRecordCount', 'failedFields', 'totalSkipRecordCount', 'totalFailedRecordArray', 'totalOverwiteRecordArray', 'totalSkipRecordArray'));
    }
}
