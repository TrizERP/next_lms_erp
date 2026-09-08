<?php

namespace App\Http\Controllers\AI;

use App\Services\Mcp\AiReportGenerator;
use App\Services\Mcp\AiTemplateService;
use App\Services\Mcp\ReportSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The saved AI reports — read, edit, and refresh.
 *
 * A report is a `template_master` row filed under the assistant's category, written
 * by `AiReportGenerator` when somebody asks for one in the chat. Everything here
 * serves the one page that opens it: `/ai-reports/{id}` in the Next.js frontend, the
 * link the generator hands back.
 *
 * Three decisions worth stating, because each had an alternative:
 *
 *   1. **These live on the AI API, not on `general-setup/templates`.** That endpoint
 *      already saves this exact column and it was tempting to reuse for the save.
 *      But it takes the tenant and the category from request input, while this group
 *      derives both from the JWT via McpContextHydrator, and refreshing figures needs
 *      that context anyway. Splitting one page across two auth stacks and two scoping
 *      models to save a controller method would have been the more expensive choice.
 *   2. **A report is only ever this tenant's.** There is no shared (id 0) fallback of
 *      the kind `AiTemplateService` uses for designed templates: a template is
 *      something a school may inherit, a report is a statement about one school's own
 *      records.
 *   3. **Saved HTML is stored as written.** This is the trust position
 *      `template_master` already has — Settings → Templates saves the same column
 *      from a WYSIWYG with a code view, unfiltered — and a hand-rolled strip here
 *      would be a claim of safety this file cannot keep. The mitigation belongs at
 *      the render surface, which shows a report inside a sandboxed frame, so a script
 *      in a saved document has nothing to execute in.
 */
class ReportController extends AiController
{
    public function __construct(
        private readonly AiReportGenerator $reports,
        private readonly ReportSender $sender,
    ) {
    }

    /** One report, with its document and what its figures say about themselves. */
    public function show(Request $request, int $report)
    {
        try {
            $context = $this->scope($request);
            $row = $this->find($context, $report);

            if (! $row) {
                return $this->failure('That report could not be found.', 404);
            }

            $html = (string) $row->html_content;

            return $this->success('Report loaded.', [
                'report' => [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'html' => $html,
                    'created_on' => $row->created_on,
                    // Null when the document no longer holds a generated table — the
                    // page uses this to decide whether refreshing is offered at all,
                    // rather than offering a button that can only fail.
                    'figures' => $this->reports->describeFigures($html),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /** Save an edited report. */
    public function save(Request $request, int $report)
    {
        try {
            $context = $this->scope($request);

            $validated = $request->validate([
                'title' => 'required|string|max:250',
                'html' => 'required|string',
            ]);

            $row = $this->find($context, $report);

            if (! $row) {
                return $this->failure('That report could not be found.', 404);
            }

            DB::table('template_master')
                ->where('id', $report)
                ->where('sub_institute_id', $context->selectedInstituteId)
                ->update([
                    'title' => $validated['title'],
                    'html_content' => $validated['html'],
                ]);

            return $this->success('Report saved.', [
                'report' => [
                    'id' => $report,
                    'title' => $validated['title'],
                    'figures' => $this->reports->describeFigures($validated['html']),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Re-read the live rows and replace only the generated figures.
     *
     * The failure cases here are answers rather than errors — a document whose table
     * has been deleted, or a query that now matches nothing — so they come back as a
     * refusal with the reason, and the stored document is left as it was.
     */
    public function regenerate(Request $request, int $report)
    {
        try {
            $result = $this->reports->refresh($this->scope($request), $report);

            if (empty($result['success'])) {
                return $this->failure(
                    (string) ($result['message'] ?? 'The figures could not be refreshed.'),
                    422,
                    $result['error'] ?? null
                );
            }

            return $this->success((string) $result['message'], $result['data'] ?? null);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Who this report would reach, and what each of them would receive.
     *
     * A read: nothing is queued and nothing is sent. It exists so that the person
     * pressing Send approves an actual list of names and an actual notice, rather than
     * a button labelled with a promise.
     */
    public function recipients(Request $request, int $report)
    {
        try {
            $result = $this->sender->preview($this->scope($request), $report);

            if (empty($result['success'])) {
                return $this->failure((string) $result['message'], 422, $result['error'] ?? null);
            }

            return $this->success((string) $result['message'], $result['data'] ?? null);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * Send each person in the report their own figures.
     *
     * `expected_recipients` is required rather than optional. It is the count the
     * caller was shown by `recipients`, and the send is refused if the list has moved
     * since — the difference between confirming a list and confirming a button.
     */
    public function send(Request $request, int $report)
    {
        try {
            $validated = $request->validate([
                'expected_recipients' => 'required|integer|min:1',
                'confirm' => 'required|accepted',
            ]);

            $result = $this->sender->send(
                $this->scope($request),
                $report,
                (int) $validated['expected_recipients']
            );

            if (empty($result['success'])) {
                return $this->failure((string) $result['message'], 422, $result['error'] ?? null);
            }

            return $this->success((string) $result['message'], $result['data'] ?? null);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function find($context, int $report)
    {
        return $this->scopedQuery($context)->where('id', $report)->first();
    }

    /** The assistant's own reports for this school, and nothing else. */
    private function scopedQuery($context)
    {
        return DB::table('template_master')
            ->where('module_name', AiTemplateService::AI_MODULE)
            ->where('sub_institute_id', $context->selectedInstituteId)
            ->where(function ($query) {
                // `status` is nullable in this table and generated rows are saved as 1.
                // Anything not explicitly disabled counts, matching how
                // AiTemplateService reads the same column.
                $query->whereNull('status')->orWhere('status', '!=', 0);
            });
    }
}
