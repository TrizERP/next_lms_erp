<?php

namespace App\Http\Controllers\api\easy_com;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp Report  (whatsapp_sent_messages)
 *
 * Fixed relative to the Blade flow (WhatsappController::whatsappSentGenerateReportDetails):
 *  - the payload was $result['stu_data'] carrying Eloquent rows with hasMany
 *    relations (student/standard/division each nested as a one-element ARRAY),
 *    so the flat columns the Next.js table asked for - enrollment_no,
 *    student_name, standard_name - were never present and the grid stayed
 *    empty. The relations are flattened into scalar columns by a join here;
 *  - the report is scoped by grade as well as standard/division;
 *  - the date range used whereBetween only when BOTH dates were supplied;
 *    either bound now works on its own, and to_date is inclusive;
 *  - the unread-reply count (the `messages` relation) is aggregated as a single
 *    integer instead of shipping the whole IncomingMessage rows.
 */
class WhatsappReportApiController extends BaseEasyComApiController
{
    /**
     * GET /api/easy_com/reports/whatsapp
     *     ?from_date=&to_date=&grade=&standard=&division=&mobile_no=&message_status=
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'from_date' => 'nullable|date',
                'to_date'   => 'nullable|date',
            ]);

            $subInstituteId = $this->subInstituteId();
            $syear = $this->syear();

            $fromDate = $this->filter($request, 'from_date');
            $toDate = $this->filter($request, 'to_date');
            $grade = $this->filter($request, 'grade');
            $standard = $this->filter($request, 'standard');
            $division = $this->filter($request, 'division');
            $mobileNo = $this->filter($request, 'mobile_no');
            $status = $this->filter($request, 'message_status');

            $query = DB::table('whatsapp_sent_messages as w')
                ->leftJoin('tblstudent as s', function ($join) use ($subInstituteId) {
                    $join->on('s.id', '=', 'w.student_id')
                        ->where('s.sub_institute_id', '=', $subInstituteId);
                })
                ->leftJoin('standard as ss', function ($join) use ($subInstituteId) {
                    $join->on('ss.id', '=', 'w.standard_id')
                        ->where('ss.sub_institute_id', '=', $subInstituteId);
                })
                ->leftJoin('division as dd', function ($join) use ($subInstituteId) {
                    $join->on('dd.id', '=', 'w.division_id')
                        ->where('dd.sub_institute_id', '=', $subInstituteId);
                })
                ->selectRaw("w.id                as id,
                    w.student_id                 as student_id,
                    s.enrollment_no              as enrollment_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) as student_name,
                    ss.name                      as standard_name,
                    dd.name                      as division_name,
                    w.whatsapp_number            as whatsapp_number,
                    w.message                    as message,
                    w.attachment                 as attachment,
                    w.message_status             as message_status,
                    w.message_error              as message_error,
                    w.sent_date                  as sent_date,
                    w.created_by_name            as created_by_name,
                    (SELECT COUNT(*) FROM incoming_messages im
                       WHERE im.whatsapp_number = w.whatsapp_number
                         AND im.type = 'incoming'
                         AND im.is_seen = 0)     as unread_replies")
                ->where('w.sub_institute_id', $subInstituteId)
                ->where('w.syear', $syear);

            if ($standard !== '') {
                $query->where('w.standard_id', $standard);
            }

            if ($division !== '') {
                $query->where('w.division_id', $division);
            }

            if ($grade !== '') {
                $query->where('ss.grade_id', $grade);
            }

            if ($mobileNo !== '') {
                $query->where('w.whatsapp_number', 'like', '%'.$mobileNo);
            }

            if ($status !== '') {
                $query->where('w.message_status', $status);
            }

            if ($fromDate !== '') {
                $query->whereDate('w.sent_date', '>=', $fromDate);
            }

            if ($toDate !== '') {
                $query->whereDate('w.sent_date', '<=', $toDate);
            }

            $rows = $query->orderByDesc('w.id')->limit(5000)->get();

            return $this->success($rows, 'Success', 200, ['count' => $rows->count()]);
        });
    }
}
