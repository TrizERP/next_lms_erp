<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getTableFieldFromId;
use App\Http\Controllers\student\tblstudentController;

class conversationalAIController extends Controller
{
    //
    public function genkitDetailsAPI(Request $request)
    {
        // resolve session
        $sub = in_array($request->type, ['API', 'JSON'])
            ? $request->sub_institute_id
            : session('sub_institute_id');
        $year = in_array($request->type, ['API', 'JSON'])
            ? $request->syear
            : session('syear');

        // find student
        $studentId = getTableFieldFromId(
            'tblstudent',
            'id',
            $request->enrollment_no,
            'enrollment_no',
            ['sub_institute_id' => $sub]
        );
        if (!$studentId) {
            return response()->json([
                'html' => '<div class="text-muted p-2">Failed to find student please check enrollment no.</div>',
                'raw_data' => 'No data Found'
            ]);
        }

        // fetch local data if needed
        $html = '';
        $data = [];
        if ($request->action_type === 'remain_fees' || $request->action_type === 'paid_fees') {
            $ctrl = new tblstudentController;
            $raw  = $ctrl->edit(
                new Request(['sub_institute_id' => $sub, 'syear' => $year, 'type' => 'API']),
                $studentId
            );
            $data = json_decode($raw, true);
            $html = $this->formatDetailsHTML('remain_fees', $data, ['unpaid_fees' => $data['paid_unpaid_fees'] ?? [], 'student_detail' => $data['data'] ?? []]);
        } else {
            // call external API
            $url  = "https://kgenkit.vercel.app/api/genkit-k12?enrollment_no={$request->enrollment_no}&action={$request->action_type}&syear={$year}&sub_institute_id={$sub}&student_id={$studentId}";
            $data = json_decode(
                (new \GuzzleHttp\Client)->get($url)->getBody(),
                true
            );

            if (empty($data) || !is_array($data)) {
                return response()->json([
                    'html' => '<div class="text-muted p-2">No records found for the provided enrollment number.</div>',
                    'raw_data' => $data
                ]);
            }

            // pick formatter
            $html = $this->formatDetailsHTML($request->action_type, $data);
        }
        return response()->json(['html' => $html, 'raw_data' => $data]);
    }

    /**
     * Common HTML formatting function for all detail types
     */
    private function formatDetailsHTML($actionType, $data, $extra = [])
    {
        switch ($actionType) {
            case 'fees_details':
                return $this->generateFeesHTML($data);
            case 'admission_details':
                return $this->generateAdmissionHTML($data);
            case 'remain_fees':
                return $this->generateUnpaidFeesHTML($extra['unpaid_fees'] ?? [], $extra['student_detail'] ?? []);
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

    private function generateUnpaidFeesHTML($unPaidFees, $studentDetail)
    {
        if (empty($unPaidFees)) {
            return '<div class="text-muted p-2">No records found.</div>';
        }

        $studentName = trim(($studentDetail['first_name'] ?? '') . ' ' . ($studentDetail['middle_name'] ?? '') . ' ' . ($studentDetail['last_name'] ?? ''));

        // Calculate totals
        $totalBk = 0;
        $totalPaid = 0;
        $totalRemain = 0;
        $totalDiscount = 0;
        foreach ($unPaidFees as $val) {
            $totalBk += (float)($val['bk'] ?? 0);
            $totalPaid += (float)($val['paid'] ?? 0);
            $totalRemain += (float)($val['remain'] ?? 0);
            $totalDiscount += (float)($val['discount'] ?? 0);
        }

        // Modern fees cards with flex-wrap
        $feesHtml = '<div style="display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0;">';
        foreach ($unPaidFees as $val) {
            $monthName = $val['month'] ?? 'N/A';
            $total = (float)($val['bk'] ?? 0);
            $paid = (float)($val['paid'] ?? 0);
            $remain = (float)($val['remain'] ?? 0);
            $discount = (float)($val['discount'] ?? 0);

            // Calculate payment percentage for progress bar
            $percentage = $total > 0 ? ($paid / $total) * 100 : 0;

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
                        <div style="font-size: 0.7rem; color: #6c757d; margin-bottom: 4px;">Paid</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #28a745;">₹' . number_format($paid, 2) . '</div>
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
                ">Total Paid</div>
                <div style="
                    font-size: 1.5rem;
                    font-weight: 800;
                    color: #28a745;
                ">₹' . number_format($totalPaid, 2) . '</div>
            </div>
            <div style="text-align: center; padding: 4px;">
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

        return $this->getCardWrapper("Unpaid Fees", "fa-file-alt", '
        <div class="detail-item">
            <span class="detail-label">Student Name : </span>
            <span class="detail-value fw-bold">' . ($studentName ?: 'N/A') . '</span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Enrollment No : </span>
            <span class="detail-value fw-bold">' . ($studentDetail['enrollment_no'] ?? 'N/A') . '</span>
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
        
        <!-- Modern Fees Cards -->
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

        $intent = $this->parseIntent($sentence);

        if (!$intent['table']) {
            return response()->json(['error' => 'Could not understand query']);
        }

        $result = $this->executeQuery($intent, $sub_institute_id, $syear);

        if (isset($result['error'])) {
            return response()->json($result);
        }
        $fieldMap = $this->getFieldMap($intent['table']);
        $html = $this->generateMockHTML($result, $fieldMap);

        return response()->json([
            'data' => $result,
            'html' => $html
        ]);
    }

    /**
     * Common HTML generation for mock queries
     */
    private function generateMockHTML($data, $fieldMap = [])
    {
        if (isset($data['count'])) {
            return "<div class='alert alert-info text-center shadow-sm' style='border-radius: 12px; border-left: 4px solid #0d6efd;'><strong>Total: {$data['count']}</strong></div>";
        }

        if (empty($data) || count($data) == 0) {
            return "<div class='alert alert-warning text-center shadow-sm' style='border-radius: 12px;'><strong>No data found</strong></div>";
        }

        $borderColors = [
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

        $html = "<div style='display: flex; flex-direction: column; align-items: center; gap: 0;'>";
        $index = 0;

        foreach ($data as $row) {
            $row = (array)$row;
            $borderColor = $borderColors[$index % count($borderColors)];

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
            ' onmouseover='this.style.boxShadow=\"0 4px 12px rgba(0,0,0,0.1)\";' 
             onmouseout='this.style.boxShadow=\"0 1px 3px rgba(0,0,0,0.05)\";'>
                <div class='card-body' style='padding: 14px 18px;'>
            ";

            if (!empty($fieldMap)) {
                foreach ($fieldMap as $label => $column) {
                    if (array_key_exists($column, $row)) {
                        $value = e($row[$column]);
                        $html .= "
                        <div style='margin-bottom: 10px; display: flex; align-items: flex-start; gap: 12px; font-size: 0.85rem;'>
                            <span style='color: black; font-weight: 600; min-width: 120px; font-size: 0.85rem;'>{$label}:</span>
                            <span style='color: #2c3e50; flex: 1; word-break: break-word;'>{$value}</span>
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
                        <span style='color: #2c3e50; flex: 1; word-break: break-word;'>{$value}</span>
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

    private function parseIntent($text)
    {
        $intent = ['action' => 'list', 'table' => null, 'filters' => []];

        if (str_contains($text, 'total') || str_contains($text, 'count') || str_contains($text, 'how many') || str_contains($text, 'howmany') || str_contains($text, 'how much') || str_contains($text, 'howmuch')) {
            $intent['action'] = 'count';
        }

        $map = [
            'academic section' => 'academic_section',
            'grades' => 'academic_section',
            'grade' => 'academic_section',
            'standard' => 'standard',
            'standards' => 'standard',
            'class' => 'standard',
            'division' => 'division',
            'divisions' => 'division',
            'section' => 'division',
            'sections' => 'division',
            'student' => 'tblstudent',
            'students' => 'tblstudent',
            'subject' => 'sub_std_map',
            'subjects' => 'sub_std_map',
            'batch' => 'batch',
            'batches' => 'batch',
            'announcement' => 'announcement',
            'announcements' => 'announcements',
            'notice' => 'announcement',
            'chapter' => 'chapter_master',
            'chapters' => 'chapter_master',
            'payroll type' => 'payroll_types',
            'payroll types' => 'payroll_types',
            'period' => 'period',
            'periods' => 'period',
            'question paper' => 'question_paper',
            'question papers' => 'question_paper',
            'driver' => 'transport_driver_detail',
            'drivers' => 'transport_driver_detail',
            'kilometer rate' => 'transport_kilometer_rate',
            'kilometer rates' => 'transport_kilometer_rate',
            'km rates' => 'transport_kilometer_rate',
            'transport route' => 'transport_route',
            'transport routes' => 'transport_route',
            'vehicle' => 'transport_vehicle',
            'vehicles' => 'transport_vehicle',
            'transport vehicle' => 'transport_vehicle',
            'user profile' => 'tbluserprofilemaster',
            'user profiles' => 'tbluserprofilemaster',
            'profile' => 'tbluserprofilemaster',
            'user' => 'tbluser',
            'users' => 'tbluser',
            'teacher' => 'tbluser',
            'teachers' => 'tbluser',
            'hrms departments' => 'hrms_departments',
            'hrms department' => 'hrms_departments',
            'department' => 'hrms_departments',
            'departments' => 'hrms_departments',
        ];

        foreach ($map as $key => $table) {
            if (str_contains($text, $key)) {
                $intent['table'] = $table;
                break;
            }
        }

        if (preg_match('/standard\s*(\d+)/', $text, $match)) {
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

        if (in_array('sub_institute_id', $columns)) {
            $query->where($table . '.sub_institute_id', $sub_institute_id);
        }

        if (in_array('syear', $columns)) {
            $query->where($table . '.syear', $syear);
        }

        if (in_array('standard_id', $columns)) {
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
        }

        foreach ($intent['filters'] as $col => $val) {
            if ($col !== 'standard_id') {
                $query->where($table . '.' . $col, $val);
            }
        }

        if ($intent['action'] === 'count') {
            return ['count' => $query->count()];
        }

        $data = $query->limit(50)->get();

        if ($data->isEmpty()) {
            return ['error' => 'No data found'];
        }

        return $data;
    }

    private function getFieldMap($table)
    {
        return [
            'tblstudent' => ['Student Name' => 'first_name', 'Last Name' => 'last_name', 'GR no' => 'enrollment_no', 'Mobile' => 'mobile', 'Email' => 'email'],
            'standard' => ['Standard Name' => 'name'],
            'tbluser' => ['User Name' => 'first_name', 'Last Name' => 'last_name', 'Mobile' => 'mobile', 'Email' => 'email', 'Profile' => 'profile_name', 'Department' => 'department_name'],
            'division' => ['Division Name' => 'name'],
            'sub_std_map' => ['Standard' => 'standard_name', 'Subject Name' => 'display_name', 'Elective Subject' => 'is_elective'],
            'announcements' => ['Announcement' => 'title', 'Announcement Content' => 'description', 'Start Date' => 'from_date', 'End Date' => 'to_date'],
            'chapter_master' => ['Standard Name' => 'standard_name', 'Subject Name' => 'subject_name', 'Chapter Name' => 'chapter_name'],
            'payroll_types' => ['Payroll Type' => 'payroll_name', 'Amount Type' => 'amount_type'],
            'period' => ['Period' => 'title', 'Short Name' => 'short_name', 'Start Time' => 'start_time', 'End Time' => 'end_time'],
            'question_paper' => ['Standard Name' => 'standard_name', 'Subject Name' => 'subject_name', 'Exam Name' => 'paper_name', 'Open Date' => 'open_date', 'Close Date' => 'close_date', 'Total Questions' => 'total_questions', 'Total Marks' => 'total_marks'],
            'transport_driver_detail' => ['Driver Name' => 'first_name', 'Last Name' => 'last_name', 'Mobile' => 'mobile', 'Type' => 'type', 'Status' => 'status'],
            'transport_kilometer_rate' => ['Distance From School' => 'distance_from_school', 'From Distance' => 'from_distance', 'To Distance' => 'to_distance', 'Old Rickshaw Rate' => 'rick_old', 'New Rickshaw Rate' => 'rick_new', 'Old Van Rate' => 'van_old', 'New Van Rate' => 'van_new'],
            'transport_route' => ['Route Name' => 'route_name', 'From Time' => 'from_time', 'To Time' => 'to_time'],
            'transport_vehicle' => ['Vehicle Name' => 'title', 'Vehicle Number' => 'vehicle_number', 'Vehicle Type' => 'vehicle_type', 'Sitting Capacity' => 'sitting_capacity'],
            'tbluserprofilemaster' => ['Profile Name' => 'name'],
            'hrms_departments' => ['Department Name' => 'department'],
        ][$table] ?? [];
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
