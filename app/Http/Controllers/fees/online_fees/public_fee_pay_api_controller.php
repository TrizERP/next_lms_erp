<?php

namespace App\Http\Controllers\fees\online_fees;

use App\Services\Fees\FeeAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Throwable;

/**
 * Login-free online fee payment for parents (/api/public-pay/{code}/...).
 *
 * A parent opens one school link, proves who they are by the mobile number registered for the
 * child, picks how much to pay and is handed to the school's configured gateway. Nothing here
 * trusts the browser: the school comes from the link, the student must belong to that school AND
 * carry the typed mobile on every call, and the payable amount is recomputed on the server.
 *
 * It reuses the legacy gateway code (get_fees / pay_fees / *_request_handler) unchanged, which
 * reads school and year from session(); the session is the in-memory one used by api.session,
 * never started or saved.
 */
class public_fee_pay_api_controller extends online_fees_collect_controller
{
    /** Gateways the legacy handlers implement; bank_name in fees_online_maping is normalised to one of these. */
    private const GATEWAYS = ['hdfc', 'axis', 'aggre_pay', 'icici', 'razorpay', 'payphi', 'hdfcrazorpay', 'icici_orange'];

    /** GET /api/public-pay/{code} - what the landing step shows. */
    public function school(string $code)
    {
        $school = $this->resolveSchool($code);

        return response()->json(['status' => 1, 'data' => [
            'name' => $school->name,
            'code' => $school->code,
            'gateway' => $school->gateway,
            'inline' => $school->gateway === 'razorpay',
        ]]);
    }

    /** POST /api/public-pay/{code}/lookup - children registered on a mobile number. */
    public function lookup(Request $request, string $code)
    {
        $school = $this->resolveSchool($code);
        $mobile = $this->mobileFrom($request);

        $students = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as se', function ($join) {
                $join->on('se.student_id', '=', 's.id')->on('se.sub_institute_id', '=', 's.sub_institute_id');
            })
            ->join('school_setup as ss', function ($join) {
                $join->on('ss.Id', '=', 's.sub_institute_id')->on('ss.syear', '=', 'se.syear');
            })
            ->where('s.sub_institute_id', $school->id)
            ->whereRaw($this->mobileSql('s.mobile'), [$mobile])
            ->whereNull('se.end_date')
            ->select('s.id', DB::raw("TRIM(CONCAT_WS(' ', s.first_name, s.last_name)) AS name"))
            ->distinct()
            ->orderBy('name')
            ->get();

        return response()->json(['status' => 1, 'data' => [
            'students' => $students->map(fn ($s) => ['id' => (string) $s->id, 'name' => (string) $s->name])->values(),
        ]]);
    }

    /** POST /api/public-pay/{code}/fees - what the child owes, shaped for the payment screen. */
    public function fees(Request $request, string $code)
    {
        $school = $this->resolveSchool($code);
        $studentId = $this->verifiedStudentId($request, $school);

        try {
            $fees = $this->payableFees($studentId, $school);
        } catch (Throwable $e) {
            return $this->fail($e instanceof \RuntimeException ? $e->getMessage() : 'We could not load the fees right now. Please try again.', 422, $e);
        }

        return response()->json(['status' => 1, 'data' => $fees['view']]);
    }

    /**
     * POST /api/public-pay/{code}/pay - start a payment.
     * Razorpay answers JSON for the in-page checkout; every other gateway answers the page that
     * posts the parent to the bank (the browser navigates here, see /checkout).
     */
    public function pay(Request $request, string $code)
    {
        $school = $this->resolveSchool($code);
        abort_unless($school->gateway === 'razorpay', 404);

        try {
            $order = $this->createRazorpayOrder($request, $school);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->fail('We could not start the payment. Please try again.', 422, $e);
        }

        return response()->json(['status' => 1, 'data' => $order]);
    }

    /** POST /api/public-pay/{code}/checkout - hand-off to a redirect-style gateway (HTML for the browser). */
    public function checkout(Request $request, string $code)
    {
        $school = $this->resolveSchool($code);
        $handler = $school->gateway . '_request_handler';
        abort_if($school->gateway === 'razorpay' || ! method_exists($this, $handler), 404);

        try {
            $studentId = $this->verifiedStudentId($request, $school);
            $fees = $this->payableFees($studentId, $school);
            $amount = $this->validatedAmount($request, $fees);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response($this->plainError($e->getMessage()), 422);
        } catch (Throwable $e) {
            Log::error('Public fee checkout failed.', ['school' => $school->id, 'exception' => $e]);

            return response($this->plainError('We could not start the payment. Please try again.'), 422);
        }

        // The legacy handlers read school/year from session() and the posted form from $_REQUEST,
        // exactly what show_*_fees.blade.php used to post.
        $student = $fees['raw']['stu_data'];
        $post = [
            'student_id' => $studentId,
            'syear' => $fees['year'],
            'pay_amount' => $amount,
            'total' => $amount,
            'standard_id' => $student['std_id'] ?? '',
            'grade_id' => $student['grade_id'] ?? '',
            'div_id' => $student['div_id'] ?? '',
            'std_div' => $student['stddiv'] ?? '',
            'full_name' => $student['name'] ?? '',
            'student_batch' => $student['student_batch'] ?? '',
            'totalDis' => 0,
            'fees_data' => ['fine' => 0],
            'months' => $fees['monthIds'],
            'submit' => 'Pay Now',
        ];
        $inner = Request::create('/', 'POST', $post);
        $this->attachSession($inner, $school->id, $fees['year'], $studentId);
        $_REQUEST = $post;
        $_POST = $post;

        return $this->{$handler}($inner);
    }

    /** POST /api/public-pay/{code}/razorpay/confirm - verify the checkout result and book the fee. */
    public function confirmRazorpay(Request $request, string $code)
    {
        $school = $this->resolveSchool($code);
        abort_unless($school->gateway === 'razorpay', 404);
        $studentId = $this->verifiedStudentId($request, $school);

        $input = $request->validate([
            'payment_ref' => ['required', 'integer'],
            'razorpay_payment_id' => ['required', 'string', 'max:255'],
            'razorpay_order_id' => ['required', 'string', 'max:255'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $pending = DB::table('fees_payment')
            ->where('id', $input['payment_ref'])
            ->where('student_id', $studentId)
            ->where('sub_institute_id', $school->id)
            ->first();
        if (! $pending || ! hash_equals((string) $pending->razorpay_order_id, $input['razorpay_order_id'])) {
            return $this->fail('We could not match this payment to your order. If money was deducted, it will be reconciled automatically.', 422);
        }

        $credentials = $this->razorpayCredentials($school->id, $studentId);
        if (! $credentials) {
            return $this->fail('Online payment is not configured for this school.', 422);
        }

        try {
            $api = new Api($credentials->key_id, $credentials->key_secret);
            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $input['razorpay_order_id'],
                'razorpay_payment_id' => $input['razorpay_payment_id'],
                'razorpay_signature' => $input['razorpay_signature'],
            ]);
        } catch (Throwable $e) {
            return $this->fail('The payment signature could not be verified. If money was deducted, it will be reconciled automatically.', 422, $e);
        }

        try {
            $payment = $api->payment->fetch($input['razorpay_payment_id']);
            $status = (string) $payment['status'];
            FeeAuditService::logCallbackReceived('razorpay', $input['razorpay_order_id'], $studentId, $school->id);

            $receipt = null;
            $alreadyBooked = false;
            DB::transaction(function () use ($input, $payment, $status, $pending, $school, $request, &$receipt, &$alreadyBooked) {
                $row = DB::table('fees_payment')->where('id', $pending->id)->lockForUpdate()->first();
                if ($row->razorpay_payment_status === 'PS') {
                    $alreadyBooked = true;

                    return;
                }
                DB::table('fees_payment')->where('id', $pending->id)->update([
                    'razorpay_payment_status' => $status === 'captured' ? 'PS' : 'PR',
                    'razorpay_dashboard_ps' => $status,
                    'icici_bank_res' => $status,
                    'razorpay_bank_res' => $this->razorpay_payment_response_data_to_array($payment),
                    'updated_at' => now(),
                ]);
                if ($status === 'captured') {
                    $inner = Request::create('/', 'POST');
                    $this->attachSession($inner, $school->id, $row->syear, $row->student_id);
                    $receipt = $this->pay_fees($inner, $row->student_id, $row->syear, $row->sub_institute_id, $row->amount / 100, $input['razorpay_payment_id']);
                    // pay_fees answers status_code 0 (it does not throw) when it books nothing, e.g. the school's receipt book
                    // has no entry for the fee head. Roll the PS mark back so the payment stays pending for reconciliation.
                    if (! is_array($receipt) || (isset($receipt['status_code']) && (int) $receipt['status_code'] === 0)) {
                        throw new \RuntimeException('pay_fees booked nothing for payment ' . $row->id . ' (check the receipt book setup for the student\'s class and year).');
                    }
                }
            });
        } catch (Throwable $e) {
            return $this->fail('Your payment was received but the receipt is still being prepared. It will appear shortly - please do not pay again.', 202, $e);
        }

        if ($alreadyBooked) {
            return response()->json(['status' => 1, 'data' => ['state' => 'paid', 'amount' => $pending->amount / 100, 'reference' => $input['razorpay_payment_id'], 'receiptHtml' => null, 'receiptNo' => null]]);
        }
        if ($status !== 'captured') {
            return response()->json(['status' => 1, 'data' => ['state' => 'pending', 'amount' => $pending->amount / 100, 'reference' => $input['razorpay_payment_id']]]);
        }
        if (! is_array($receipt) || (isset($receipt['status_code']) && (int) $receipt['status_code'] === 0)) {
            return $this->fail('Your payment was received but the receipt is still being prepared. It will appear shortly - please do not pay again.', 202);
        }

        return response()->json(['status' => 1, 'data' => [
            'state' => 'paid',
            'amount' => $pending->amount / 100,
            'reference' => $input['razorpay_payment_id'],
            'receiptNo' => (string) ($receipt['receipt_id_html'] ?? ''),
            'receiptHtml' => (string) ($receipt['data'] ?? ''),
        ]]);
    }

    // ---------------------------------------------------------------------------------------------

    /** School from the link (ShortCode) and the gateway it has switched on; 404 when either is missing. */
    private function resolveSchool(string $code): object
    {
        $rows = DB::table('school_setup')
            ->whereRaw('LOWER(ShortCode) = ?', [mb_strtolower(trim($code))])
            ->orderByDesc('syear')
            ->get(['Id', 'SchoolName', 'ShortCode']);
        // A link may also carry the school's numeric id (e.g. /pay/1) when it has no short code of its own.
        if ($rows->isEmpty() && ctype_digit(trim($code))) {
            $rows = DB::table('school_setup')->where('Id', (int) $code)->orderByDesc('syear')->get(['Id', 'SchoolName', 'ShortCode']);
        }
        // A short code shared by two institutes cannot name one school safely.
        if ($rows->isEmpty() || $rows->pluck('Id')->unique()->count() > 1) {
            abort($this->fail('This payment link is not valid. Please ask your school for a new one.', 404));
        }

        $mapping = DB::table('fees_online_maping')->where('sub_institute_id', $rows[0]->Id)->orderByDesc('syear')->first();
        $gateway = $mapping ? $this->normalizeGateway((string) $mapping->bank_name) : '';
        if (! in_array($gateway, self::GATEWAYS, true)) {
            abort($this->fail('Online fee payment is not available for this school yet.', 404));
        }

        return (object) [
            'id' => $rows[0]->Id,
            'name' => (string) $rows[0]->SchoolName,
            'code' => (string) $rows[0]->ShortCode,
            'gateway' => $gateway,
            'feesType' => (string) $mapping->fees_type,
        ];
    }

    private function normalizeGateway(string $bankName): string
    {
        $key = str_replace(' ', '_', mb_strtolower(trim($bankName)));

        return $key === 'hdfc_razorpay' ? 'hdfcrazorpay' : $key;
    }

    private function mobileFrom(Request $request): string
    {
        $request->validate(['mobile' => ['required', 'string', 'max:20']]);
        $digits = preg_replace('/\D+/', '', (string) $request->input('mobile'));
        if (strlen($digits) < 10) {
            abort($this->fail('Please enter the 10-digit mobile number registered with the school.', 422));
        }

        return substr($digits, -10);
    }

    /** The student id from the request, only if the child is at this school and carries the typed mobile. */
    private function verifiedStudentId(Request $request, object $school): string
    {
        $mobile = $this->mobileFrom($request);
        $request->validate(['student_id' => ['required', 'integer']]);

        $found = DB::table('tblstudent')
            ->where('id', $request->input('student_id'))
            ->where('sub_institute_id', $school->id)
            ->whereRaw($this->mobileSql('mobile'), [$mobile])
            ->value('id');
        if (! $found) {
            abort($this->fail('We could not find this student for that mobile number.', 404));
        }

        return (string) $found;
    }

    /** Attach the in-memory legacy session (never persisted) that get_fees/pay_fees/handlers read. */
    private function attachSession(Request $request, $schoolId, $syear, $studentId): void
    {
        $store = app('session.store');
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $schoolId);
        $store->put('syear', $syear);
        $store->put('student_id', $studentId);
    }

    /**
     * What the parent can pay now. When an older year still has dues the legacy code returns that
     * year's data with an error flag; we walk back to the oldest year with dues so the parent
     * clears it first, as the legacy year dropdown forced them to.
     */
    private function payableFees(string $studentId, object $school): array
    {
        $year = null;
        $latest = null;
        $data = [];
        for ($i = 0; $i < 6; $i++) {
            $inner = Request::create('/', 'POST', array_filter(['student_id' => $studentId, 'syear' => $year]));
            $this->attachSession($inner, $school->id, $year, $studentId);
            $data = $this->get_fees($inner);
            $latest ??= max(array_keys($data['dd_arr'] ?? [(int) $data['cur_year'] => 1]));
            if (($data['error'] ?? '') === '') {
                break;
            }
            $year = (int) $data['cur_year'] - 1;
        }
        if (($data['error'] ?? '') !== '') {
            throw new \RuntimeException('Earlier-year fees are pending for this student. Please contact the school office.');
        }

        $months = [];
        foreach (($data['total_fees'] ?? []) as $row) {
            if (($row['month'] ?? '') === 'Total') {
                continue;
            }
            $months[] = [
                'id' => (string) ($row['month_id'] ?? ''),
                'label' => (string) $row['month'],
                'fee' => $this->num($row['bk'] ?? 0),
                'paid' => $this->num($row['paid'] ?? 0),
                'due' => $this->num($row['remain'] ?? 0),
            ];
        }
        $due = round(array_sum(array_column($months, 'due')), 2);
        $stu = $data['stu_data'] ?? [];

        return [
            'year' => (int) $data['cur_year'],
            'months' => $months,
            'due' => $due,
            'feesType' => (string) $data['fees_type'],
            'monthIds' => collect($months)->where('due', '>', 0)->pluck('id', 'id')->all(),
            'raw' => $data,
            'view' => [
                'student' => [
                    'id' => $studentId,
                    'name' => (string) ($stu['name'] ?? ''),
                    'class' => (string) ($stu['stddiv'] ?? ''),
                    'uniqueId' => (string) ($stu['uniqueid'] ?? ''),
                ],
                'year' => (int) $data['cur_year'],
                'clearingEarlierYear' => (int) $data['cur_year'] < (int) $latest,
                'feesType' => (string) $data['fees_type'],
                'fixed' => $data['fees_type'] === 'fix',
                'due' => $due,
                'months' => $months,
                'gateway' => $school->gateway,
            ],
        ];
    }

    /**
     * The amount the parent may pay, recomputed here. pay_fees spreads the amount over the months in
     * order, so a fixed-fee school accepts only "the first N unpaid months in full"; others accept
     * any amount up to what is due.
     */
    private function validatedAmount(Request $request, array $fees): float
    {
        $amount = round((float) $request->input('amount'), 2);
        if ($fees['due'] <= 0) {
            throw new \RuntimeException('There are no pending fees for this student.');
        }
        if ($amount <= 0 || $amount > $fees['due'] + 0.001) {
            throw new \InvalidArgumentException('Please enter an amount between ₹1 and the pending fees.');
        }
        if ($fees['feesType'] === 'fix') {
            $running = 0.0;
            $ok = false;
            foreach ($fees['months'] as $month) {
                if ($month['due'] <= 0) {
                    continue;
                }
                $running = round($running + $month['due'], 2);
                if (abs($running - $amount) < 0.01) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                throw new \InvalidArgumentException('Please choose the months to pay; this school collects whole months only.');
            }
        }

        return $amount;
    }

    private function razorpayCredentials($schoolId, $studentId): ?object
    {
        $medium = DB::table('tblstudent_enrollment as e')
            ->join('academic_section as a', 'a.id', '=', 'e.grade_id')
            ->where('e.student_id', $studentId)
            ->orderByDesc('e.syear')
            ->value('a.medium');

        return DB::table('fees_razorpay')
            ->where('sub_institute_id', $schoolId)
            ->when($medium, fn ($q) => $q->where('medium', $medium))
            ->first();
    }

    private function createRazorpayOrder(Request $request, object $school): array
    {
        $studentId = $this->verifiedStudentId($request, $school);
        $fees = $this->payableFees($studentId, $school);
        $amount = $this->validatedAmount($request, $fees);

        $credentials = $this->razorpayCredentials($school->id, $studentId);
        if (! $credentials) {
            throw new \RuntimeException('Online payment is not configured for this school.');
        }

        $order = (new Api($credentials->key_id, $credentials->key_secret))->order->create([
            'receipt' => 'order_' . uniqid(),
            'amount' => (int) round($amount * 100),
            'currency' => 'INR',
            'payment_capture' => 1,
        ]);

        $paymentRef = DB::table('fees_payment')->insertGetId([
            'student_id' => $studentId,
            'syear' => $fees['year'],
            'amount' => (int) round($amount * 100),
            'razorpay_order_id' => $order['id'],
            'razorpay_payment_status' => 'PR',
            'razorpay_payment_date' => now(),
            'sub_institute_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        FeeAuditService::logOrderCreated('razorpay', $order['id'], $studentId, $school->id, $amount);

        return [
            'orderId' => (string) $order['id'],
            'keyId' => (string) $credentials->key_id,
            'paymentRef' => (string) $paymentRef,
            'amount' => $amount,
            'studentName' => $fees['view']['student']['name'],
            'schoolName' => $school->name,
        ];
    }

    /** Compare on the last 10 digits so "+91 98765-43210" and "9876543210" are the same number. */
    private function mobileSql(string $column): string
    {
        return "RIGHT(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '+', ''), 10) = ?";
    }

    private function num($value): float
    {
        return (float) str_replace(',', '', (string) $value);
    }

    private function fail(string $message, int $status, ?Throwable $e = null)
    {
        if ($e) {
            Log::warning('Public fee pay: ' . $message, ['exception' => $e]);
        }

        return response()->json(['status' => 0, 'message' => $message], $status);
    }

    private function plainError(string $message): string
    {
        return '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<body style="font-family:system-ui;max-width:28rem;margin:4rem auto;padding:0 1rem;color:#0f172a">'
            . '<h1 style="font-size:1.25rem">We could not start your payment</h1><p>' . e($message) . '</p>'
            . '<p><a href="javascript:history.back()">Go back</a></p></body>';
    }
}
