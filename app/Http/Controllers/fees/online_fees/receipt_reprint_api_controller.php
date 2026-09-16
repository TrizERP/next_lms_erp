<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Http\Controllers\AJAXController;
use App\Http\Controllers\Controller;
use App\Services\Fees\FeeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * New-ERP JSON wrapper around AJAXController::ajax_PDF_FeesReceipt(action=
 * fees_re_receipt|other_fees_re_receipt). Reuses that method's existing
 * receipt-rendering logic unchanged — this controller only translates its
 * raw string/URL response into the {status, data} JSON contract used by the
 * rest of the New-ERP API surface (see online_fees_payment_api_controller),
 * and records a RECEIPT_REPRINTED audit event.
 */
class receipt_reprint_api_controller extends Controller
{
    private const ALLOWED_ACTIONS = ['fees_re_receipt', 'other_fees_re_receipt'];

    public function reprint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => ['required'],
            'receipt_id_html' => ['required'],
            'sub_institute_id' => ['required', 'numeric'],
            'syear' => ['required', 'numeric'],
            'action' => ['nullable', 'in:' . implode(',', self::ALLOWED_ACTIONS)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->errors()->first(),
                'data' => null,
            ], 422);
        }

        $action = $request->input('action', 'fees_re_receipt');

        try {
            $ajaxController = new AJAXController();

            // ajax_PDF_FeesReceipt reads student_id/receipt_id_html/paper_size/
            // sub_institute_id/syear straight off the request, same as every
            // other New-ERP caller of this method.
            $request->merge(['type' => 'API', 'action' => $action]);

            $result = $ajaxController->ajax_PDF_FeesReceipt($request);

            // ajax_PDF_FeesReceipt returns either a pretty-printed JSON error
            // string ("No Record Found") or a raw PDF URL string.
            $decoded = json_decode($result, true);
            if (is_array($decoded) && isset($decoded['message'])) {
                return response()->json([
                    'status' => 0,
                    'message' => $decoded['message'],
                    'data' => null,
                ], 404);
            }

            FeeAuditService::logReceiptReprinted(
                $request->input('student_id'),
                $request->input('receipt_id_html'),
                $action,
                $request->input('sub_institute_id')
            );

            return response()->json([
                'status' => 1,
                'data' => [
                    'pdf_url' => $result,
                    'receipt_no' => $request->input('receipt_id_html'),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Unable to generate receipt for reprint.',
                'data' => null,
            ], 422);
        }
    }
}
