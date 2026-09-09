<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\lms\Content\AuthoringTypeRegistry;
use App\Services\lms\Content\ContentAuthoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * The one front door for content authoring — tracker row 3 / Decision #36.
 *
 * Replaces three separately-built creation flows at the API layer:
 *   POST /api/lms/gamma-content-master        (Generate content)
 *   POST /api/lms-chapter-content/upload      (Upload content)
 *   POST /api/intelligence/questions/generate (Generate Questions)
 *
 * Those three routes REMAIN LIVE and unchanged. They are load-bearing for the legacy Blade
 * UI and, in the case of the mobile writers, for shipped app clients. This is an
 * additional front door, not a replacement — the strangler's first step. Retiring them
 * requires golden-file tests capturing their exact current envelopes, which needs traffic
 * capture and is a separate piece of work.
 *
 * Auth: ['lms.auth', 'perm:lms.content,create'] — the pattern already applied to the other
 * content write routes in Phase A4. Tenancy comes from the token, never the body.
 */
class ContentAuthoringController extends Controller
{
    public function __construct(
        private ContentAuthoringService $authoring,
        private AuthoringTypeRegistry $registry
    ) {
    }

    /**
     * GET /api/lms/content/authoring-vocabulary
     *
     * Drives a schema-built form, the way GET /api/pal/content/vocabulary drives the PAL
     * authoring console. A new authoring type appears in the UI without a React change —
     * which is the actual test of whether this was consolidated or merely moved.
     */
    public function vocabulary(): JsonResponse
    {
        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'data' => $this->authoring->vocabulary(),
        ], 200);
    }

    /**
     * POST /api/lms/content/author
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content_type'    => 'required|string',
            'mode'            => 'required|string|in:generate,upload',
            'chapter_id'      => 'nullable|integer',
            'subject_id'      => 'nullable|integer',
            'standard_id'     => 'nullable|integer',
            'concept_id'      => 'nullable|integer',
            'title'           => 'nullable|string|max:255',
            'prompt'          => 'nullable|string',
            'slide_count'     => 'nullable|integer|min:1|max:60',
            'idempotency_key' => 'nullable|string|max:64',
            // The overlay pointer: this new item EXTENDS a platform item rather than
            // replacing it. First writer of derived_from_entity_id anywhere.
            'derived_from_entity_id' => 'nullable|integer',
            'file'            => 'nullable|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Validation failed.',
                'errors' => $validator->errors()->messages(),
            ], 422);
        }

        $type = (string) $request->input('content_type');

        if (! in_array($type, $this->registry->keys(), true)) {
            return response()->json([
                'status_code' => 0,
                'message' => sprintf('Unknown content_type "%s".', $type),
                'allowed' => $this->registry->keys(),
            ], 422);
        }

        /** @var array<string,mixed>|null $auth */
        $auth = $request->attributes->get('lms_auth');

        if ($auth === null) {
            // Reachable only while lms.auth is in warn-only mode. Authoring writes rows
            // that must carry an owner, so unlike a read this cannot proceed without one.
            return response()->json([
                'status_code' => 0,
                'message' => 'Authoring requires an authenticated session.',
            ], 401);
        }

        try {
            $result = $this->authoring->author(
                $validator->validated() + ['content_type' => $type],
                $auth,
                $request->file('file')
            );
        } catch (Throwable $e) {
            // One error shape. The three legacy paths return a 500, a 502, a JSON error
            // body or an empty string depending on which you hit.
            return response()->json([
                'status_code' => 0,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status_code' => 1,
            'message' => $result['idempotent_replay'] ? 'Already authored.' : 'SUCCESS',
            'data' => $result,
        ], $result['idempotent_replay'] ? 200 : 201);
    }
}
