<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PAL Workspace API.
 *
 * Purpose-built, stateless replacement for the legacy `/lms/pal` web flow so the
 * SPA no longer has to stitch together the 1.3 MB `/api/lms-courses` catalog or
 * depend on the Laravel session (the legacy `getSubjectList` reads
 * `sub_institute_id` from the session only). Every value is derived from the
 * authenticated JWT (`pal_auth`, injected by the PalApiAuth middleware) and
 * request parameters:
 *
 *   GET /api/pal/workspace/students          list students for the picker
 *   GET /api/pal/workspace/{learnerId}        a learner's PAL workspace
 *
 * Auth + per-learner ownership is enforced upstream by `pal.auth`; this
 * controller only reads `pal_auth` for the caller's institute scope.
 */
class PalWorkspaceController extends Controller
{
    /** @return array<string,mixed> */
    private function auth(Request $request): array
    {
        $auth = $request->attributes->get('pal_auth');

        return is_array($auth) ? $auth : [];
    }

    /** Institute ids the caller may read (CSV membership supported). */
    private function callerInstitutes(array $auth): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) ($auth['sub_institute_id'] ?? ''))),
            'strlen'
        ));
    }

    private function ok($data, string $message = 'Success'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    private function fail(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    /**
     * GET /api/pal/workspace/students?syear=&grade_id=&standard_id=&division_id=
     *
     * Lists students within the caller's institute, optionally narrowed by
     * grade / standard / division. Students cannot enumerate other learners.
     */
    public function students(Request $request): JsonResponse
    {
        $auth = $this->auth($request);
        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Students cannot list other learners.', 403);
        }

        $syear = $request->input('syear');
        if ($syear === null || $syear === '') {
            return $this->fail('syear is required.');
        }

        $gradeId    = $request->input('grade_id');
        $standardId = $request->input('standard_id');
        $divisionId = $request->input('division_id');

        $institutes = $this->callerInstitutes($auth);
        $isSuperAdmin = (int) ($auth['is_admin'] ?? 0) === 2;

        $query = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('s.id', '=', 'se.student_id')->whereNull('se.end_date');
            })
            ->leftJoin('academic_section as ac', function ($join) {
                $join->on('ac.id', '=', 'se.grade_id')->on('ac.sub_institute_id', '=', 's.sub_institute_id');
            })
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('division as d', 'd.id', '=', 'se.section_id')
            ->where('se.syear', $syear)
            ->when(! $isSuperAdmin && ! empty($institutes), function ($q) use ($institutes) {
                $q->whereIn('s.sub_institute_id', $institutes);
            })
            ->when($gradeId !== null && $gradeId !== '', fn ($q) => $q->where('se.grade_id', $gradeId))
            ->when($standardId !== null && $standardId !== '', fn ($q) => $q->where('se.standard_id', $standardId))
            ->when($divisionId !== null && $divisionId !== '', fn ($q) => $q->where('se.section_id', $divisionId))
            ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                se.grade_id, se.standard_id, se.section_id AS division_id, se.roll_no, s.enrollment_no,
                ac.title AS academic_section, st.name AS standard_name, d.name AS division_name")
            ->groupBy('s.id')
            ->orderBy('student_name')
            ->limit(2000)
            ->get();

        return $this->ok($query);
    }

    /**
     * GET /api/pal/workspace/preview?syear=&standard_id=&grade_id=&division_id=
     *
     * "Guest student" preview: the student-facing PAL for a whole class
     * (subjects + chapters for a standard), with no specific learner and no
     * attempt history. Lets staff experience what a student in that class sees.
     * Scoped to the caller's own institute.
     */
    public function preview(Request $request): JsonResponse
    {
        $auth = $this->auth($request);
        if (($auth['role'] ?? '') === 'student') {
            return $this->fail('Students cannot preview other classes.', 403);
        }

        $syear = $request->input('syear');
        $standardId = $request->input('standard_id');
        if ($syear === null || $syear === '') {
            return $this->fail('syear is required.');
        }
        if ($standardId === null || $standardId === '') {
            return $this->fail('standard_id is required.');
        }

        $institutes = $this->callerInstitutes($auth);
        $subInstituteId = (int) ($institutes[0] ?? 0);
        if ($subInstituteId <= 0) {
            // Multi-client super admin may pass one explicitly.
            $subInstituteId = (int) $request->input('sub_institute_id');
        }
        if ($subInstituteId <= 0) {
            return $this->fail('No institute available for this account.', 403);
        }

        $subjectRows = DB::table('sub_std_map')
            ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
            ->where('sub_std_map.sub_institute_id', $subInstituteId)
            ->where('sub_std_map.standard_id', $standardId)
            ->orderBy('sub_std_map.sort_order')
            ->select('subject.id as subject_id', 'sub_std_map.display_name as subject_name')
            ->get();

        $subjectIds = $subjectRows->pluck('subject_id')->all();
        $chaptersBySubject = [];
        if (! empty($subjectIds)) {
            $chapterRows = DB::table('chapter_master')
                ->where('sub_institute_id', $subInstituteId)
                ->where('standard_id', $standardId)
                ->whereIn('subject_id', $subjectIds)
                ->orderBy('sort_order')
                ->select('id', 'chapter_name', 'subject_id')
                ->get();
            foreach ($chapterRows as $chapter) {
                $chaptersBySubject[$chapter->subject_id][] = $chapter;
            }
        }

        $subjects = $subjectRows->map(function ($subject) use ($chaptersBySubject) {
            return [
                'subject_id'   => (string) $subject->subject_id,
                'subject_name' => $subject->subject_name,
                'chapters'     => collect($chaptersBySubject[$subject->subject_id] ?? [])
                    ->map(fn ($c) => ['id' => (string) $c->id, 'chapter_name' => $c->chapter_name])
                    ->values(),
            ];
        })->values();

        $standardName = DB::table('standard')->where('id', $standardId)->value('name');

        return $this->ok([
            'student' => [
                'student_id'    => '',
                'name'          => 'Guest student',
                'grade_id'      => (string) $request->input('grade_id', ''),
                'standard_id'   => (string) $standardId,
                'division_id'   => (string) $request->input('division_id', ''),
                'enrollment_no' => '',
                'standard_name' => $standardName ?? '',
                'grade_name'    => '',
            ],
            'subjects'            => $subjects,
            'per_chapter_quiz'    => (object) [],
            'attempts_by_chapter' => (object) [],
        ]);
    }

    /**
     * GET /api/pal/workspace/{learnerId}?syear=
     *
     * Returns a learner's PAL workspace: profile, subjects (each with its
     * chapters and PAL quiz counts) and attempts grouped by chapter. Subjects
     * and chapters are read straight from `sub_std_map` / `chapter_master`
     * (scoped by the learner's own institute), not the session or the catalog.
     */
    public function workspace(Request $request, $learnerId): JsonResponse
    {
        $learnerId = (int) $learnerId;
        if ($learnerId <= 0) {
            return $this->fail('A valid learner id is required.');
        }

        $syear = $request->input('syear');
        if ($syear === null || $syear === '') {
            return $this->fail('syear is required.');
        }

        // Scope all data to the learner's own institute (ownership already
        // verified by pal.auth), so cross-institute staff read consistent data.
        $subInstituteId = DB::table('tblstudent')->where('id', $learnerId)->value('sub_institute_id');
        if ($subInstituteId === null) {
            return $this->fail('Learner not found.', 404);
        }

        $student = DB::table('tblstudent as s')
            ->leftJoin('tblstudent_enrollment as se', function ($join) use ($syear) {
                $join->on('s.id', '=', 'se.student_id')
                    ->where('se.syear', '=', $syear)
                    ->whereNull('se.end_date');
            })
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('academic_section as ac', 'ac.id', '=', 'se.grade_id')
            ->where('s.id', $learnerId)
            ->selectRaw("s.id, CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS name,
                s.enrollment_no, se.grade_id, se.standard_id, se.section_id AS division_id,
                st.name AS standard_name, ac.title AS grade_name")
            ->first();

        $standardId = $student->standard_id ?? null;

        // ---- PAL attempts (chapter id lives in question_paper.paper_desc) ----
        $attempts = DB::table('question_paper as q')
            ->join('lms_online_exam as l', 'l.question_paper_id', '=', 'q.id')
            ->where('q.created_by', $learnerId)
            ->where('q.sub_institute_id', $subInstituteId)
            ->where('q.exam_type', 'PAL')
            ->orderBy('l.start_time', 'desc')
            ->selectRaw("q.id, q.paper_desc, q.paper_name, q.subject_id, q.standard_id, q.total_marks,
                l.total_right, l.total_wrong, l.obtain_marks, l.start_time")
            ->get();

        $attemptsByChapter = [];
        $perChapterQuiz = [];
        $attemptSubjectByChapter = [];
        foreach ($attempts as $attempt) {
            $chapterId = (string) $attempt->paper_desc;
            if ($chapterId === '') {
                continue;
            }
            $totalRight = (int) $attempt->total_right;
            $total = $totalRight + (int) $attempt->total_wrong;
            $attemptsByChapter[$chapterId][] = [
                'chapter_id'   => $chapterId,
                'subject_id'   => (string) $attempt->subject_id,
                'total_right'  => $totalRight,
                'total'        => $total,
                'obtain_marks' => (int) $attempt->obtain_marks,
                'percent'      => $total > 0 ? min(100, (int) round(($totalRight / $total) * 100)) : 0,
                'start_time'   => $attempt->start_time,
            ];
            $perChapterQuiz[$chapterId] = ($perChapterQuiz[$chapterId] ?? 0) + 1;
            if ((string) $attempt->subject_id !== '') {
                $attemptSubjectByChapter[$chapterId] = (string) $attempt->subject_id;
            }
        }

        // ---- Subjects + chapters (institute-scoped, session-free) ----
        // Keyed by subject id so attempted chapters can be merged in without
        // duplicates. $knownChapters lets us cheaply detect attempts whose
        // chapter is not part of the mapped curriculum.
        $subjectMap = [];      // subjectId => ['subject_id','subject_name','chapters'=>[cid=>['id','chapter_name']]]
        $subjectOrder = [];
        $knownChapters = [];   // chapterId => true

        if ($standardId) {
            $subjectRows = DB::table('sub_std_map')
                ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
                ->where('sub_std_map.sub_institute_id', $subInstituteId)
                ->where('sub_std_map.standard_id', $standardId)
                ->orderBy('sub_std_map.sort_order')
                ->select('subject.id as subject_id', 'sub_std_map.display_name as subject_name')
                ->get();

            foreach ($subjectRows as $subject) {
                $sid = (string) $subject->subject_id;
                if (! isset($subjectMap[$sid])) {
                    $subjectMap[$sid] = ['subject_id' => $sid, 'subject_name' => $subject->subject_name, 'chapters' => []];
                    $subjectOrder[] = $sid;
                }
            }

            $subjectIds = array_keys($subjectMap);
            if (! empty($subjectIds)) {
                $chapterRows = DB::table('chapter_master')
                    ->where('sub_institute_id', $subInstituteId)
                    ->where('standard_id', $standardId)
                    ->whereIn('subject_id', $subjectIds)
                    ->orderBy('sort_order')
                    ->select('id', 'chapter_name', 'subject_id')
                    ->get();

                foreach ($chapterRows as $chapter) {
                    $sid = (string) $chapter->subject_id;
                    $cid = (string) $chapter->id;
                    if (isset($subjectMap[$sid])) {
                        $subjectMap[$sid]['chapters'][$cid] = ['id' => $cid, 'chapter_name' => $chapter->chapter_name];
                        $knownChapters[$cid] = true;
                    }
                }
            }
        }

        // ---- Merge attempted chapters missing from the curriculum ----
        // Resolve their real names (by id) and subject names so nothing is lost.
        $missingChapterIds = array_values(array_filter(
            array_keys($attemptSubjectByChapter),
            fn ($cid) => ! isset($knownChapters[$cid])
        ));
        $resolvedChapterNames = empty($missingChapterIds) ? [] : DB::table('chapter_master')
            ->whereIn('id', $missingChapterIds)
            ->pluck('chapter_name', 'id')
            ->all();

        $orphanSubjectIds = array_values(array_unique(array_filter(
            array_values($attemptSubjectByChapter),
            fn ($sid) => ! isset($subjectMap[$sid])
        )));
        $resolvedSubjectNames = empty($orphanSubjectIds) ? [] : DB::table('sub_std_map')
            ->join('subject', 'subject.id', '=', 'sub_std_map.subject_id')
            ->whereIn('subject.id', $orphanSubjectIds)
            ->where('sub_std_map.sub_institute_id', $subInstituteId)
            ->pluck('sub_std_map.display_name', 'subject.id')
            ->all();

        foreach ($attemptSubjectByChapter as $chapterId => $subjectId) {
            $chapterId = (string) $chapterId;
            $subjectId = (string) $subjectId;
            if (isset($knownChapters[$chapterId])) {
                continue;
            }
            if (! isset($subjectMap[$subjectId])) {
                $subjectMap[$subjectId] = [
                    'subject_id'   => $subjectId,
                    'subject_name' => $resolvedSubjectNames[$subjectId] ?? ('Subject #' . $subjectId),
                    'chapters'     => [],
                ];
                $subjectOrder[] = $subjectId;
            }
            $subjectMap[$subjectId]['chapters'][$chapterId] = [
                'id'           => $chapterId,
                'chapter_name' => $resolvedChapterNames[$chapterId] ?? ('Chapter #' . $chapterId),
            ];
            $knownChapters[$chapterId] = true;
        }

        // Attempted subjects first, then the rest — mirrors the SPA ordering.
        usort($subjectOrder, function ($a, $b) use ($subjectMap, $attemptsByChapter) {
            $has = fn ($sid) => collect($subjectMap[$sid]['chapters'])->keys()
                ->contains(fn ($cid) => isset($attemptsByChapter[$cid]));
            return ($has($b) ? 1 : 0) <=> ($has($a) ? 1 : 0);
        });

        $subjects = [];
        foreach ($subjectOrder as $sid) {
            $subjects[] = [
                'subject_id'   => $subjectMap[$sid]['subject_id'],
                'subject_name' => $subjectMap[$sid]['subject_name'],
                'chapters'     => array_values($subjectMap[$sid]['chapters']),
            ];
        }

        return $this->ok([
            'student' => [
                'student_id'    => (string) $learnerId,
                'name'          => $student->name ?? '',
                'grade_id'      => (string) ($student->grade_id ?? ''),
                'standard_id'   => (string) ($standardId ?? ''),
                'division_id'   => (string) ($student->division_id ?? ''),
                'enrollment_no' => (string) ($student->enrollment_no ?? ''),
                'standard_name' => $student->standard_name ?? '',
                'grade_name'    => $student->grade_name ?? '',
            ],
            'subjects'            => $subjects,
            'per_chapter_quiz'    => $perChapterQuiz,
            'attempts_by_chapter' => $attemptsByChapter,
        ]);
    }
}
