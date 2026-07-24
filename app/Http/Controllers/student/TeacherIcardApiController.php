<?php

namespace App\Http\Controllers\student;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherIcardApiController extends teacherIcardController
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

    protected function validateContext(array $context): ?JsonResponse
    {
        if ($context['sub_institute_id'] === '') {
            return response()->json([
                'status' => 0,
                'message' => 'sub_institute_id is required.',
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
            'message' => 'Unexpected response from teacher I-card workflow.',
        ], 500);
    }

    protected function resolveTemplateOptions(): array
    {
        return [
            [
                'value' => 'teacher_template_3',
                'label' => 'Template 1',
                'sample_url' => url('icard/templates/teacher_template_3.html'),
            ],
        ];
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

    protected function buildTeacherCardHtml(array $users, string $template, int $row, int $column): string
    {
        $templateParts = $this->loadTemplateParts($template);
        $templateBody = $templateParts['body'];
        $templateStyles = $templateParts['styles'];
        $pageBreakCount = max(1, $row * $column);
        $itemWidth = 100 / max(1, $column);
        $itemsHtml = '';
        $count = 0;

        foreach ($users as $value) {
            $count++;

            $cardHtml = $templateBody;
            $cardHtml = str_replace(htmlspecialchars('<<first_name>>'), $value->first_name ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<last_name>>'), $value->last_name ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<email>>'), $value->email ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<mobile>>'), $value->mobile ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<gender>>'), $value->gender ?? '&nbsp;', $cardHtml);
            $cardHtml = str_replace(htmlspecialchars('<<address>>'), $value->address ?? '&nbsp;', $cardHtml);

            $itemsHtml .= '<div class="item" style="width:' . $itemWidth . '%;">' . $cardHtml . '</div>';

            if ($count % $pageBreakCount === 0) {
                $itemsHtml .= '<div class="page-break-clear"></div><div class="page-break"></div>';
            }
        }

        return '<style>
            * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
            .teacher-icard-grid { display: flex; flex-wrap: wrap; align-items: flex-start; }
            .teacher-icard-grid .item { box-sizing: border-box; padding: 0 10px 10px 0; }
            .page-break-clear { clear: both; width: 100%; }
            .page-break { page-break-after: always; width: 100%; }
            ' . $templateStyles . '
        </style><div class="teacher-icard-grid">' . $itemsHtml . '</div>';
    }

    public function metadata(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        return response()->json([
            'status' => 1,
            'message' => 'Success',
            'teacher_types' => $this->teacher_types($context['sub_institute_id']),
            'templates' => $this->resolveTemplateOptions(),
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
        return $this->ensureJsonResponse($this->showTeacher($request));
    }

    public function preview(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $template = (string) $request->input('template');
        $users = $request->input('users', []);
        $row = max(1, (int) $request->input('row', 1));
        $column = max(1, (int) $request->input('column', 1));

        if (!is_array($users)) {
            $users = array_filter(array_map('trim', explode(',', (string) $users)));
        }

        if ($template === '') {
            return response()->json([
                'status' => 0,
                'message' => 'template is required.',
            ], 422);
        }

        if (empty($users)) {
            return response()->json([
                'status' => 0,
                'message' => 'Please select at least one user.',
            ], 422);
        }

        $userData = DB::table('tbluser')
            ->select('first_name', 'last_name', 'email', 'mobile', 'gender', 'address')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereIn('id', $users)
            ->where('status', 1)
            ->get()
            ->toArray();

        if (empty($userData)) {
            return response()->json([
                'status' => 0,
                'message' => 'No active users found for I-card generation.',
            ], 404);
        }

        try {
            $html = $this->buildTeacherCardHtml($userData, $template, $row, $column);
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
            'selected_count' => count($userData),
        ]);
    }
}
