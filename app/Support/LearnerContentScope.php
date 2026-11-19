<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What a signed-in STUDENT may open in the Classroom Resource lane.
 *
 * Content endpoints used to take a chapter or content id and a school from the request and trust them, so a Std 9
 * student could read Std 10 content by changing an id, and `lms-courses` decided "is this a student?" from a
 * `user_profile_name` the client typed. This class answers from the verified identity instead:
 *
 *  - who the caller is comes from the request attributes App\Http\Middleware\RequireApiJwt fills in from the validated
 *    token (never from the body), and
 *  - what the student may open is the standard(s) of their own enrollment, plus the shared "Other" section's
 *    standards, which is exactly the set `ApiLmsCourseController::getCourseData` lists for them.
 *
 * Staff are never restricted here: every method that decides access returns true for a caller who is not a learner.
 * When the guard is switched off (API_GUARD_ENFORCE=false, an emergency rollback) there is no verified identity, so
 * nobody is treated as a learner and behaviour is what it was before this class existed.
 */
class LearnerContentScope
{
    /**
     * The verified caller, or null when the request did not pass the API guard.
     *
     * @return array{user_id:int,school:int,is_student:bool}|null
     */
    public static function identity(Request $request): ?array
    {
        $userId = (int) $request->attributes->get('verified_user_id', 0);
        if ($userId <= 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'school' => (int) $request->attributes->get('verified_school', 0),
            'is_student' => (bool) $request->attributes->get('verified_is_student', false),
        ];
    }

    public static function isLearner(?array $identity): bool
    {
        return $identity !== null && $identity['is_student'] === true;
    }

    /**
     * Standard ids this student may open: the standard of their enrollment for the school's current academic year (or,
     * failing that, their most recent enrollment) plus the school's "Other" section standards.
     *
     * @return list<int>
     */
    public static function allowedStandardIds(int $studentId, int $school): array
    {
        if ($studentId <= 0 || $school <= 0) {
            return [];
        }

        $currentYears = DB::table('academic_year')
            ->where('sub_institute_id', $school)
            ->whereRaw('? BETWEEN start_date AND end_date', [date('Y-m-d')])
            ->pluck('syear')
            ->all();

        $enrolled = [];
        if ($currentYears !== []) {
            $enrolled = DB::table('tblstudent_enrollment')
                ->where('student_id', $studentId)
                ->where('sub_institute_id', $school)
                ->whereIn('syear', $currentYears)
                ->pluck('standard_id')
                ->all();
        }

        if ($enrolled === []) {
            $latest = DB::table('tblstudent_enrollment')
                ->where('student_id', $studentId)
                ->where('sub_institute_id', $school)
                ->max('syear');
            if ($latest !== null) {
                $enrolled = DB::table('tblstudent_enrollment')
                    ->where('student_id', $studentId)
                    ->where('sub_institute_id', $school)
                    ->where('syear', $latest)
                    ->pluck('standard_id')
                    ->all();
            }
        }

        $otherSection = DB::table('academic_section')
            ->where('sub_institute_id', $school)
            ->where('title', 'Other')
            ->pluck('id')
            ->all();
        $shared = $otherSection === []
            ? []
            : DB::table('standard')
                ->where('sub_institute_id', $school)
                ->whereIn('grade_id', $otherSection)
                ->pluck('id')
                ->all();

        return array_values(array_unique(array_map('intval', array_merge($enrolled, $shared))));
    }

    /**
     * May this caller open content that belongs to the given standard and subject?
     *
     * A learner may when the standard is one of their own and, if the subject is an elective, they chose it. Anyone
     * else (staff, or a request the guard did not identify) is not restricted here.
     */
    public static function mayOpen(?array $identity, int $standardId, int $subjectId): bool
    {
        if (! self::isLearner($identity)) {
            return true;
        }
        if ($standardId <= 0) {
            return false;
        }
        if (! in_array($standardId, self::allowedStandardIds($identity['user_id'], $identity['school']), true)) {
            return false;
        }

        $elective = DB::table('sub_std_map')
            ->where('sub_institute_id', $identity['school'])
            ->where('standard_id', $standardId)
            ->where('subject_id', $subjectId)
            ->value('elective_subject');
        if ($elective === 'Yes') {
            return DB::table('student_optional_subject')
                ->where('sub_institute_id', $identity['school'])
                ->where('student_id', $identity['user_id'])
                ->where('subject_id', $subjectId)
                ->exists();
        }

        return true;
    }

    /** Same as mayOpen(), for a chapter_master row (or any object carrying standard_id and subject_id). */
    public static function mayOpenChapter(?array $identity, ?object $chapter): bool
    {
        if (! self::isLearner($identity)) {
            return true;
        }
        if ($chapter === null) {
            return false;
        }

        return self::mayOpen($identity, (int) ($chapter->standard_id ?? 0), (int) ($chapter->subject_id ?? 0));
    }
}
