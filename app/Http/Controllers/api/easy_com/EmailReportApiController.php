<?php

namespace App\Http\Controllers\api\easy_com;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Email Report  (email_sent_parents)
 *
 * Fixed relative to the Blade flow (send_email_report_controller::store):
 *  - the controller hard-coded `$type = "WEB"` before calling is_mobile(), so
 *    the API branch was unreachable and every JSON client received a rendered
 *    Blade view. That is why the Next.js page reported "the Laravel endpoint
 *    returned HTML instead of an API response";
 *  - from_date / to_date were mandatory (whereBetween on raw $_REQUEST values);
 *    both are optional here;
 *  - the to_date bound excluded same-day rows because CREATED_ON is a
 *    timestamp; it is now inclusive to 23:59:59;
 *  - the sender was returned as a bare USER_ID; the user's name is joined in;
 *  - users() exposes the sender dropdown the Blade show screen rendered - the
 *    Next.js page previously asked for a typed-in user id.
 */
class EmailReportApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/reports/email/options */
    public function options(Request $request): JsonResponse
    {
        return $this->run(function () {
            $users = DB::table('tbluser')
                ->where('sub_institute_id', $this->subInstituteId())
                ->where('status', 1)
                ->orderBy('first_name')
                ->selectRaw("id, CONCAT_WS(' ', first_name, last_name) as name")
                ->get();

            return $this->success(['users' => $users], 'Success');
        });
    }

    /**
     * GET /api/easy_com/reports/email?from_date=&to_date=&user_id=
     */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $this->validate($request, [
                'from_date' => 'nullable|date',
                'to_date'   => 'nullable|date',
                'user_id'   => 'nullable',
            ]);

            $fromDate = $this->filter($request, 'from_date');
            $toDate = $this->filter($request, 'to_date');
            // The Blade form named this field `user`; accept both.
            $userId = $this->filter($request, 'user_id') ?: $this->filter($request, 'user');

            $query = DB::table('email_sent_parents as e')
                ->leftJoin('tbluser as u', function ($join) {
                    $join->on('e.USER_ID', '=', 'u.id')
                        ->on('e.sub_institute_id', '=', 'u.sub_institute_id');
                })
                ->selectRaw("e.ID          as id,
                    e.EMAIL       as email,
                    e.SUBJECT     as subject,
                    e.EMAIL_TEXT  as email_text,
                    e.ATTECHMENT  as attachment,
                    e.SYEAR       as syear,
                    e.IP          as ip,
                    e.USER_ID     as user_id,
                    e.CREATED_ON  as sent_on,
                    CONCAT_WS(' ', u.first_name, u.last_name) as user_name")
                ->where('e.sub_institute_id', $this->subInstituteId());

            if ($userId !== '') {
                $query->where('e.USER_ID', $userId);
            }

            if ($fromDate !== '') {
                $query->where('e.CREATED_ON', '>=', $fromDate);
            }

            if ($toDate !== '') {
                $query->where('e.CREATED_ON', '<=', $this->endOfDay($toDate));
            }

            $rows = $query->orderByDesc('e.CREATED_ON')->limit(5000)->get()
                ->map(function ($row) {
                    // Stored as an absolute server path; expose the file name only.
                    $row->attachment_name = $row->attachment ? basename($row->attachment) : '';

                    return $row;
                });

            return $this->success($rows, 'Success', 200, ['count' => $rows->count()]);
        });
    }
}
