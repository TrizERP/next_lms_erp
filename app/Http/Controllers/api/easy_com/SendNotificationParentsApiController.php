<?php

namespace App\Http\Controllers\api\easy_com;

use App\Models\school_setup\SchoolModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\SearchStudent;
use function App\Helpers\send_FCM_Notification;
use function App\Helpers\sendNotification;

/**
 * Send app notification to parents.
 *
 * Fixed relative to the Blade flow (send_notification_parents_controller):
 *
 *  - create() dereferenced $_REQUEST['admission_year'] unguarded, so any client
 *    that did not send it got a 500. The admission-year filter is now optional,
 *    and academicYears() exposes the dropdown the Blade show screen rendered
 *    (Next.js had no academic-year selector at all);
 *  - store() re-ran the student query AND re-fetched SchoolModel once PER
 *    selected number, and re-inserted the app_notification row inside that
 *    loop. School data is fetched once here and the recipients resolved in one
 *    query;
 *  - $_SERVER['APP_URL'] is not guaranteed to be populated (it is an .env key,
 *    not a CGI variable), which made the logo URL - and sometimes the request -
 *    fail. config('app.url') is used instead;
 *  - FCM dispatch failures no longer abort the run; the in-app notification row
 *    is still written and the failure is reported per recipient.
 */
class SendNotificationParentsApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/send-notification-parents/options */
    public function options(Request $request): JsonResponse
    {
        return $this->run(function () {
            $years = DB::table('academic_year')
                ->where('sub_institute_id', $this->subInstituteId())
                ->groupBy('syear')
                ->orderBy('syear', 'desc')
                ->pluck('syear');

            return $this->success(['academicYears' => $years], 'Success');
        });
    }

    /**
     * GET /api/easy_com/send-notification-parents/recipients
     *     ?grade=&standard=&division=&admission_year=
     */
    public function recipients(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'grade'          => 'required',
                'standard'       => 'required',
                'division'       => 'required',
                'admission_year' => 'nullable',
            ], [], ['grade' => 'academic section']);

            $students = SearchStudent(
                $data['grade'],
                $data['standard'],
                $data['division'],
                $this->subInstituteId(),
                $this->syear(),
                '', '', '', '', '', '', '', '',
                $this->filter($request, 'admission_year')
            );

            $rows = [];
            foreach ($students as $index => $student) {
                $mobile = trim((string) ($student['mobile'] ?? ''));

                $rows[] = [
                    'sr_no'         => $index + 1,
                    'student_id'    => $student['student_id'] ?? null,
                    'enrollment_no' => $student['enrollment_no'] ?? '',
                    'name'          => $this->fullName($student),
                    'mobile'        => $mobile,
                    'standard_name' => $student['standard_name'] ?? '',
                    'division_name' => $student['division_name'] ?? '',
                    'eligible'      => $this->isValidMobile($mobile),
                ];
            }

            return $this->success([
                'grade'          => $data['grade'],
                'standard'       => $data['standard'],
                'division'       => $data['division'],
                'academic_year'  => $this->filter($request, 'admission_year'),
                'stu_data'       => $rows,
            ], 'Success');
        });
    }

    /**
     * POST /api/easy_com/send-notification-parents
     * Body: grade, standard, division, notificationText,
     *       sendNotification[<mobile>]=on
     */
    public function send(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'grade'            => 'required',
                'standard'         => 'required',
                'division'         => 'required',
                'notificationText' => 'required|string|max:1000',
            ], [], ['notificationText' => 'notification text']);

            $numbers = $this->selectionKeys($request, 'sendNotification');

            if (empty($numbers)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sendNotification' => ['Select at least one recipient.'],
                ]);
            }

            $subInstituteId = $this->subInstituteId();
            $syear = $this->syear();
            $text = $data['notificationText'];

            // One query for every selected parent instead of one per number.
            $students = DB::table('tblstudent_enrollment as se')
                ->join('tblstudent as s', function ($join) {
                    $join->on('s.id', '=', 'se.student_id')
                        ->on('s.sub_institute_id', '=', 'se.sub_institute_id');
                })
                ->selectRaw("s.id as student_id, s.mobile,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name")
                ->whereIn('s.mobile', $numbers)
                ->where('se.syear', $syear)
                ->whereNull('se.end_date')
                ->where('se.sub_institute_id', $subInstituteId)
                ->where('se.grade_id', $data['grade'])
                ->where('se.standard_id', $data['standard'])
                ->where('se.section_id', $data['division'])
                ->get();

            if ($students->isEmpty()) {
                return $this->error('None of the selected numbers belong to the chosen class.', 422);
            }

            $school = SchoolModel::where('id', $subInstituteId)->first();
            $schoolName = $school->SchoolName ?? '';
            $schoolLogo = rtrim((string) config('app.url'), '/').'/admin_dep/images/'.($school->Logo ?? '');

            // Device tokens for the whole batch, grouped by parent mobile.
            $tokensByMobile = DB::table('gcm_users')
                ->where('sub_institute_id', $subInstituteId)
                ->whereIn('mobile_no', $numbers)
                ->get(['mobile_no', 'gcm_regid'])
                ->groupBy('mobile_no');

            $notified = [];
            $pushFailed = [];

            foreach ($students as $student) {
                sendNotification([
                    'NOTIFICATION_TYPE'        => 'Notification',
                    'NOTIFICATION_DATE'        => now(),
                    'STUDENT_ID'               => $student->student_id,
                    'NOTIFICATION_DESCRIPTION' => $text,
                    'STATUS'                   => 0,
                    'SUB_INSTITUTE_ID'         => $subInstituteId,
                    'SYEAR'                    => $syear,
                    'SCREEN_NAME'              => 'general',
                    'CREATED_BY'               => $this->userId(),
                    'CREATED_IP'               => $request->ip(),
                ]);

                $notified[] = $student->student_id;

                $tokens = ($tokensByMobile[$student->mobile] ?? collect())
                    ->pluck('gcm_regid')
                    ->filter()
                    ->values()
                    ->all();

                if (empty($tokens)) {
                    continue;
                }

                foreach (array_chunk($tokens, 1000) as $chunk) {
                    try {
                        send_FCM_Notification($chunk, [
                            'body'    => $text,
                            'TYPE'    => 'Notification',
                            'USER_ID' => $student->student_id,
                            'title'   => $schoolName,
                            'image'   => $schoolLogo,
                        ], $subInstituteId);
                    } catch (\Throwable $e) {
                        // The in-app row is already saved; a push failure must
                        // not abort the remaining recipients.
                        $pushFailed[] = [
                            'student_id' => $student->student_id,
                            'reason'     => $e->getMessage(),
                        ];
                    }
                }
            }

            $summary = [
                'requested'   => count($numbers),
                'notified'    => count($notified),
                'push_failed' => $pushFailed,
            ];

            $message = count($notified).' notification(s) sent.';
            if ($pushFailed) {
                $message .= ' Push delivery failed for '.count($pushFailed).'.';
            }

            return $this->success($summary, $message);
        });
    }

    private function fullName(array $student): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [
            $student['first_name'] ?? '',
            $student['middle_name'] ?? '',
            $student['last_name'] ?? '',
        ])));
    }
}
