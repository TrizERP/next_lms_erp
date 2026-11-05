<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\ConversationalSettingsService;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ConversationalSettingsController extends Controller
{
    public function __construct(
        private readonly ConversationalSettingsService $settingsService
    ) {}

    /**
     * Retrieve the centralized settings (or scoped to a project/platform).
     */
    public function getSettings(Request $request): JsonResponse
    {
        try {
            $projectId = $request->query('project_id')
                ?: $request->header('x-project-id')
                ?: $request->query('projectId');

            $settings = $this->settingsService->getCentralizedSettings($projectId);

            return response()->json([
                'success' => true,
                'message' => 'Centralized conversational settings retrieved.',
                'data' => $settings,
                'errors' => null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversational settings.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Persist centralized voice, language, and channel configuration.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'voice_enabled' => 'nullable|boolean',
                'default_language' => 'nullable|string|in:en-IN,hi-IN,gu-IN',
                'show_ask_tab' => 'nullable|boolean',
                'show_create_tab' => 'nullable|boolean',
                'suggested_prompt_source' => 'nullable|string|in:workspace,static,none',
                'project_id' => 'nullable|string|max:64',
                'updated_by' => 'nullable|string|max:255',
            ]);

            $updatedBy = $validated['updated_by'] ?? null;
            if (!$updatedBy) {
                $context = $request->attributes->get('mcp_context');
                if ($context instanceof McpRequestContext && $context->actor) {
                    $actor = $context->actor;
                    $name = trim((string)($actor->name ?? ''));
                    $id = trim((string)($actor->userId ?? ''));
                    $updatedBy = $name && $id ? "{$name} ({$id})" : ($name ?: $id);
                } elseif ($request->user()) {
                    $u = $request->user();
                    $name = trim((string)($u->name ?? ''));
                    $id = trim((string)($u->id ?? ''));
                    $updatedBy = $name && $id ? "{$name} ({$id})" : ($name ?: $id);
                }
            }

            $settings = $this->settingsService->saveCentralizedSettings($validated, $updatedBy);

            return response()->json([
                'success' => true,
                'message' => 'Centralized conversational settings saved successfully.',
                'data' => $settings,
                'errors' => null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save conversational settings.',
                'detail' => $e->getMessage(),
            ], 400);
        }
    }
}

