<?php

namespace App\Http\Controllers\student;

use App\Models\transportation\add_driver\add_driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\getStudents;

class StudentIcardApiController extends studentIcardController
{
    protected function bootstrapRequestContext(Request $request): array
    {
        $context = [
            'sub_institute_id' => (string) $request->input('sub_institute_id', $request->session()->get('sub_institute_id')),
            'syear' => (string) $request->input('syear', $request->session()->get('syear')),
            'user_id' => (string) $request->input('user_id', $request->session()->get('user_id')),
            'term_id' => (string) $request->input('term_id', $request->session()->get('term_id')),
            'user_profile_id' => (string) $request->input('user_profile_id', $request->session()->get('user_profile_id')),
            'user_profile_name' => (string) $request->input('user_profile_name', $request->session()->get('user_profile_name')),
            'client_id' => (string) $request->input('client_id', $request->session()->get('client_id')),
        ];

        foreach ($context as $key => $value) {
            if ($value !== '') {
                $request->session()->put($key, $value);
            }
        }

        return $context;
    }

    protected function validateContext(array $context, bool $requiresAcademicYear = true): ?JsonResponse
    {
        if ($context['sub_institute_id'] === '') {
            return response()->json([
                'status' => 0,
                'message' => 'sub_institute_id is required.',
            ], 422);
        }

        if ($requiresAcademicYear && $context['syear'] === '') {
            return response()->json([
                'status' => 0,
                'message' => 'syear is required.',
            ], 422);
        }

        return null;
    }

    protected function ensureJsonResponse($response): JsonResponse
    {
        if ($response instanceof JsonResponse) {
            return $response;
        }

        if (is_array($response)) {
            return response()->json($response);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Unexpected response from student I-card workflow.',
        ], 500);
    }

    protected function resolveTemplateOptions(string $subInstituteId): array
    {
        $templates = [
            ['value' => 'template_1', 'label' => 'Template 1'],
            ['value' => 'template_2', 'label' => 'Template 2'],
            ['value' => 'template_3', 'label' => 'Template 3'],
            ['value' => 'template_4', 'label' => 'Template 4'],
        ];

        if ($subInstituteId === '257') {
            foreach (['cn_card_front' => 'CN Card Front', 'cn_card_back' => 'CN Card Back'] as $value => $label) {
                if (file_exists($this->getTemplatePath($value))) {
                    $templates[] = ['value' => $value, 'label' => $label];
                }
            }
        }

        return array_map(function (array $template) {
            return [
                'value' => $template['value'],
                'label' => $template['label'],
                'sample_url' => url('icard/templates/' . $template['value'] . '.html'),
            ];
        }, $templates);
    }

    protected function getTemplatePath(string $template): string
    {
        return public_path('icard/templates/' . $template . '.html');
    }

    protected function loadTemplateParts(string $template): array
    {
        $filePath = $this->getTemplatePath($template);
        if (!file_exists($filePath)) {
            throw new \RuntimeException('Selected I-card template is not available.');
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the selected I-card template.');
        }

        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $contents, $styleMatches);
        $styles = isset($styleMatches[1]) ? implode("\n", $styleMatches[1]) : '';

        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $contents, $bodyMatch)) {
            $body = $bodyMatch[1];
        } else {
            $body = $contents;
        }

        return [
            'styles' => $styles,
            'body' => $body,
        ];
    }

    protected function buildStudentCardHtml(array $students, string $template, int $row, int $column, $receiptBook, string $syear): string
    {
        $templateParts = $this->loadTemplateParts($template);
        $templateBody = $templateParts['body'];
        $templateStyles = $templateParts['styles'];
        $baseUrl = rtrim(url('/'), '/');
        $pageBreakCount = max(1, $row * $column);
        $itemsHtml = '';
        $count = 0;

        foreach ($students as $value) {
            $count++;
            $icardIcon = '';
            if (isset($value['icard_icon']) && $value['icard_icon'] !== '') {
                $icardIcon = $baseUrl . '/storage/driver/' . ltrim($value['icard_icon'], '/');
            }

            $address = strtoupper(trim((string) ($value['address'] ?? '')));
            $address = $address !== '' ? substr($address, 0, 80) . '.' : '&nbsp;';
            $distanceRate = isset($value['distance_rate']) && $value['distance_rate'] !== '' ? 'Rs ' . $value['distance_rate'] : '';
            $fromDistance = isset($value['from_distance']) && $value['from_distance'] !== '' ? $value['from_distance'] . 'K.M.' : '';
            $admissionDate = !empty($value['admission_date']) ? \Carbon\Carbon::parse($value['admission_date'])->format('d-m-Y') : '&nbsp;';
            $birthDate = !empty($value['dob']) ? \Carbon\Carbon::parse($value['dob'])->format('d-m-Y') : '&nbsp;';
            $studentImage = !empty($value['image']) ? $baseUrl . '/storage/student/' . ltrim($value['image'], '/') : '';
            $schoolImage = !empty($value['school_image']) ? $baseUrl . '/storage/school/' . ltrim($value['school_image'], '/') : '';

            $cardHtml = $templateBody;
            $cardHtml = str_replace(htmlspecialchars('<<student_name>>'), \App\Helpers\sortStudentName($value['student_name'] ?? ''), $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<short_student_name>>'), strtoupper($value['short_student_name'] ?? ''), $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<enrollment_no>>'), $value['enrollment_no'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<standard_name>>'), $value['standard_name'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<division_name>>'), $value['division_name'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<father_name>>'), $value['father_name'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<mother_name>>'), $value['mother_name'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<address>>'), $address, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<mobile>>'), $value['mobile'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<admission_date>>'), $admissionDate, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<dob>>'), $birthDate, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<batch_name>>'), $value['batch_name'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<emergency_contact>>'), $value['emergency_contact'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<blood_group_name>>'), $value['blood_group_name'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<mother_mobile>>'), $value['mother_mobile'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<student_image>>'), $studentImage, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<gender>>'), $value['gender'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<driver_name>>'), strtoupper($value['driver_name'] ?? ''), $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<driver_mobile>>'), $value['driver_mobile'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<icard_icon>>'), $icardIcon, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<distance_from_school>>'), $value['distance_from_school'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<from_distance>>'), $fromDistance, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<distance_rate>>'), $distanceRate, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<school_name>>'), $value['school_name'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<school_mobile>>'), $value['school_mobile'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<school_image>>'), $schoolImage, $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<school_address>>'), $value['school_address'] ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<years>>'), $syear . '-' . ((int) $syear + 1), $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<nationality>>'), $value['nationality'] ?? '&nbsp;', $cardHtml);

            if (!empty($receiptBook)) {
                $receiptLogo = !empty($receiptBook->receipt_logo) ? $baseUrl . '/storage/fees/' . ltrim($receiptBook->receipt_logo, '/') : '';
                $cardHtml = str_replace(htmlspecialchars('<<receipt_logo>>'), $receiptLogo, $cardHtml);
                $cardHtml = str_replace(htmlspecialchars('<<receipt_line_1>>'), $receiptBook->receipt_line_1 ?? '', $cardHtml);
                $cardHtml = str_replace(htmlspecialchars('<<receipt_line_2>>'), $receiptBook->receipt_line_2 ?? '', $cardHtml);
                $cardHtml = str_replace(htmlspecialchars('<<receipt_line_3>>'), $receiptBook->receipt_line_3 ?? '', $cardHtml);
                $cardHtml = str_replace(htmlspecialchars('<<receipt_line_4>>'), $receiptBook->receipt_line_4 ?? '', $cardHtml);
            }

            $itemsHtml .= '<div class="item">' . $cardHtml . '</div>';

            if ($count % $pageBreakCount === 0) {
                $itemsHtml .= '<div class="page-break-clear"></div><div class="page-break"></div>';
            }
        }

        return '<style>
            * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
            .student-icard-grid { display: grid; grid-template-columns: repeat(' . max(1, $column) . ', 1fr); grid-template-rows: repeat(' . max(1, $row) . ', 1fr); grid-column-gap: 10px; grid-row-gap: 5px; width: 48vh; }
            .student-icard-grid .item { position: relative; z-index: 9; margin: 0 25px; }
            .page-break-clear { clear: both; }
            .page-break { page-break-after: always; }
            ' . $templateStyles . '
        </style><div class="student-icard-grid">' . $itemsHtml . '</div>';
    }

    public function metadata(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context, false);
        if ($validation) {
            return $validation;
        }

        $drivers = add_driver::where([
            'sub_institute_id' => $context['sub_institute_id'],
            'type' => 'Driver',
            'status' => 'Active',
        ])->get(['id', 'first_name'])->toArray();

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'drivers' => $drivers,
            'templates' => $this->resolveTemplateOptions($context['sub_institute_id']),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $request->merge(['type' => 'API']);
        return $this->ensureJsonResponse($this->showStudent($request));
    }

    public function preview(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $template = (string) $request->input('template');
        $students = $request->input('students', []);
        $row = max(1, (int) $request->input('row', 1));
        $column = max(1, (int) $request->input('column', 1));

        if (!is_array($students)) {
            $students = array_filter(array_map('trim', explode(',', (string) $students)));
        }

        if ($template === '') {
            return response()->json([
                'status' => 0,
                'message' => 'template is required.',
            ], 422);
        }

        if (empty($students)) {
            return response()->json([
                'status' => 0,
                'message' => 'Please select at least one student.',
            ], 422);
        }

        $studentData = getStudents($students, $context['sub_institute_id'], $context['syear']);
        if (empty($studentData)) {
            return response()->json([
                'status' => 0,
                'message' => 'No student found for I-card generation.',
            ], 404);
        }

        $receiptBook = DB::table('fees_receipt_book_master')
            ->selectRaw('*,GROUP_CONCAT(fees_head_id) heads')
            ->where('syear', $context['syear'])
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->groupByRaw('receipt_line_1,receipt_line_2,receipt_line_3,receipt_line_4,receipt_prefix,receipt_logo,last_receipt_number')
            ->orderBy('sort_order')
            ->first();

        try {
            $html = $this->buildStudentCardHtml($studentData, $template, $row, $column, $receiptBook, $context['syear']);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'html' => $html,
            'template' => $template,
            'row' => $row,
            'column' => $column,
            'selected_count' => count($studentData),
        ]);
    }
}
