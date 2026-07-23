<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AiSop;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AiSopGenerationController extends Controller
{
    private const SOP_PDF_DIRECTORY = 'public/admission_payment/';

    private const DEFAULT_SECTIONS = [
        'Purpose',
        'Scope',
        'Responsibilities',
        'Procedure',
        'Compliance',
        'Records',
        'Approval',
        'Required Documents',
    ];

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'nullable|integer|min:1',
            'sub_institute_id' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:Draft,Active,Archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $query = AiSop::query()
                ->select([
                    'id',
                    'sop_name',
                    'department_id',
                    'sop_content',
                    'pdf_file_path',
                    'pdf_url',
                    'sub_institute_id',
                    'created_by',
                    'updated_by',
                    'deleted_by',
                    'status',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ])
                ->latest('id');

            if (!empty($data['department_id'])) {
                $query->where('department_id', $data['department_id']);
            }

            if (!empty($data['sub_institute_id'])) {
                $query->where('sub_institute_id', $data['sub_institute_id']);
            }

            if (!empty($data['status'])) {
                $query->where('status', $data['status']);
            }

            return response()->json([
                'status_code' => 1,
                'message' => 'SUCCESS',
                'data' => $query->get(),
            ], 200);
        } catch (\Throwable $exception) {
            Log::error('AI SOP list failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'status_code' => 0,
                'message' => 'Unable to load SOPs. Please try again.',
            ], 500);
        }
    }

    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department' => 'required|string|max:150',
            'title' => 'required|string|max:180',
            'purpose' => 'required|string|max:2000',
            'roles' => 'nullable|string|max:1000',
            'keywords' => 'nullable',
            'language' => 'required|string|max:60',
            'sop_type' => 'required|string|max:100',
            'detail_level' => 'required|in:Brief,Standard,Detailed',
            'include_sections' => 'required|array|min:1',
            'include_sections.*' => 'required|string|max:80',
            'existing_content' => 'nullable|string|max:20000',
            'mode' => 'nullable|in:generate,improve,regenerate',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Gemini API key is not configured. Please set GEMINI_API_KEY in the backend .env file.',
            ], 500);
        }

        $data = $validator->validated();
        $mode = $data['mode'] ?? 'generate';
        $model = env('GEMINI_MODEL', 'gemini-2.5-flash');
        $prompt = $this->buildPrompt($data, $mode);

        try {
            $response = Http::timeout(90)
                ->retry(1, 500)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.35,
                        'topP' => 0.9,
                        'maxOutputTokens' => $this->maxTokensForDetailLevel($data['detail_level']),
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('Gemini SOP generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'status_code' => 0,
                    'message' => $this->geminiErrorMessage($response->json(), $response->status()),
                ], 502);
            }

            $content = $this->extractGeneratedText($response->json());

            if (!$content) {
                Log::warning('Gemini SOP generation returned empty content', [
                    'body' => $response->json(),
                ]);

                return response()->json([
                    'status_code' => 0,
                    'message' => 'Gemini returned an empty SOP. Please refine the inputs and try again.',
                ], 502);
            }

            return response()->json([
                'status_code' => 1,
                'message' => 'SUCCESS',
                'data' => [
                    'content' => $content,
                    'model' => $model,
                ],
            ], 200);
        } catch (\Throwable $exception) {
            Log::error('Gemini SOP generation exception', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'status_code' => 0,
                'message' => 'Something went wrong while generating the SOP. Please try again later.',
            ], 500);
        }
    }

    public function departmentJobRoles(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'department_id' => 'required|integer|min:1',
            'sub_institute_id' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            if (!Schema::hasTable('s_user_jobrole')) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Job role mapping table is not available.',
                ], 500);
            }

            $mappingColumns = Schema::getColumnListing('s_user_jobrole');
            $roleColumns = Schema::hasTable('s_jobrole') ? Schema::getColumnListing('s_jobrole') : [];
            $hasMappingColumn = static fn (string $column): bool => in_array($column, $mappingColumns, true);
            $hasRoleColumn = static fn (string $column): bool => in_array($column, $roleColumns, true);

            $query = DB::table('s_user_jobrole as suj')
                ->where('suj.department_id', $data['department_id']);

            if (!empty($data['sub_institute_id']) && $hasMappingColumn('sub_institute_id')) {
                $query->where('suj.sub_institute_id', $data['sub_institute_id']);
            }

            if ($hasMappingColumn('deleted_at')) {
                $query->whereNull('suj.deleted_at');
            }

            if ($hasMappingColumn('status')) {
                $query->where(function ($statusQuery) {
                    $statusQuery->whereNull('suj.status')
                        ->orWhere('suj.status', 1)
                        ->orWhere('suj.status', '1')
                        ->orWhere('suj.status', 'Active');
                });
            }

            $roleNameExpression = null;
            $roleIdExpression = null;

            if ($hasMappingColumn('jobrole_id') && $hasRoleColumn('id')) {
                $query->leftJoin('s_jobrole as sj', 'sj.id', '=', 'suj.jobrole_id');
                $roleIdExpression = 'COALESCE(suj.jobrole_id, suj.id)';

                if ($hasRoleColumn('jobrole')) {
                    $roleNameExpression = $hasMappingColumn('jobrole')
                        ? 'COALESCE(sj.jobrole, suj.jobrole)'
                        : 'sj.jobrole';
                } elseif ($hasRoleColumn('name')) {
                    $roleNameExpression = $hasMappingColumn('jobrole')
                        ? 'COALESCE(sj.name, suj.jobrole)'
                        : 'sj.name';
                }
            }

            if (!$roleNameExpression) {
                if ($hasMappingColumn('jobrole')) {
                    $roleNameExpression = 'suj.jobrole';
                } elseif ($hasMappingColumn('job_role')) {
                    $roleNameExpression = 'suj.job_role';
                } elseif ($hasMappingColumn('role_name')) {
                    $roleNameExpression = 'suj.role_name';
                } elseif ($hasMappingColumn('name')) {
                    $roleNameExpression = 'suj.name';
                }
            }

            if (!$roleNameExpression) {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'No supported job role name column was found in s_user_jobrole.',
                ], 500);
            }

            $roleIdExpression = $roleIdExpression ?: ($hasMappingColumn('id') ? 'suj.id' : 'NULL');

            $roles = $query
                ->selectRaw($roleIdExpression . ' as id')
                ->selectRaw($roleNameExpression . ' as name')
                ->distinct()
                ->orderBy('name')
                ->get()
                ->filter(static fn ($role) => trim((string) $role->name) !== '')
                ->values()
                ->map(static function ($role) {
                    $name = trim((string) $role->name);

                    return [
                        'id' => $role->id,
                        'name' => $name,
                        'label' => $name,
                        'value' => $name,
                    ];
                });

            return response()->json([
                'status_code' => 1,
                'message' => 'SUCCESS',
                'data' => $roles,
            ], 200);
        } catch (\Throwable $exception) {
            Log::error('AI SOP department job role lookup failed', [
                'message' => $exception->getMessage(),
                'department_id' => $data['department_id'] ?? null,
            ]);

            return response()->json([
                'status_code' => 0,
                'message' => 'Unable to load mapped job roles. Please try again.',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sop_name' => 'required|string|max:180',
            'department_id' => 'required|integer|min:1',
            'sop_content' => 'required|string|max:50000',
            'sub_institute_id' => 'required|integer|min:1',
            'created_by' => 'nullable|integer|min:1',
            'updated_by' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:Draft,Active,Archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $status = $data['status'] ?? 'Active';

        try {
            $sop = DB::transaction(function () use ($data, $status) {
                $pdfContent = $this->buildSopPdf($data['sop_name'], $data['sop_content']);
                $fileName = $this->buildPdfFileName($data['sop_name']);
                $filePath = self::SOP_PDF_DIRECTORY . $fileName;

                Storage::disk('digitalocean')->put($filePath, $pdfContent, 'public');

                return AiSop::create([
                    'sop_name' => $data['sop_name'],
                    'department_id' => $data['department_id'],
                    'sop_content' => $data['sop_content'],
                    'pdf_file_path' => $filePath,
                    'pdf_url' => Storage::disk('digitalocean')->url($filePath),
                    'sub_institute_id' => $data['sub_institute_id'],
                    'created_by' => $data['created_by'] ?? null,
                    'updated_by' => $data['updated_by'] ?? ($data['created_by'] ?? null),
                    'status' => $status,
                ]);
            });

            return response()->json([
                'status_code' => 1,
                'message' => 'SOP published successfully.',
                'data' => [
                    'id' => $sop->id,
                    'sop_name' => $sop->sop_name,
                    'department_id' => $sop->department_id,
                    'pdf_file_path' => $sop->pdf_file_path,
                    'pdf_url' => $sop->pdf_url,
                    'sub_institute_id' => $sop->sub_institute_id,
                    'created_by' => $sop->created_by,
                    'updated_by' => $sop->updated_by,
                    'deleted_by' => $sop->deleted_by,
                    'status' => $sop->status,
                    'created_at' => optional($sop->created_at)->toDateTimeString(),
                    'updated_at' => optional($sop->updated_at)->toDateTimeString(),
                    'deleted_at' => optional($sop->deleted_at)->toDateTimeString(),
                ],
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('AI SOP store failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'status_code' => 0,
                'message' => 'Unable to publish SOP. Please try again.',
            ], 500);
        }
    }

    private function buildPrompt(array $data, string $mode): string
    {
        $sections = $this->normalizeSections($data['include_sections'] ?? []);
        $keywords = $this->normalizeKeywords($data['keywords'] ?? []);
        $roles = trim((string)($data['roles'] ?? ''));
        $existingContent = trim((string)($data['existing_content'] ?? ''));

        $action = $mode === 'improve'
            ? 'Improve and rewrite the existing SOP content'
            : 'Generate a complete Standard Operating Procedure (SOP)';

        $lines = [
            $action . ' for a K-12 school ERP / school operations context.',
            '',
            'User inputs:',
            '- Department: ' . trim($data['department']),
            '- Title: ' . trim($data['title']),
            '- Purpose / Short Description: ' . trim($data['purpose']),
            '- Applicable Roles/Users: ' . ($roles !== '' ? $roles : 'Not specified'),
            '- Keywords: ' . (!empty($keywords) ? implode(', ', $keywords) : 'Not specified'),
            '- Language: ' . trim($data['language']),
            '- SOP Type: ' . trim($data['sop_type']),
            '- Detail Level: ' . trim($data['detail_level']),
            '- Include Sections: ' . implode(', ', $sections),
            '',
            'Output requirements:',
            '- Write only the SOP content. Do not include markdown fences, JSON, commentary, or AI disclaimers.',
            '- Use clear headings and numbered sections in the exact order of the requested sections.',
            '- Keep the title at the top.',
            '- Make responsibilities, procedure steps, records, approvals, compliance, and required documents practical and school-ready.',
            '- Use concise professional language suitable for policy documentation.',
            '- If a requested section has limited input, infer reasonable content from the department, purpose, roles, and keywords.',
            '- Write the entire SOP in ' . trim($data['language']) . '.',
        ];

        if ($data['detail_level'] === 'Brief') {
            $lines[] = '- Keep each section short with 2 to 4 bullet points or sentences.';
        } elseif ($data['detail_level'] === 'Standard') {
            $lines[] = '- Use moderate detail with actionable points under each section.';
        } else {
            $lines[] = '- Use detailed, audit-ready content with clear ownership, controls, exceptions, and records.';
        }

        if ($existingContent !== '') {
            $lines[] = '';
            $lines[] = 'Existing content to improve or regenerate from:';
            $lines[] = $existingContent;
        }

        return implode("\n", $lines);
    }

    private function normalizeSections(array $sections): array
    {
        $allowed = array_flip(self::DEFAULT_SECTIONS);
        $clean = [];

        foreach ($sections as $section) {
            $section = trim((string)$section);
            if ($section !== '' && isset($allowed[$section])) {
                $clean[] = $section;
            }
        }

        return !empty($clean) ? array_values(array_unique($clean)) : self::DEFAULT_SECTIONS;
    }

    private function normalizeKeywords($keywords): array
    {
        if (is_array($keywords)) {
            $items = $keywords;
        } else {
            $items = explode(',', (string)$keywords);
        }

        return array_values(array_filter(array_map(static function ($keyword) {
            return trim((string)$keyword);
        }, $items), static function ($keyword) {
            return $keyword !== '';
        }));
    }

    private function maxTokensForDetailLevel(string $detailLevel): int
    {
        if ($detailLevel === 'Brief') {
            return 1200;
        }

        if ($detailLevel === 'Standard') {
            return 2200;
        }

        return 3600;
    }

    private function extractGeneratedText(?array $payload): string
    {
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    private function geminiErrorMessage(?array $payload, int $status): string
    {
        $message = $payload['error']['message'] ?? '';

        if (is_string($message) && trim($message) !== '') {
            return 'Gemini API error: ' . trim($message);
        }

        return "Unable to generate SOP from Gemini. HTTP status: {$status}.";
    }

    private function buildSopPdf(string $title, string $content): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->buildSopPdfHtml($title, $content));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildSopPdfHtml(string $title, string $content): string
    {
        $safeTitle = e($title);
        $safeContent = nl2br(e($content));

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.55;
            margin: 28px 34px;
        }
        h1 {
            border-bottom: 1px solid #d1d5db;
            color: #061632;
            font-size: 20px;
            margin: 0 0 18px;
            padding-bottom: 10px;
        }
        .meta {
            color: #4b5563;
            font-size: 10px;
            margin-bottom: 20px;
        }
        .content {
            white-space: normal;
        }
    </style>
</head>
<body>
    <h1>{$safeTitle}</h1>
    <div class="meta">Generated SOP document</div>
    <div class="content">{$safeContent}</div>
</body>
</html>
HTML;
    }

    private function buildPdfFileName(string $title): string
    {
        $baseName = Str::slug($title);
        if ($baseName === '') {
            $baseName = 'ai-sop';
        }

        return $baseName . '-' . now()->format('YmdHis') . '-' . Str::random(8) . '.pdf';
    }
}
