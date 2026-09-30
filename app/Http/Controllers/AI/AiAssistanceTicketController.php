<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Support\AiAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The "No, I'm fine" branch of the stuck-user popup.
 *
 * Deliberately a small, dedicated table rather than the estate's existing complaint or
 * student-request queues — see the migration's own docblock for why. Three endpoints:
 * `store` (any signed-in user, on the popup's decline path), `index` and `screenshot`
 * (admin only, for Support to review). Nothing here is generative — no model is called;
 * this is a plain, governed write of what the popup already knew.
 */
class AiAssistanceTicketController extends AiController
{
    public function __construct(private readonly AiAuditLogger $audit)
    {
    }

    /**
     * Record a ticket. The screenshot is optional and its absence is not an error —
     * html2canvas can fail on some page content, and a ticket with no image is still a
     * real, useful signal that someone struggled here.
     */
    public function store(Request $request)
    {
        try {
            $scope = $this->scope($request);

            $validated = $request->validate([
                'module' => 'nullable|string|max:80',
                'page_title' => 'nullable|string|max:200',
                'page_path' => 'nullable|string|max:300',
                'idle_seconds' => 'required|integer|min:0|max:86400',
                'context' => 'nullable|array',
                // A data: URI, capped well above a typical compressed PNG of one
                // screen and well below anything that would tie up the request.
                'screenshot' => 'nullable|string|max:8000000',
            ]);

            $screenshotPath = $this->storeScreenshot($validated['screenshot'] ?? null, $scope->selectedInstituteId);

            $id = DB::table('ai_assistance_tickets')->insertGetId([
                'sub_institute_id' => $scope->selectedInstituteId,
                'client_id' => $scope->clientId,
                'user_id' => $scope->userId,
                'user_name' => $request->input('user_name'),
                'user_role' => $scope->role,
                'module' => $validated['module'] ?? null,
                'page_title' => $validated['page_title'] ?? null,
                'page_path' => $validated['page_path'] ?? null,
                'idle_seconds' => $validated['idle_seconds'],
                'context_snapshot' => isset($validated['context']) ? json_encode($validated['context']) : null,
                'screenshot_path' => $screenshotPath,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record(AiAuditLogger::ASSISTANCE_TICKET_CREATED, $scope, [
                'related_type' => 'ai_assistance_tickets',
                'related_id' => $id,
                'message' => sprintf(
                    'Stuck-user ticket raised on %s after %ds idle.',
                    $validated['page_title'] ?? ($validated['page_path'] ?? 'an unnamed screen'),
                    $validated['idle_seconds']
                ),
                'payload' => ['module' => $validated['module'] ?? null, 'has_screenshot' => $screenshotPath !== null],
            ]);

            return $this->success('Thanks — we have noted this for the support team.', ['id' => $id]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The queue, for Support/Admin. Never the screenshot binary itself — that is a
     * separate, individually-authorised fetch, so listing a hundred tickets does not
     * mean transferring a hundred images nobody has asked to see yet.
     */
    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);

            if (! $scope->isAdmin) {
                return $this->failure('Only an administrator may review assistance tickets.', 403);
            }

            $query = DB::table('ai_assistance_tickets')
                ->where('sub_institute_id', $scope->selectedInstituteId);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $rows = $query->orderByDesc('created_at')->limit($this->limit($request))->get();

            return $this->success('Tickets loaded.', [
                'tickets' => $rows->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'module' => $row->module,
                    'page_title' => $row->page_title,
                    'page_path' => $row->page_path,
                    'idle_seconds' => (int) $row->idle_seconds,
                    'user_name' => $row->user_name,
                    'user_role' => $row->user_role,
                    'status' => $row->status,
                    'has_screenshot' => $row->screenshot_path !== null,
                    'context' => $row->context_snapshot ? json_decode($row->context_snapshot, true) : null,
                    'created_at' => $row->created_at,
                ])->all(),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * The screenshot itself, on the `local` (non-public) disk, admin-gated on every
     * request — never a public URL. A stuck-user screenshot can show anything the
     * screen was showing when it fired: another family's fee amount, a child's name.
     */
    public function screenshot(Request $request, int $ticket)
    {
        try {
            $scope = $this->scope($request);

            if (! $scope->isAdmin) {
                return $this->failure('Only an administrator may view an assistance ticket screenshot.', 403);
            }

            $row = DB::table('ai_assistance_tickets')
                ->where('id', $ticket)
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->first();

            if (! $row || ! $row->screenshot_path || ! Storage::disk('local')->exists($row->screenshot_path)) {
                return $this->failure('No screenshot is stored for that ticket.', 404);
            }

            return response(Storage::disk('local')->get($row->screenshot_path), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    /**
     * @return string|null The stored path, or null if no screenshot was sent or it
     *                      could not be decoded — never a fatal error, since the
     *                      ticket itself is the useful part.
     */
    private function storeScreenshot(?string $dataUri, int $instituteId): ?string
    {
        if (! $dataUri || ! str_starts_with($dataUri, 'data:image/')) {
            return null;
        }

        $comma = strpos($dataUri, ',');

        if ($comma === false) {
            return null;
        }

        $binary = base64_decode(substr($dataUri, $comma + 1), true);

        if ($binary === false || $binary === '') {
            return null;
        }

        $path = sprintf(
            'ai-assistance-tickets/%d/%s-%s.png',
            $instituteId,
            now()->format('Ymd-His'),
            Str::random(8)
        );

        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
