<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\SearchStudent;
use function App\Helpers\getStudents;
use function App\Helpers\getTableFieldFromId;
use App\Http\Controllers\student\tblstudentController;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use Illuminate\Support\Facades\Http;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;

class conversationalAIController extends Controller
{
       use GetsJwtToken;
    //
    
    public $borderColors = [
        '#0d6efd',
        '#f7495aff',
        '#24bd76ff',
        '#e9b005ff',
        '#6f42c1',
        '#fd7e14',
        '#20c997',
        '#e83e8c',
        '#0dcaf0',
        '#d63384'
    ];
    public function genkitDetailsAPI(Request $request, JwtToken $jwt)
    {
        // resolve session
        $sub = in_array($request->type, ['API', 'JSON'])
            ? $request->sub_institute_id
            : session('sub_institute_id');
        $year = in_array($request->type, ['API', 'JSON'])
            ? $request->syear
            : session('syear');
        $term = in_array($request->type, ['API', 'JSON'])
            ? $request->term_id
            : session('term_id');
        $student_id = DB::table('tblstudent as s')->join('tblstudent_enrollment as ts', 'ts.student_id', '=', 's.id')->where(['s.sub_institute_id' => $sub, 'ts.syear' => $year])->where('s.enrollment_no', $request->enrollment_no)->whereNull('ts.end_date')->pluck('s.id');
        // return $student_id;

        $stu_arr = $student_id->toArray();
        // find students (multiple possible)
        $students = SearchStudent("", "", "", $sub, $year, "",  "", "", "", "", $stu_arr, "", 1);
        // return $students;
        if (empty($students)) {
            return response()->json([
                'html' => '<div class="text-muted p-2">Failed to find student please check enrollment no.</div>',
                'raw_data' => 'No data Found'
            ]);
        }

        // Process each student and collect HTML
        $allHtml = '';
        $allData = [];
        $studentCounter = 1;
        $totalStudents = count($students);
        // return $students[0]['id'];
        foreach ($students as $key => $student) {
            // Add separator between multiple students
            if ($totalStudents > 1) {
                $allHtml .= '<div class="student-separator" style="margin-bottom: 20px;">';
                $allHtml .= '<div class="alert alert-info" style="border-radius: 12px; margin-bottom: 15px;">';
                $allHtml .= '<strong>Student ' . $studentCounter . ' of ' . $totalStudents . '</strong>';
                $allHtml .= '</div>';
            }

            if ($request->action_type === 'remain_fees' || $request->action_type === 'paid_fees' || $request->action_type === 'total_fees') {
                // Call the new API endpoint for fees details
                $apiUrl = url("/studentFeesDetailAPI");
                $feesRequest = request()->duplicate(
                    array_merge(
                        request()->except('type'),
                        [
                            'syear' => $year,
                            'sub_institute_id' => $sub,
                            'student_id' => $student['id'],
                        ]
                    )
                );
                // return $requestFees;
                try {
                    $feesController = new fees_collect_controller;
                    $response = $feesController->studentFeesDetailAPI($feesRequest);
                    $apiResponse = json_decode($response, true);
                    // return $apiResponse;
                    // return $apiResponse;
                    if (isset($apiResponse['status']) && $apiResponse['status'] == 1 && isset($apiResponse['data'])) {
                        $data = $apiResponse['data'];
                        // Get student details from STU_DATA
                        $studentDetail = $data['STU_DATA'] ?? [];

                        if ($request->action_type === 'remain_fees') {
                            $pendingFees = $data['PENDING'] ?? [];
                            $html = $this->generateUnpaidFeesHTML($pendingFees, $studentDetail);
                        } elseif ($request->action_type === 'paid_fees') {
                            $paidFees = $data['PAID'] ?? [];
                            $filteredFees = array_filter($paidFees, function ($item) use ($year) {
                                return $item['syear'] == $year;
                            });
                            $html = $this->generatePaidFeesHTML($filteredFees, $studentDetail, $year);
                        } elseif ($request->action_type === 'total_fees') {
                            // Display both unpaid and paid fees
                            $pendingFees = $data['PENDING'] ?? [];
                            $paidFees = $data['PAID'] ?? [];
                            $filteredFees = array_filter($paidFees, function ($item) use ($year) {
                                return $item['syear'] == $year;
                            });
                            $html = $this->generateUnpaidFeesHTML($pendingFees, $studentDetail);
                            $html .= $this->generatePaidFeesHTML($filteredFees, $studentDetail, $year);
                        }
                        $allData[] = $data;
                    } else {
                        $html = '<div class="text-muted p-2">No records found for enrollment number: ' . htmlspecialchars($request->enrollment_no) . '</div>';
                        $allData[] = ['error' => 'No data found in API response'];
                    }
                } catch (\Exception $e) {
                    $html = '<div class="text-muted p-2">Error fetching data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $allData[] = ['error' => $e->getMessage()];
                }
            } else if ($request->action_type === 'attendance') {
                $getAttendance = getStudents([$student['id']], $sub, $year);
                // return $getAttendance;
                $html = $this->generateAttendanceHTML($getAttendance, $student['id']);
                $allData[] = $getAttendance;
            } else if ($request->action_type === 'results') {
                $getStudentData = getStudents([$student['id']], $sub, $year);
                $html = $this->generateResultsHTML($getStudentData, $student['id'], $sub, $year, $term, $jwt);
                // return $html;
                $allData[] = $getStudentData;
            } else {
                // call external API with student_id
                $url  = "https://kgenkit.vercel.app/api/genkit-k12?enrollment_no={$request->enrollment_no}&action={$request->action_type}&syear={$year}&sub_institute_id={$sub}&student_id={$student['id']}";

                try {
                    $response = (new \GuzzleHttp\Client)->get($url);
                    $data = json_decode($response->getBody(), true);

                    if (empty($data) || !is_array($data)) {
                        $html = '<div class="text-muted p-2">No records found for enrollment number: ' . htmlspecialchars($request->enrollment_no) . '</div>';
                    } else {
                        // pick formatter
                        $html = $this->formatDetailsHTML($request->action_type, $data);
                    }
                    $allData[] = $data;
                } catch (\Exception $e) {
                    $html = '<div class="text-muted p-2">Error fetching data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $allData[] = ['error' => $e->getMessage()];
                }
            }

            $allHtml .= $html;

            if ($totalStudents > 1) {
                $allHtml .= '</div>';
                // Add a divider between students
                if ($studentCounter < $totalStudents) {
                    $allHtml .= '<hr style="margin: 20px 0; border-top: 2px dashed #dee2e6;">';
                }
            }

            $studentCounter++;
        }
        // return $allData;
        return response()->json(['action_type' => $request->action_type, 'raw_data' => $allData, 'html' => $allHtml]);
    }

    /**
     * Transform the fees API response to match expected format
     */
    private function transformFeesAPIResponse($data, $actionType)
    {
        $transformed = [];

        if (isset($data['STU_DATA'])) {
            $stuData = $data['STU_DATA'];
            $transformed['student_detail'] = [
                'id' => $stuData['student_id'] ?? '',
                'enrollment_no' => $stuData['enrollment'] ?? '',
                'first_name' => $stuData['first_name'] ?? '',
                'middle_name' => $stuData['middle_name'] ?? '',
                'last_name' => $stuData['last_name'] ?? '',
                'mobile' => $stuData['mobile'] ?? '',
                'email' => $stuData['email'] ?? '',
                'standard_name' => $stuData['stddiv'] ?? '',
                'division_name' => '',
                'class' => $stuData['stddiv'] ?? '',
                'admission_status' => $stuData['end_date'] ?? 'Active'
            ];
        }

        if ($actionType === 'remain_fees' && isset($data['PENDING'])) {
            $transformed['unpaid_fees'] = [];
            foreach ($data['PENDING'] as $pending) {
                $transformed['unpaid_fees'][] = [
                    'month' => $pending['month'] ?? '',
                    'remain' => $pending['remain'] ?? 0,
                    'discount' => $pending['discount'] ?? 0,
                    'bk' => ($pending['remain'] ?? 0) + ($pending['discount'] ?? 0),
                    'paid' => 0
                ];
            }
        }

        if ($actionType === 'paid_fees' && isset($data['PAID'])) {
            $transformed['paid_fees'] = [];
            foreach ($data['PAID'] as $paid) {
                $transformed['paid_fees'][] = [
                    'month' => $paid['month_id'] ?? '',
                    'receipt_no' => $paid['receipt_no'] ?? '',
                    'receiptdate' => $paid['receiptdate'] ?? '',
                    'payment_mode' => $paid['payment_mode'] ?? '',
                    'amount' => $paid['paid_amount'] ?? 0,
                    'fees_html' => $paid['fees_html'] ?? ''
                ];
            }
        }

        return $transformed;
    }

    /**
     * Common HTML formatting function for all detail types
     */
    private function formatDetailsHTML($actionType, $data, $extra = [], $syear = '')
    {
        switch ($actionType) {
            case 'fees_details':
                return $this->generateFeesHTML($data);
            case 'admission_details':
                return $this->generateAdmissionHTML($data);
            case 'remain_fees':
                return $this->generateUnpaidFeesHTML($extra['unpaid_fees'] ?? [], $extra['student_detail'] ?? []);
            case 'paid_fees':
                $filteredFees = array_filter($extra['paid_fees'], function ($item) use ($syear) {
                    return $item['syear'] == $syear;
                });
                return $this->generatePaidFeesHTML($filteredFees, $extra['student_detail'] ?? [], $syear);
            default:
                return $this->generateStudentHTML($data);
        }
    }

    private function generateStudentHTML($data)
    {
        if (empty($data) || !isset($data[0])) {
            return '<div class="text-muted p-2">No records found.</div>';
        }

        $student = $data[0];
        $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        $classRow = isset($student['class']) ? "
        <div class=\"detail-item\">
            <span class=\"detail-label\">Class</span>
            <span class=\"detail-value\">{$student['class']}</span>
        </div>" : '';

        return $this->getCardWrapper("Student Details", "fa-user-graduate", '
            <div class="detail-item">
                <span class="detail-label">Enrollment No.</span>
                <span class="detail-value fw-bold">' . ($student['enrollment_no'] ?? 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Full Name</span>
                <span class="detail-value">' . ($fullName ?: 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">' . ($student['email'] ?? 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Mobile</span>
                <span class="detail-value">' . ($student['mobile'] ?? 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Standard</span>
                <span class="detail-value">' . (isset($student['standard_name']) ? $student['standard_name'] : 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Division</span>
                <span class="detail-value">' . ($student['division_name'] ?? 'N/A') . '</span>
            </div>
            ' . $classRow . '
        ');
    }

    private function generateFeesHTML($data)
    {
        if (empty($data) || !isset($data['student_details'][0])) {
            return '<div class="text-muted p-2">No records found.</div>';
        }

        $student = $data['student_details'][0];
        $fees = $data['student_details'][0]['fees_details'] ?? [];
        $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));

        // Calculate totals
        $totalAmount = 0;
        foreach ($fees as $item) {
            $totalAmount += (float)($item['amount'] ?? 0);
        }

        $feesHtml = '<div style="display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0;">';
        foreach ($fees as $item) {
            $paymentMode = $item['payment_mode'] ?? 'N/A';
            $amount = (float)($item['amount'] ?? 0);
            $receiptNo = $item['receipt_no'] ?? 'N/A';
            $receiptDate = isset($item['receiptdate']) ? date('d-m-Y', strtotime($item['receiptdate'])) : 'N/A';

            $feesHtml .= '
            <div style="
                flex: 1;
                min-width: 250px;
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                border-radius: 16px;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                cursor: pointer;
                border: 1px solid #e9ecef;
            " onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 8px 24px rgba(0,0,0,0.12)\';" 
             onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.05)\';">
                
                <!-- Payment Mode Header -->
                <div style="
                    text-align: center;
                    margin-bottom: 16px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #e9ecef;
                ">
                    <div style="
                        font-size: 1rem;
                        font-weight: 700;
                        color: #1a1a2e;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">' . htmlspecialchars($paymentMode) . '</div>
                </div>
                
                <!-- Amount Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Paid Amount</div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: #28a745;">₹' . number_format($amount, 2) . '</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Receipt No.</div>
                        <div style="font-size: 1rem; font-weight: 600; color: #17a2b8;">' . htmlspecialchars($receiptNo) . '</div>
                    </div>
                </div>
                
                <!-- Payment Date -->
                <div style="
                    text-align: center;
                    padding-top: 12px;
                    border-top: 1px solid #e9ecef;
                    margin-top: 8px;
                ">
                    <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Payment Date</div>
                    <div style="font-size: 0.85rem; font-weight: 500; color: #6f42c1;">' . $receiptDate . '</div>
                </div>
            </div>
        ';
        }
        $feesHtml .= '</div>';

        // Totals summary
        $totalsHtml = '
        <div style="
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin: 20px 0 24px 0;
            padding: 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 20px;
            border: 1px solid #e9ecef;
        ">
            <div style="text-align: center; padding: 12px;">
                <div style="
                    font-size: 0.75rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">Total Fees Paid</div>
                <div style="
                    font-size: 0.45rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">(Fine and Discount Included)</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #28a745;
                ">₹' . number_format($totalAmount, 2) . '</div>
            </div>
            <div style="text-align: center; padding: 12px;">
                <div style="
                    font-size: 0.75rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">Total Transactions</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #0d6efd;
                ">' . count($fees) . '</div>
            </div>
        </div>
    ';

        return $this->getCardWrapper("Fees Details", "fa-money-bill-wave", '
        <div class="detail-item">
            <span class="detail-label">Student Name</span>
            <span class="detail-value fw-bold">' . ($fullName ?: 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Enrollment No.</span>
            <span class="detail-value">' . ($student['enrollment_no'] ?? 'N/A') . '</span>
        </div>
        
        <!-- Totals Summary -->
        ' . $totalsHtml . '
        
        <!-- Modern Fees Cards -->
        ' . $feesHtml . '
    ');
    }

    private function generateAdmissionHTML($data)
    {
        if (empty($data) || !isset($data[0])) {
            return '<div class="text-muted p-2">No records found.</div>';
        }

        $student = $data[0];
        $fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        $status = $student['admission_status'] ?? 'N/A';
        $statusClass = strtolower($status) === 'active' ? 'status-active' : 'status-pending';

        return $this->getCardWrapper("Admission Details", "fa-file-alt", '
            <div class="detail-item">
                <span class="detail-label">Student Name</span>
                <span class="detail-value fw-bold">' . ($fullName ?: 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Mobile</span>
                <span class="detail-value">' . ($student['mobile'] ?? 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">' . ($student['email'] ?? 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Admission Enquiry No.</span>
                <span class="detail-value">' . (isset($student['admission_registration']['enquiry_no']) ? $student['admission_registration']['enquiry_no'] : 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Admission Date</span>
                <span class="detail-value">' . (isset($student['admission_registration']['admission_date']) ? date('d-m-Y', strtotime($student['admission_registration']['admission_date'])) : 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Admission Standard</span>
                <span class="detail-value">' . ($student['admission_standard_name'] ?? 'N/A') . '</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Current Standard / Division</span>
                <span class="detail-value">' . ($student['standard_name'] ?? 'N/A') . ' / ' . ($student['division_name'] ?? 'N/A') . '</span>
            </div>
        ');
    }

    /**
     * Generate HTML for remaining/unpaid fees using STU_DATA and PENDING array
     */
    private function generateUnpaidFeesHTML($pendingFees, $studentDetail)
    {
        if (empty($pendingFees)) {
            return '<div class="text-muted p-2">No pending fees records found.</div>';
        }

        $studentName = trim(($studentDetail['first_name'] ?? '') . ' ' . ($studentDetail['middle_name'] ?? '') . ' ' . ($studentDetail['last_name'] ?? ''));

        // Calculate totals from pending fees
        $totalRemain = 0;
        $totalDiscount = 0;
        foreach ($pendingFees as $val) {
            $totalRemain += (float)($val['remain'] ?? 0);
            $totalDiscount += (float)($val['discount'] ?? 0);
        }
        $totalBk = $totalRemain + $totalDiscount;

        // Modern fees cards for pending fees
        $feesHtml = '<div style="display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0;">';
        foreach ($pendingFees as $val) {
            $monthName = $val['month'] ?? 'N/A';
            $remain = (float)($val['remain'] ?? 0);
            $discount = (float)($val['discount'] ?? 0);
            $total = $remain + $discount;

            $feesHtml .= '
            <div style="
                flex: 1;
                min-width: 200px;
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                border-radius: 16px;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                cursor: pointer;
                border: 1px solid #e9ecef;
            " onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 8px 24px rgba(0,0,0,0.12)\';" 
             onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.05)\';">
                
                <!-- Month Header -->
                <div style="
                    text-align: center;
                    margin-bottom: 16px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #e9ecef;
                ">
                    <div style="
                        font-size: 1rem;
                        font-weight: 700;
                        color: #1a1a2e;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">' . htmlspecialchars($monthName) . '</div>
                </div>
                
                <!-- Amount Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Total Amount</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #2c3e50;">₹' . number_format($total, 2) . '</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Remaining</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #ffc107;">₹' . number_format($remain, 2) . '</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Discount</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #17a2b8;">₹' . number_format($discount, 2) . '</div>
                    </div>
                </div>
                
            </div>
        ';
        }
        $feesHtml .= '</div>';

        // Modern totals summary cards
        $totalsHtml = '
        <div style="
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin: 20px 0 24px 0;
            padding: 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 20px;
            border: 1px solid #e9ecef;
        ">
            <div style="text-align: center; padding: 12px;">
                <div style="
                    font-size: 0.75rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">Total Fees</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #2c3e50;
                ">₹' . number_format($totalBk, 2) . '</div>
            </div>
            <div style="text-align: center; padding: 12px; border-left: 1px solid #e9ecef; border-right: 1px solid #e9ecef;">
                <div style="
                    font-size: 0.75rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">Total Remaining</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #ffc107;
                ">₹' . number_format($totalRemain, 2) . '</div>
            </div>
            <div style="text-align: center; padding: 12px;">
                <div style="
                    font-size: 0.75rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">Total Discount</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #17a2b8;
                ">₹' . number_format($totalDiscount, 2) . '</div>
            </div>
        </div>
    ';

        return $this->getCardWrapper("Remaining Fees Details", "fa-file-invoice-dollar", '
        <div class="detail-item">
            <span class="detail-label">Student Name : </span>
            <span class="detail-value fw-bold">' . ($studentName ?: 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Enrollment No : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['enrollment'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Class : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['stddiv'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Mobile : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['mobile'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['email'] ?? 'N/A') . '</span>
        </div>
        
        <!-- Totals Summary between student details and fees cards -->
        ' . $totalsHtml . '
        
        <!-- Modern Fees Cards for Pending Fees -->
        ' . $feesHtml . '
    ');
    }

    /**
     * Generate HTML for paid fees using STU_DATA and PAID array
     */
    private function generatePaidFeesHTML($paidFees, $studentDetail, $syear)
    {
        if (empty($paidFees)) {
            return '<div class="text-muted p-2">No paid fees records found.</div>';
        }

        $studentName = trim(($studentDetail['first_name'] ?? '') . ' ' . ($studentDetail['middle_name'] ?? '') . ' ' . ($studentDetail['last_name'] ?? ''));

        // Calculate total paid amount
        $totalPaidAmount = 0;
        foreach ($paidFees as $val) {
            $totalPaidAmount += (float)($val['paid_amount'] ?? 0);
        }

        // Modern fees cards with flex-wrap for paid fees
        $feesHtml = '<div style="display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0;">';
        foreach ($paidFees as $val) {
            $paymentMode = $val['payment_mode'] ?? 'N/A';
            $amount = (float)($val['paid_amount'] ?? 0);
            $receiptNo = $val['receipt_no'] ?? 'N/A';
            $receiptDate = isset($val['receiptdate']) ? date('d-m-Y', strtotime($val['receiptdate'])) : 'N/A';
            $chequeNo = $val['cheque_no'] ?? '';
            $bankName = $val['cheque_bank_name'] ?? '';

            $paymentDetails = $paymentMode;
            if ($chequeNo) {
                $paymentDetails .= ' - ' . $chequeNo;
            }
            if ($bankName) {
                $paymentDetails .= ' (' . $bankName . ')';
            }

            $feesHtml .= '
            <div style="
                flex: 1;
                min-width: 250px;
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                border-radius: 16px;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                cursor: pointer;
                border: 1px solid #e9ecef;
            " onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 8px 24px rgba(0,0,0,0.12)\';" 
             onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 12px rgba(0,0,0,0.05)\';">
                
                <!-- Payment Mode Header -->
                <div style="
                    text-align: center;
                    margin-bottom: 16px;
                    padding-bottom: 12px;
                    border-bottom: 2px solid #e9ecef;
                ">
                    <div style="
                        font-size: 0.9rem;
                        font-weight: 700;
                        color: #1a1a2e;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">' . htmlspecialchars($paymentDetails) . '</div>
                </div>
                
                <!-- Amount Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Paid Amount</div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: #28a745;">₹' . number_format($amount, 2) . '</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Receipt No.</div>
                        <div style="font-size: 1rem; font-weight: 600; color: #17a2b8;">' . htmlspecialchars($receiptNo) . '</div>
                    </div>
                </div>
                
                <!-- Payment Date -->
                <div style="
                    text-align: center;
                    padding-top: 12px;
                    border-top: 1px solid #e9ecef;
                    margin-top: 8px;
                ">
                    <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Payment Date</div>
                    <div style="font-size: 0.85rem; font-weight: 500; color: #6f42c1;">' . $receiptDate . '</div>
                </div>
            </div>
        ';
        }
        $feesHtml .= '</div>';

        // Totals summary
        $totalsHtml = '
        <div style="
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin: 20px 0 24px 0;
            padding: 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 20px;
            border: 1px solid #e9ecef;
        ">
            <div style="text-align: center; padding: 12px;">
                <div style="
                    font-size: 0.75rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">Total Fees Paid</div>
                <div style="
                    font-size: 0.45rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">(Fine and Discount Included)</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #28a745;
                ">₹' . number_format($totalPaidAmount, 2) . '</div>
            </div>
            <div style="text-align: center; padding: 12px;">
                <div style="
                    font-size: 0.75rem;
                    color: #6c757d;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 8px;
                ">Total Transactions</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #0d6efd;
                ">' . count($paidFees) . '</div>
            </div>
        </div>
    ';

        return $this->getCardWrapper("Paid Fees Details", "fa-money-bill-wave", '
        <div class="detail-item">
            <span class="detail-label">Student Name : </span>
            <span class="detail-value fw-bold">' . ($studentName ?: 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Enrollment No : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['enrollment'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Class : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['stddiv'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Mobile : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['mobile'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['email'] ?? 'N/A') . '</span>
        </div>
        
        <!-- Totals Summary -->
        ' . $totalsHtml . '
        
        <!-- Modern Paid Fees Cards -->
        ' . $feesHtml . '
    ');
    }

    /**
     * Common card wrapper function
     */
    private function getCardWrapper($title, $icon, $content)
    {
        return '
        <div class="student-detail-card">
            <div class="student-detail-header">
                <h6><i class="fas ' . $icon . ' me-2"></i>' . $title . '</h6>
            </div>
            <div class="student-detail-body">
                ' . $content . '
            </div>
        </div>
        ';
    }

    public function MockQueriesAPI(Request $request)
    {
        $type = $request->type;
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');
        }

        $sentence = strtolower($request->sentence);

        if (!$sentence) {
            return response()->json(['error' => 'Sentence required']);
        }

        // Check if we have a specific query type (total or list) from the request
        $mockQueryType = $request->get('mock_query_type');
        $originalSentence = $request->get('original_sentence');

        // Parse intent but respect the mock_query_type if provided
        $intent = $this->parseIntent($originalSentence ?: $sentence);

        // If we have a specific mock query type, override the action
        if ($mockQueryType === 'total') {
            $intent['action'] = 'count';
        } elseif ($mockQueryType === 'list') {
            $intent['action'] = 'list';
        }

        if (!$intent['table']) {
            return response()->json(['error' => 'Could not understand query']);
        }

        // Execute query based on intent action
        $result = $this->executeQuery($intent, $sub_institute_id, $syear);

        if (isset($result['error'])) {
            return response()->json($result);
        }

        // Get field map for the table
        $fieldMap = $this->getFieldMap($intent['table']);

        // Generate HTML based on action type and result
        $html = $this->generateMockHTML($result, $fieldMap, $intent);

        return response()->json([
            'data' => $result,
            'html' => $html,
            'intent' => $intent
        ]);
    }

    /**
     * Common HTML generation for mock queries with enhanced formatting for count vs list
     */
    private function generateMockHTML($data, $fieldMap = [], $intent = [])
    {
        // Handle count/total results
        if (isset($data['count'])) {
            $itemName = $this->getItemNameFromIntent($intent);
            return "
            <div class='alert alert-info text-center shadow-sm' style='border-radius: 12px; border-left: 4px solid #0d6efd; background: linear-gradient(135deg, #e3f2fd 0%, #ffffff 100%);'>
                <div style='font-size: 2rem; font-weight: 800; color: #0d6efd; margin-bottom: 8px;'>{$data['count']}</div>
                <div style='font-size: 0.9rem; color: #1e293b;'>Total {$itemName}</div>
            </div>";
        }

        // Handle empty results
        if (empty($data) || count($data) == 0) {
            return "<div class='alert alert-warning text-center shadow-sm' style='border-radius: 12px;'><strong>No data found</strong></div>";
        }

        // For list views, generate beautiful cards


        $html = "<div style='display: flex; flex-direction: column; align-items: center; gap: 0;'>";
        $index = 0;

        // Add header with count
        $itemName = $this->getItemNameFromIntent($intent);
        $totalCount = count($data);
        $html .= "
        <div style='width: 95%; margin-bottom: 16px; padding: 8px 16px; background: #f8f9fa; border-radius: 10px; text-align: center;'>
            <span style='font-size: 0.85rem; color: #6c757d;'>Showing {$totalCount} {$itemName}</span>
        </div>";

        foreach ($data as $key => $row) {
            $row = (array)$row;
            $borderColor = $this->borderColors[$key % count($this->borderColors)];

            $html .= "
            <div class='modern-card' style='
                width: 95%;
                margin-bottom: 16px;
                border-radius: 12px;
                background: #ffffff;
                border-left: 5px solid {$borderColor};
                border-right: 1px solid #e9ecef;
                border-top: 1px solid #e9ecef;
                border-bottom: 1px solid #e9ecef;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                transition: all 0.2s ease;
                overflow: hidden;
            ' onmouseover='this.style.boxShadow=\"0 4px 12px rgba(0,0,0,0.1)\"; this.style.transform=\"translateY(-2px)\";' 
             onmouseout='this.style.boxShadow=\"0 1px 3px rgba(0,0,0,0.05)\"; this.style.transform=\"translateY(0)\";'>
                <div class='card-body' style='padding: 14px 18px;'>
            ";

            if (!empty($fieldMap)) {
                foreach ($fieldMap as $label => $column) {
                    if (array_key_exists($column, $row)) {
                        $value = e($row[$column]);
                        $html .= "
                        <div style='margin-bottom: 10px; display: flex; align-items: flex-start; gap: 12px; font-size: 0.85rem;'>
                            <span style='color: #475569; font-weight: 600; min-width: 120px; font-size: 0.85rem;'>{$label}:</span>
                            <span style='color: #1e293b; flex: 1; word-break: break-word;'>{$value}</span>
                        </div>";
                    }
                }
            } else {
                foreach ($row as $key => $value) {
                    $formattedKey = ucfirst(str_replace('_', ' ', $key));
                    $value = e($value);
                    $html .= "
                    <div style='margin-bottom: 10px; display: flex; align-items: flex-start; gap: 12px; font-size: 0.85rem;'>
                        <span style='color: {$borderColor}; font-weight: 600; min-width: 120px; font-size: 0.85rem;'>{$formattedKey}:</span>
                        <span style='color: #1e293b; flex: 1; word-break: break-word;'>{$value}</span>
                    </div>";
                }
            }

            $html .= "
                </div>
            </div>";
            $index++;
        }

        $html .= "</div>";
        return $html;
    }

    public function generateAttendanceHTML($studentData, $student_id)
    {
        if (empty($studentData) || count($studentData) == 0) {
            return "<div class='alert alert-warning text-center shadow-sm' style='border-radius: 12px;'><strong>No data found</strong></div>";
        }

        $html = $htmlAttendance = '';
        foreach ($studentData as $key => $student) {
            $borderColor = $this->borderColors[$key % count($this->borderColors)];
            $working_present = $student['total_working_days_present_system'] ?? '';
            $working_total = $student['total_working_days_system'] ?? '';
            $html = $this->getCardWrapper("Attendance Details", "fa-money-bill-wave", '
              <div class="detail-item">
            <span class="detail-value fw-bold">Attendance based on system :</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Student Name : </span>
            <span class="detail-value fw-bold">' . ($student['student_full_name'] ?: 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Enrollment No : </span>
            <span class="detail-value fw-bold">' . ($student['enrollment_no'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Class : </span>
            <span class="detail-value fw-bold">' . ($student['standard_name'] ?? 'N/A') . '/' . ($student['division_name'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Mobile : </span>
            <span class="detail-value fw-bold">' . ($student['mobile'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email : </span>
            <span class="detail-value fw-bold">' . ($student['email'] ?? 'N/A') . '</span>
        </div>
        
        <div class="detail-item">
            <span class="detail-label">Total Working Days : </span>
            <span class="detail-value fw-bold">' . ($working_total) . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Total Present Days : </span>
            <span class="detail-value fw-bold">' . ($working_present) . '</span>
        </div>
    ');
        }
        return $html;
    }
    public function generateResultsHTML($studentData, $student_id, $sub,$year,$term, $jwt)
    {
        if (empty($studentData) || count($studentData) == 0) {
            return "<div class='alert alert-warning text-center shadow-sm' style='border-radius: 12px;'><strong>No data found</strong></div>";
        }
        $token = $jwt->createToken([
            'student_id' => $student_id,
            'sub'        => $sub,
            'year'       => $year,
            'term'       => $term,
        ]);
         $getStudentResultPDF = '';
         $postData = [
            'sub_institute_id' => $sub,
            'syear' => $year,
            'student_id' => $student_id,
            'term_id' => $term,
            'type'=>"API",
            'token'=>$token,
           // "token"=>"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdHVkZW50X2lkIjo5NzM4Miwic3ViX2luc3RpdHV0ZV9pZCI6MX0.gvRa2kggWK5F1J-qYxZdFWhdRx8ZIqzlzT7pwmlWDAM"
        ];
        
        $payload = json_encode($postData);
        $resultsCurl = curl_init("https://erp.triz.co.in/studentResultPDFAPI");
        curl_setopt($resultsCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($resultsCurl, CURLINFO_HEADER_OUT, true);
        curl_setopt($resultsCurl, CURLOPT_POST, true);
        curl_setopt($resultsCurl, CURLOPT_POSTFIELDS, $payload);
      
        curl_setopt($resultsCurl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
            ]
        );
        $getResultsResult = curl_exec($resultsCurl);
        // Submit the POST request
         $resultsResult = json_decode($getResultsResult);
          curl_close($resultsCurl);
        // decode json result
        // return $resultsResult;
        
        $getStudentResultPDF.="<span class='detail-value text-red'>No Result Found</span>";
            
        if(!empty($resultsResult) && !empty($resultsResult->data)&& $resultsResult->status == 1){
            foreach($resultsResult->data as $k=>$v){
                $getStudentResultPDF .= "<span class='detail-value fw-bold'>$v->result_type : <a href='".$v->pdf_link."' target='_blank'>Download PDF</a></span><br>";
            }
        }
        $html = $htmlAttendance = '';
        foreach ($studentData as $key => $student) {
            $borderColor = $this->borderColors[$key % count($this->borderColors)];
            $html = $this->getCardWrapper("Results Details", "fa-money-bill-wave", '
        <div class="detail-item">
            <span class="detail-label">Student Name : </span>
            <span class="detail-value fw-bold">' . ($student['student_full_name'] ?: 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Enrollment No : </span>
            <span class="detail-value fw-bold">' . ($student['enrollment_no'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Class : </span>
            <span class="detail-value fw-bold">' . ($student['standard_name'] ?? 'N/A') . '/' . ($student['division_name'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Mobile : </span>
            <span class="detail-value fw-bold">' . ($student['mobile'] ?? 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email : </span>
            <span class="detail-value fw-bold">' . ($student['email'] ?? 'N/A') . '</span>
        </div>'.$getStudentResultPDF);
        }
        return $html;
    }
    /**
     * Get friendly item name from intent for display
     */
    private function getItemNameFromIntent($intent)
    {
        $tableNames = [
            'academic_section' => 'Academic Sections',
            'standard' => 'Standards',
            'division' => 'Divisions',
            'tblstudent' => 'Students',
            'sub_std_map' => 'Subjects',
            'batch' => 'Batches',
            'announcement' => 'Announcements',
            'announcements' => 'Announcements',
            'chapter_master' => 'Chapters',
            'payroll_types' => 'Payroll Types',
            'period' => 'Periods',
            'question_paper' => 'Question Papers',
            'transport_driver_detail' => 'Transport Drivers',
            'transport_kilometer_rate' => 'Transport Kilometer Rates',
            'transport_route' => 'Transport Routes',
            'transport_vehicle' => 'Transport Vehicles',
            'tbluserprofilemaster' => 'User Profiles',
            'tbluser' => 'Users',
            'hrms_departments' => 'HRMS Departments',
        ];

        $table = $intent['table'] ?? '';
        return $tableNames[$table] ?? ucfirst(str_replace('_', ' ', $table));
    }

    private function parseIntent($text)
    {
        $intent = ['action' => 'list', 'table' => null, 'filters' => []];

        // Check for count/total keywords
        if (preg_match('/\b(total|count|how many|howmuch|how much)\b/i', $text)) {
            $intent['action'] = 'count';
        }

        $map = [
            'academic section' => 'academic_section',
            'grade' => 'academic_section',
            'standard' => 'standard',
            'class' => 'standard',
            'division' => 'division',
            'section' => 'division',
            'student' => 'tblstudent',
            'subject' => 'sub_std_map',
            'batch' => 'batch',
            'announcement' => 'announcement',
            'notice' => 'announcement',
            'chapter' => 'chapter_master',
            'payroll type' => 'payroll_types',
            'period' => 'period',
            'question paper' => 'question_paper',
            'driver' => 'transport_driver_detail',
            'kilometer rate' => 'transport_kilometer_rate',
            'km rate' => 'transport_kilometer_rate',
            'transport route' => 'transport_route',
            'vehicle' => 'transport_vehicle',
            'transport vehicle' => 'transport_vehicle',
            'user profile' => 'tbluserprofilemaster',
            'profile' => 'tbluserprofilemaster',
            'user' => 'tbluser',
            'teacher' => 'tbluser',
            'hrms department' => 'hrms_departments',
            'department' => 'hrms_departments',
        ];

        foreach ($map as $key => $table) {
            if (str_contains($text, $key)) {
                $intent['table'] = $table;
                break;
            }
        }

        // Handle plural forms
        if (!$intent['table']) {
            foreach ($map as $key => $table) {
                $pluralKey = $key . 's';
                if (str_contains($text, $pluralKey)) {
                    $intent['table'] = $table;
                    break;
                }
            }
        }

        // Extract standard/class filter
        if (preg_match('/standard\s*(\d+)/i', $text, $match)) {
            $intent['filters']['standard_id'] = $match[1];
        }

        return $intent;
    }

    private function executeQuery($intent, $sub_institute_id, $syear)
    {
        $table = $intent['table'];
        $query = DB::table($table);
        $query->select($table . '.*');
        $columns = DB::getSchemaBuilder()->getColumnListing($table);

        // Add sub_institute_id filter
        if (in_array('sub_institute_id', $columns)) {
            $query->where($table . '.sub_institute_id', $sub_institute_id);
        }

        // Add syear filter - only if table has syear column and action is not count (count should consider all years for the institute)
        if (in_array('syear', $columns) && $intent['action'] !== 'count') {
            $query->where($table . '.syear', $syear);
        }

        // Add joins for related data
        if (in_array('standard_id', $columns) && $table !== 'standard') {
            $query->leftJoin('standard', $table . '.standard_id', '=', 'standard.id');
            $query->addSelect('standard.name as standard_name');
        }

        if (in_array('subject_id', $columns) && $table !== 'sub_std_map') {
            $query->leftJoin('sub_std_map', $table . '.subject_id', '=', 'sub_std_map.subject_id');
            $query->addSelect('sub_std_map.display_name as subject_name');
        }

        if (in_array('profile_id', $columns)) {
            $query->leftJoin('tbluserprofilemaster', $table . '.profile_id', '=', 'tbluserprofilemaster.profile_id');
            $query->addSelect('tbluserprofilemaster.name as profile_name');
        }

        if ($table === 'tblstudent') {
            $query->leftJoin('tblstudent_enrollment', 'tblstudent.id', '=', 'tblstudent_enrollment.student_id');
            $query->where('tblstudent_enrollment.syear', $syear);
            $query->whereNull('tblstudent_enrollment.end_date');
        }

        // Apply additional filters
        foreach ($intent['filters'] as $col => $val) {
            if ($col !== 'standard_id' || in_array($col, $columns)) {
                $query->where($table . '.' . $col, $val);
            }
        }

        // Execute based on action
        if ($intent['action'] === 'count') {
            return ['count' => $query->count()];
        }

        // For list action, limit to 50 results for performance
        $data = $query->limit(50)->get();

        if ($data->isEmpty()) {
            return ['error' => 'No data found'];
        }

        return $data;
    }

    private function getFieldMap($table)
    {
        $maps = [
            'tblstudent' => ['Student Name' => 'first_name', 'Last Name' => 'last_name', 'Enrollment No' => 'enrollment_no', 'Mobile' => 'mobile', 'Email' => 'email'],
            'standard' => ['Standard Name' => 'name'],
            'tbluser' => ['User Name' => 'first_name', 'Last Name' => 'last_name', 'Mobile' => 'mobile', 'Email' => 'email', 'Profile' => 'profile_name'],
            'division' => ['Division Name' => 'name'],
            'academic_section' => ['Grade Name' => 'grade_name'],
            'sub_std_map' => ['Standard' => 'standard_name', 'Subject Name' => 'display_name', 'Elective' => 'is_elective'],
            'batch' => ['Batch Name' => 'batch_name', 'Start Date' => 'start_date', 'End Date' => 'end_date'],
            'announcement' => ['Title' => 'title', 'Description' => 'description', 'Start Date' => 'from_date', 'End Date' => 'to_date'],
            'chapter_master' => ['Chapter Name' => 'chapter_name', 'Standard' => 'standard_name', 'Subject' => 'subject_name'],
            'payroll_types' => ['Payroll Type' => 'payroll_name', 'Amount Type' => 'amount_type'],
            'period' => ['Period' => 'title', 'Short Name' => 'short_name', 'Start Time' => 'start_time', 'End Time' => 'end_time'],
            'question_paper' => ['Exam Name' => 'paper_name', 'Standard' => 'standard_name', 'Subject' => 'subject_name', 'Total Questions' => 'total_questions', 'Total Marks' => 'total_marks'],
            'transport_driver_detail' => ['Driver Name' => 'first_name', 'Last Name' => 'last_name', 'Mobile' => 'mobile', 'Type' => 'type', 'Status' => 'status'],
            'transport_kilometer_rate' => ['From Distance' => 'from_distance', 'To Distance' => 'to_distance', 'Rickshaw Rate' => 'rick_new', 'Van Rate' => 'van_new'],
            'transport_route' => ['Route Name' => 'route_name', 'From Time' => 'from_time', 'To Time' => 'to_time'],
            'transport_vehicle' => ['Vehicle Name' => 'title', 'Vehicle Number' => 'vehicle_number', 'Type' => 'vehicle_type', 'Capacity' => 'sitting_capacity'],
            'tbluserprofilemaster' => ['Profile Name' => 'name'],
            'hrms_departments' => ['Department Name' => 'department'],
        ];

        return $maps[$table] ?? [];
    }

    public function getIntentsList(Request $request)
    {
        $intents = [
            "Student Details",
            "Fees Details",
            "Admission Details",
            "Remain Fees",
            "Paid Fees",
            "Academic Sections",
            "Total Academic Sections",
            "Standards",
            "Total Standards",
            "Divisions",
            "Total Divisions",
            "Students",
            "Total Students",
            "Subjects",
            "Total Subjects",
            "Batches",
            "Total Batches",
            "Announcements",
            "Total Announcements",
            "Chapters",
            "Total Chapters",
            "Payroll Types",
            "Total Payroll Types",
            "Periods",
            "Total Periods",
            "Question Papers",
            "Total Question Papers",
            "Transport Drivers",
            "Total Transport Drivers",
            "Transport Kilometer Rates",
            "Total Transport Kilometer Rates",
            "Transport Routes",
            "Total Transport Routes",
            "Transport Vehicles",
            "Total Transport Vehicles",
            "User Profiles",
            "Total User Profiles",
            "HRMS Departments",
            "Total HRMS Departments",
        ];

        return response()->json(['intents' => $intents]);
    }
}
