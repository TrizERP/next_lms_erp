<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;

/**
 * AI templates, read from the existing `template_master` library.
 *
 * This is deliberately not a template system. `template_master` already holds the
 * school's designed documents, categorised by `module_name` and edited through
 * Settings → Templates; the Fees receipt is rendered from it today by
 * `fees_collect_controller`. All that is added here is a category — `module_name`
 * of "AI" — and a read path the assistant can reach.
 *
 * Placeholder substitution follows the convention already in that controller:
 * tokens are written `<<token>>` in the designer, and the WYSIWYG editor stores
 * them HTML-escaped, so both forms are replaced. Nothing is evaluated — this is
 * literal string replacement, which is the right security position for content
 * an administrator can edit.
 */
class AiTemplateService
{
    /** The `module_name` value that files a template under the assistant's category. */
    public const AI_MODULE = 'AI';

    /**
     * The AI-category templates this institute can use.
     *
     * Scoped the way the Fees lookup scopes: the institute's own templates when it
     * has them, falling back to the shared (sub_institute_id = 0) set otherwise, so
     * a school without its own designs still has something to render.
     */
    public function listTemplates(McpRequestContext $context, array $filters = []): array
    {
        $rows = $this->scopedQuery($context)
            ->orderBy('title')
            ->get(['id', 'sub_institute_id', 'module_name', 'title', 'status', 'created_on']);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(
                fn ($row) => str_contains(mb_strtolower((string) $row->title), $needle)
            )->values();
        }

        $templates = $rows->map(fn ($row) => [
            'id' => (int) $row->id,
            'title' => $row->title,
            'module_name' => $row->module_name,
            'status' => (int) $row->status,
            'scope' => ((int) $row->sub_institute_id) === 0 ? 'shared' : 'institute',
            'created_on' => $row->created_on,
        ])->all();

        return ToolResult::success(
            'ai.templates.list',
            $templates === []
                ? 'No AI templates are published in the template library yet.'
                : sprintf('%d AI template%s available.', count($templates), count($templates) === 1 ? '' : 's'),
            [
                'module_name' => self::AI_MODULE,
                'count' => count($templates),
                'templates' => $templates,
            ]
        );
    }

    /**
     * Render one AI template against a real admission enquiry.
     *
     * The enquiry is read through AdmissionMcpService rather than re-queried here,
     * so the assistant renders exactly the record the rest of the admission flow is
     * working with — same scoping, same joins, same field names.
     */
    public function renderForEnquiry(McpRequestContext $context, array $arguments): array
    {
        $enquiryId = (int) ($arguments['enquiry_id'] ?? 0);

        if ($enquiryId <= 0) {
            return ToolResult::failure(
                'ai.templates.render',
                'A valid admission enquiry is required.',
                'MISSING_ENQUIRY_ID'
            );
        }

        $template = $this->resolveTemplate($context, $arguments);

        if (! $template) {
            return ToolResult::failure(
                'ai.templates.render',
                'No matching AI template was found in the template library.',
                'TEMPLATE_NOT_FOUND',
                ['module_name' => self::AI_MODULE]
            );
        }

        $enquiry = app(AdmissionMcpService::class)->getEnquiryDetails($context, ['enquiry_id' => $enquiryId]);

        if (empty($enquiry['success'])) {
            return $enquiry;
        }

        $record = $enquiry['data']['enquiry'] ?? [];
        $values = $this->tokensForEnquiry($record);
        $html = $this->substitute((string) $template->html_content, $values);

        return ToolResult::success(
            'ai.templates.render',
            sprintf('Rendered "%s" for %s.', $template->title, $values['student_name'] ?: 'the selected enquiry'),
            [
                'template' => [
                    'id' => (int) $template->id,
                    'title' => $template->title,
                    'module_name' => $template->module_name,
                ],
                'enquiry' => $record,
                // What was actually substituted, so the output can be checked against
                // the record rather than taken on trust.
                'values' => $values,
                'unresolved_tokens' => $this->unresolvedTokens($html),
                'html' => $html,
            ],
            [
                'conversationPatch' => [
                    'workflow' => 'admission_confirmation',
                    'currentStep' => 'review_generated_document',
                    'selectedEntityType' => 'enquiry',
                    'selectedEntityId' => $enquiryId,
                    'workflowCompleted' => false,
                ],
            ]
        );
    }

    /**
     * The institute's own AI templates, or the shared set when it has none.
     *
     * Mirrors the IFNULL sub-select the Fees receipt lookup uses, expressed as two
     * plain queries because the intent — "mine, else shared" — is otherwise hidden
     * inside a raw string.
     */
    private function scopedQuery(McpRequestContext $context)
    {
        $base = fn () => DB::table('template_master')
            ->where('module_name', self::AI_MODULE)
            ->where(function ($query) {
                // `status` is a nullable integer in this table and existing rows are
                // saved as 1. Treat anything not explicitly disabled as usable, so a
                // template saved before a status was set is not silently invisible.
                $query->whereNull('status')->orWhere('status', '!=', 0);
            });

        $ownCount = (clone $base())->where('sub_institute_id', $context->selectedInstituteId)->count();

        return $ownCount > 0
            ? $base()->where('sub_institute_id', $context->selectedInstituteId)
            : $base()->where('sub_institute_id', 0);
    }

    /** Resolve by id when given one, otherwise by title — exact first, then partial. */
    private function resolveTemplate(McpRequestContext $context, array $arguments)
    {
        $templateId = (int) ($arguments['template_id'] ?? 0);

        if ($templateId > 0) {
            return $this->scopedQuery($context)->where('id', $templateId)->first();
        }

        $title = trim((string) ($arguments['title'] ?? ''));

        if ($title === '') {
            return null;
        }

        $candidates = $this->scopedQuery($context)->get();
        $needle = mb_strtolower($title);

        foreach ($candidates as $candidate) {
            if (mb_strtolower((string) $candidate->title) === $needle) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (str_contains(mb_strtolower((string) $candidate->title), $needle)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The token values for one admission enquiry.
     *
     * Names match the fields `presentAdmissionDetails` already returns, so what a
     * template can show is exactly what the admission flow holds — no invented
     * fields, and nothing that would render as a plausible blank.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, string>
     */
    private function tokensForEnquiry(array $record): array
    {
        $read = function (string $key) use ($record): string {
            $value = $record[$key] ?? null;

            return $value === null ? '' : trim((string) $value);
        };

        return [
            'student_name' => $read('student_name'),
            'enquiry_no' => $read('enquiry_no'),
            'enquiry_id' => $read('enquiry_id'),
            'mobile' => $read('mobile'),
            'standard_name' => $read('standard_name'),
            'division_name' => $read('division_name'),
            'quota_name' => $read('quota_name'),
            'admission_date' => $read('admission_date'),
            'status' => $read('status'),
        ];
    }

    /**
     * Literal replacement of `<<token>>`, in both the raw and HTML-escaped forms.
     *
     * The escaped form matters: the template designer is a WYSIWYG editor, so a
     * token typed as `<<student_name>>` is stored as `&lt;&lt;student_name&gt;&gt;`.
     * The Fees receipt handles this the same way.
     *
     * @param  array<string, string>  $values
     */
    private function substitute(string $html, array $values): string
    {
        foreach ($values as $token => $value) {
            $raw = '<<' . $token . '>>';
            $html = str_replace([$raw, htmlspecialchars($raw)], $value, $html);
        }

        return $html;
    }

    /**
     * Tokens the template asked for that this record could not fill.
     *
     * Surfaced rather than left as literal `<<...>>` in the output, so a template
     * expecting a field the enquiry does not hold is a visible fact instead of a
     * blemish someone notices after sending.
     *
     * @return array<int, string>
     */
    private function unresolvedTokens(string $html): array
    {
        $decoded = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        preg_match_all('/<<\s*([a-z0-9_]+)\s*>>/i', $decoded, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}
