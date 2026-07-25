<?php

namespace App\Http\Controllers\api\result;

use App\Http\Controllers\AJAXController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REST API for the Result-module dropdown/lookup endpoints (cascading selects).
 *
 * Wherever the existing AJAXController method already returns clean JSON
 * (pluck maps / arrays) we delegate to it 1:1 and normalize the pluck map to
 * [{id, name}]. The referer-dependent methods (getStandardList,
 * getDivisionList, getSubjectList) and the two methods whose legacy SQL is
 * syntactically broken (getExamsList, getExamByCreateExam) are replicated
 * minimally here: module detection via HTTP_REFERER is replaced by an explicit
 * `module_name` request param, and teacher scoping (classTeacher*Arr from the
 * session hydrator + subject-teacher standards/divisions computed the same way
 * as the web SearchChain helper in app/Helpers/Helper.php) is honoured.
 * See scratchpad docs/dropdowns.md for the documented simplifications.
 */
class DropdownApiController extends BaseResultApiController
{
    /**
     * Standalone modules for which class-teacher scoping is bypassed in favour
     * of subject-teacher scoping (same list as AJAXController / SearchChain).
     *
     * @var array<int, string>
     */
    private const MODULE_ARRAY = [
        'student_homework',
        'marks_entry',
        'dicipline',
        'lmsExamwise_progress_report',
        'questionReport',
        'parent_communication',
        'question_paper',
        'co_scholastic_marks_entry',
        'student_homework_submission',
    ];

    /**
     * GET /api/result/dropdowns/standards
     * Standards for one or more grades (grade_id, csv allowed), honouring
     * class-teacher / subject-teacher / student scoping.
     * Replaces web referer detection with optional `module_name` param.
     */
    public function standards(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['grade_id' => 'required']);

            $gradeIds = $this->csv($request->input('grade_id'));
            $moduleName = (string) $request->input('module_name', '');
            $moduleBypass = in_array($moduleName, self::MODULE_ARRAY, true);

            $query = DB::table('standard')->whereIn('grade_id', $gradeIds);

            $classStdArr = session('classTeacherStdArr');
            $scope = $this->subjectTeacherScope();

            if ($moduleBypass) {
                if ($scope !== null) {
                    $query->whereIn('id', $scope['std']);
                }
            } elseif (is_array($classStdArr) && count($classStdArr) > 0) {
                $query->whereIn('id', $classStdArr);
            } elseif ($scope !== null) {
                $query->whereIn('id', $scope['std']);
            }

            if (session('user_profile_name') === 'Student') {
                $student = $this->studentEnrollment();
                $query->where('id', $student->standard_id ?? 0);
            }

            return $this->success($this->dropdownOptions($query->pluck('name', 'id')));
        });
    }

    /**
     * GET /api/result/dropdowns/divisions
     * Divisions mapped to one or more standards (standard_id, csv allowed),
     * honouring class-teacher / subject-teacher / student scoping.
     * Replaces web referer detection with optional `module_name` param.
     */
    public function divisions(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['standard_id' => 'required']);

            $standardIds = $this->csv($request->input('standard_id'));
            $moduleName = (string) $request->input('module_name', '');
            $moduleBypass = in_array($moduleName, self::MODULE_ARRAY, true);

            $query = DB::table('std_div_map')
                ->join('division', 'division.id', '=', 'std_div_map.division_id')
                ->whereIn('std_div_map.standard_id', $standardIds);

            $classDivArr = session('classTeacherDivArr');
            $scope = $this->subjectTeacherScope();

            if ($moduleBypass) {
                $this->applySubjectTeacherDivisionScope($query, $scope, $standardIds);
            } elseif (is_array($classDivArr) && count($classDivArr) > 0) {
                $query->whereIn('division.id', $classDivArr);
            } else {
                $this->applySubjectTeacherDivisionScope($query, $scope, $standardIds);
            }

            if (session('user_profile_name') === 'Student') {
                $student = $this->studentEnrollment();
                $query->where('division.id', $student->section_id ?? 0);
            }

            return $this->success($this->dropdownOptions($query->pluck('division.name', 'division.id')));
        });
    }

    /**
     * GET /api/result/dropdowns/subjects
     * Subjects mapped to one or more standards (standard_id, csv allowed).
     * `allow_grades` (Yes|No, default Yes) mirrors the marks-entry / exam
     * screens which only list gradable subjects. Teachers with a single
     * standard get only subjects from their allocation/timetable
     * (division_id refines the timetable lookup), same as the web AJAX.
     */
    public function subjects(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['standard_id' => 'required']);

            $standardIds = $this->csv($request->input('standard_id'));

            $where = ['sub_std_map.sub_institute_id' => session('sub_institute_id')];
            if (strtolower((string) $request->input('allow_grades', 'Yes')) === 'yes') {
                $where['sub_std_map.allow_grades'] = 'Yes';
            }

            if (count($standardIds) > 1) {
                $map = DB::table('subject')
                    ->join('sub_std_map', 'subject.id', '=', 'sub_std_map.subject_id')
                    ->whereIn('sub_std_map.standard_id', $standardIds)
                    ->where($where)
                    ->orderBy('sub_std_map.sort_order')
                    ->pluck('sub_std_map.display_name', 'subject.id');
            } elseif (session('user_profile_name') === 'Teacher') {
                $user = DB::table('tbluser')->where('id', session('user_id'))->first();

                if (! empty($user->allocated_standards)) {
                    $map = DB::table('subject')
                        ->join('sub_std_map', 'subject.id', '=', 'sub_std_map.subject_id')
                        ->whereIn('sub_std_map.standard_id', explode(',', $user->allocated_standards))
                        ->where($where)
                        ->orderBy('sub_std_map.sort_order')
                        ->pluck('sub_std_map.display_name', 'subject.id');
                } else {
                    $map = DB::table('subject as sub')
                        ->whereIn('sub.id', function ($subQuery) use ($request) {
                            $subQuery->select('subject_id')
                                ->from('timetable')
                                ->where('teacher_id', session('user_id'))
                                ->where('syear', session('syear'))
                                ->where('standard_id', $request->input('standard_id'))
                                ->where('division_id', $request->input('division_id'));
                        })
                        ->pluck('sub.subject_name', 'sub.id');
                }
            } else {
                $where['sub_std_map.standard_id'] = $request->input('standard_id');
                $map = DB::table('subject')
                    ->join('sub_std_map', 'subject.id', '=', 'sub_std_map.subject_id')
                    ->where($where)
                    ->orderBy('sub_std_map.sort_order')
                    ->pluck('sub_std_map.display_name', 'subject.id');
            }

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/all-subjects
     * Every subject mapped to a standard (no teacher scoping, no
     * allow_grades filter). Delegates to AJAXController@getAllSubjectList.
     */
    public function allSubjects(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['standard_id' => 'required']);

            $map = $this->delegate(AJAXController::class, 'getAllSubjectList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/exam-names
     * Attempted online-exam question papers for a class + subject
     * (grade/standard/division/subject). Replicates
     * AJAXController@getExamsList with its malformed IN-subquery corrected
     * (the web SQL is syntactically invalid and always errors).
     */
    public function examNames(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade_id'    => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
                'subject_id'  => 'required',
            ]);

            $rows = DB::table('tblstudent as ts')
                ->join('tblstudent_enrollment as tse', function ($join) {
                    $join->whereRaw('tse.student_id = ts.id');
                })->join('academic_year as ay', function ($join) {
                    $join->whereRaw('ay.term_id = tse.term_id');
                })->join('standard as std', function ($join) {
                    $join->whereRaw('std.id = tse.standard_id');
                })->join('std_div_map as sdm', function ($join) {
                    $join->whereRaw('sdm.standard_id = std.id');
                })->join('division as divi', function ($join) {
                    $join->whereRaw('divi.id = sdm.division_id');
                })->join('sub_std_map as ssm', function ($join) {
                    $join->whereRaw('ssm.standard_id = sdm.standard_id');
                })->join('subject as sub', function ($join) {
                    $join->whereRaw('sub.id = ssm.subject_id');
                })->join('question_paper as qp', function ($join) {
                    $join->whereRaw('qp.subject_id = ssm.subject_id and qp.standard_id = sdm.standard_id');
                })->join('lms_question_master as lqm', function ($join) {
                    // Web query has "lqm.id in and lqm.status = 1 (SELECT ...)"
                    // which is invalid SQL; corrected to the intended form.
                    $join->whereRaw('lqm.subject_id = ssm.subject_id and lqm.standard_id = sdm.standard_id and lqm.status = 1 and lqm.id in
                        (SELECT lqm2.id FROM lms_question_master as lqm2, question_paper as qp2 WHERE qp.id = qp2.id AND lqm2.status = 1 AND FIND_IN_SET(lqm2.id, qp.question_ids))');
                })->join('lms_online_exam_answer as am', function ($join) {
                    $join->whereRaw('am.question_paper_id = qp.id and am.student_id = ts.id and am.question_id = lqm.id');
                })
                ->selectRaw('qp.id, am.online_exam_id AS online_exam_ids, qp.paper_name AS question_paper_name')
                ->where('std.grade_id', $request->input('grade_id'))
                ->where('std.id', $request->input('standard_id'))
                ->where('divi.id', $request->input('division_id'))
                ->where('sub.id', $request->input('subject_id'))
                ->where('ts.sub_institute_id', session('sub_institute_id'))
                ->where('ay.syear', session('syear'))
                ->where('ay.sub_institute_id', session('sub_institute_id'))
                ->groupBy('qp.id')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'                  => $row->id,
                    'name'                => $row->question_paper_name,
                    'online_exam_ids'     => $row->online_exam_ids,
                    'question_paper_name' => $row->question_paper_name,
                ];
            }

            return $this->success($out);
        });
    }

    /**
     * GET /api/result/dropdowns/exam-masters
     * Exam-master titles for a term + standard.
     * Delegates to AJAXController@getExamsMasterList.
     */
    public function examMasters(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'term_id'     => 'required',
                'standard_id' => 'required',
            ]);

            $map = $this->delegate(AJAXController::class, 'getExamsMasterList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/subjects-by-create-exam
     * Subjects that have result_create_exam rows for a class
     * (grade/standard/division). Delegates to
     * AJAXController@getSubjectByCreateExam with sub_institute_id/syear
     * forced from the authenticated session.
     */
    public function subjectsByCreateExam(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade_id'    => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
            ]);

            $request->merge([
                'sub_institute_id' => session('sub_institute_id'),
                'syear'            => session('syear'),
            ]);

            $rows = $this->delegate(AJAXController::class, 'getSubjectByCreateExam', $request);

            $out = [];
            foreach ((array) $rows as $row) {
                $row = (array) $row;
                $out[] = [
                    'id'           => $row['subject_id'] ?? null,
                    'name'         => $row['subject_name'] ?? null,
                    'subject_id'   => $row['subject_id'] ?? null,
                    'subject_name' => $row['subject_name'] ?? null,
                ];
            }

            return $this->success($out);
        });
    }

    /**
     * GET /api/result/dropdowns/exams-by-create-exam
     * Exam-master entries that have result_create_exam rows for a class +
     * subject. Replicates AJAXController@getExamByCreateExam because the web
     * query joins result_exam_master without its "rem" alias (always a SQL
     * error) and only selected subject fields; here the exam id/title are
     * returned so the dropdown is usable.
     */
    public function examsByCreateExam(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'grade_id'    => 'required',
                'standard_id' => 'required',
                'division_id' => 'required',
                'subject_id'  => 'required',
            ]);

            $rows = DB::table('tblstudent as ts')
                ->join('tblstudent_enrollment as tse', function ($join) {
                    $join->whereRaw('tse.student_id = ts.id');
                })->join('academic_year as ay', function ($join) {
                    $join->whereRaw('ay.term_id = tse.term_id');
                })->join('standard as std', function ($join) {
                    $join->whereRaw('std.id = tse.standard_id');
                })->join('std_div_map as sdm', function ($join) {
                    $join->whereRaw('sdm.standard_id = std.id');
                })->join('division as divi', function ($join) {
                    $join->whereRaw('divi.id = sdm.division_id');
                })->join('result_create_exam as rce', function ($join) {
                    $join->whereRaw('rce.standard_id = sdm.standard_id');
                })->join('subject as sub', function ($join) {
                    $join->whereRaw('sub.id = rce.subject_id');
                })->join('result_exam_master as rem', function ($join) {
                    $join->whereRaw('rem.id = rce.exam_id');
                })
                ->selectRaw('rce.exam_id, rem.ExamTitle as exam_title, sub.subject_name as subject_name, rce.subject_id as subject_id')
                ->where('std.grade_id', $request->input('grade_id'))
                ->where('std.id', $request->input('standard_id'))
                ->where('divi.id', $request->input('division_id'))
                ->where('rce.subject_id', $request->input('subject_id'))
                ->where('ts.sub_institute_id', session('sub_institute_id'))
                ->where('ay.syear', session('syear'))
                ->where('ay.sub_institute_id', session('sub_institute_id'))
                ->groupBy('rce.exam_id')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'id'           => $row->exam_id,
                    'name'         => $row->exam_title,
                    'subject_id'   => $row->subject_id,
                    'subject_name' => $row->subject_name,
                ];
            }

            return $this->success($out);
        });
    }

    /**
     * GET /api/result/dropdowns/chapters
     * Chapters for a standard + subject (subject_id csv allowed).
     * Delegates to AJAXController@getChapterList.
     */
    public function chapters(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'subject_id'  => 'required',
                'standard_id' => 'required',
            ]);

            $map = $this->delegate(AJAXController::class, 'getChapterList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/topics
     * Topics for one or more chapters (chapter_id, csv allowed).
     * Delegates to AJAXController@getTopicList.
     */
    public function topics(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['chapter_id' => 'required']);

            $map = $this->delegate(AJAXController::class, 'getTopicList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/exams
     * Created exams (result_create_exam titles) filtered by standard plus
     * either term_id+subject_id or exam_id (same combinations as the web
     * AJAX). Delegates to AJAXController@getExamList.
     */
    public function exams(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, ['standard_id' => 'required']);

            $map = $this->delegate(AJAXController::class, 'getExamList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/co-scholastic-parents
     * Co-scholastic parent (skill group) titles for the institute.
     * Delegates to AJAXController@getCoScholasticParentList.
     */
    public function coScholasticParents(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $map = $this->delegate(AJAXController::class, 'getCoScholasticParentList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/co-scholastics
     * Co-scholastic items under a parent for a term + standard.
     * Delegates to AJAXController@getCoScholasticList.
     */
    public function coScholastics(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'co_scholastic_parent_id' => 'required',
                'term_id'                 => 'required',
                'standard_id'             => 'required',
            ]);

            $map = $this->delegate(AJAXController::class, 'getCoScholasticList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /**
     * GET /api/result/dropdowns/activity-masters
     * Activity-master titles for a skill + standard (term_id optional).
     * Accepts `standard` (web param name) or `standard_id`.
     * Delegates to AJAXController@getActivityMasterList.
     */
    public function activityMasters(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            if (! $request->filled('standard') && $request->filled('standard_id')) {
                $request->merge(['standard' => $request->input('standard_id')]);
            }

            $this->validate($request, [
                'skillset_id' => 'required',
                'standard'    => 'required',
            ]);

            $map = $this->delegate(AJAXController::class, 'getActivityMasterList', $request);

            return $this->success($this->dropdownOptions($map));
        });
    }

    /* ---------------------------------------------------------------------
     | Internal helpers (scoping replicated from Helper.php SearchChain)
     * -------------------------------------------------------------------*/

    /**
     * Split a csv request value ("1,2,3") into a clean array of ids.
     *
     * @return array<int, string>
     */
    private function csv($value): array
    {
        return array_values(array_filter(explode(',', (string) $value), function ($v) {
            return $v !== '';
        }));
    }

    /**
     * Standards/divisions a subject-teacher (or supervisor profile with
     * allocated_standards) may see - computed exactly like the web
     * SearchChain helper, because ApiSessionHydrator does not populate the
     * subjectTeacher*Arr session keys.
     *
     * @return array{std: array, div: array, allocated: bool}|null null = no subject-teacher scoping applies
     */
    private function subjectTeacherScope(): ?array
    {
        $profileName = (string) session('user_profile_name');

        $isTeacher = $profileName === 'Teacher';
        $isSupervisor = ! in_array($profileName, ['Super Admin', 'Admin', 'Teacher', 'LMS Teacher', 'Student'], true);

        if (! $isTeacher && ! $isSupervisor) {
            return null;
        }

        $user = DB::table('tbluser')->where('id', session('user_id'))->first();

        if (! empty($user->allocated_standards)) {
            $stdArr = DB::table('standard')
                ->whereRaw('id IN (' . $user->allocated_standards . ')')
                ->where('sub_institute_id', session('sub_institute_id'))
                ->pluck('id')->unique()->values()->all();

            $divArr = [];
            if (count($stdArr) > 0) {
                $divArr = DB::table('std_div_map')
                    ->whereIn('standard_id', $stdArr)
                    ->where('sub_institute_id', session('sub_institute_id'))
                    ->pluck('division_id')->toArray();
            }

            return ['std' => $stdArr, 'div' => $divArr, 'allocated' => true];
        }

        if ($isTeacher) {
            $rows = DB::table('timetable')
                ->where('teacher_id', session('user_id'))
                ->where('syear', session('syear'))
                ->where('sub_institute_id', session('sub_institute_id'))
                ->get(['standard_id', 'division_id']);

            return [
                'std'       => $rows->pluck('standard_id')->unique()->values()->all(),
                'div'       => $rows->pluck('division_id')->unique()->values()->all(),
                'allocated' => false,
            ];
        }

        return null;
    }

    /**
     * Restrict a divisions query to the subject-teacher's divisions. For a
     * single-standard timetable-based teacher the web AJAX narrows further to
     * divisions actually taught for that standard (timetable subquery) -
     * replicated here.
     */
    private function applySubjectTeacherDivisionScope($query, ?array $scope, array $standardIds): void
    {
        if ($scope === null) {
            return;
        }

        if ($scope['allocated']) {
            if (! empty($scope['div'])) {
                $query->whereIn('division.id', $scope['div']);
            }

            return;
        }

        if (count($standardIds) === 1 && ! empty($scope['div'])) {
            $divArr = $scope['div'];
            $standardId = $standardIds[0];

            $query->whereIn('division.id', function ($subQuery) use ($divArr, $standardId) {
                $subQuery->select('division_id')
                    ->from('timetable')
                    ->where('teacher_id', session('user_id'))
                    ->where('standard_id', $standardId)
                    ->whereIn('division_id', $divArr)
                    ->where('syear', session('syear'));
            });

            return;
        }

        $query->whereIn('division.id', $scope['div']);
    }

    /**
     * Current student's enrollment row (standard/section) - same lookup the
     * web AJAX performs for Student profiles.
     */
    private function studentEnrollment(): ?object
    {
        return DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
            ->where('s.sub_institute_id', session('sub_institute_id'))
            ->where('se.syear', session('syear'))
            ->where('s.id', session('user_id'))
            ->first();
    }
}
