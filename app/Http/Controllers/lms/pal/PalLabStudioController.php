<?php

namespace App\Http\Controllers\lms\pal;

use App\Http\Controllers\Controller;
use App\Services\PAL\LabSpec\LabChapterContext;
use App\Services\PAL\LabSpec\LabSpecGenerator;
use App\Services\PAL\LabSpec\LabSpecPreview;
use App\Services\PAL\LabSpec\LabSpecRepository;
use App\Services\PAL\LabSpec\LabSpecValidator;
use App\Services\PAL\LabSpec\PalLabAuthor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lab Studio: where a teacher or admin prepares the virtual labs students use in the Application Lab.
 *
 *   list versions · generate a draft with the AI · edit (saves a NEW version) · preview (the working experiment and
 *   the expected results and key) · validate · approve · reject
 *
 * Every route here is for teachers and admins only (PalLabAuthor); a student session, however it got here, is refused
 * with 403. Students reach labs only through palController::labSpecCurrent, which serves approved specifications
 * with the marker's key removed.
 */
class PalLabStudioController extends Controller
{
    /** @return array{user_id:int,sub_institute_id:int} */
    private function author(): array
    {
        $userId = (int) session()->get('user_id');
        if ($userId <= 0) {
            abort(response()->json(['status' => 0, 'message' => 'Please sign in.'], 401));
        }
        $profileId = (int) session()->get('user_profile_id');
        $parent = $profileId > 0 ? DB::table('tbluserprofilemaster')->where('id', $profileId)->value('parent_id') : null;
        $allowed = PalLabAuthor::allows(
            (bool) session()->get('is_student'),
            (int) session()->get('is_admin'),
            (string) session()->get('user_profile_name', ''),
            $parent === null ? null : (int) $parent
        );
        if (! $allowed) {
            abort(response()->json(['status' => 0, 'message' => 'Only teachers and admins can prepare labs.'], 403));
        }

        return ['user_id' => $userId, 'sub_institute_id' => (int) session()->get('sub_institute_id')];
    }

    /** @return array<string,mixed> */
    private function detail(array $lab, array $validation = null): array
    {
        $spec = $lab['spec'];

        return [
            'id' => $lab['id'] ?? null, 'lab_key' => $lab['lab_key'], 'version' => $lab['version'], 'status' => $lab['status'] ?? 'approved',
            'source' => $lab['source'] ?? 'teacher', 'spec' => $spec,
            'validation' => $validation ?? LabSpecValidator::validate($spec),
            'preview' => LabSpecValidator::validate($spec)['errors'] === [] ? LabSpecPreview::build($spec) : null,
            'scope_level' => $spec['scope_level'] ?? 'concepts',
            'reject_reason' => $lab['reject_reason'] ?? null,
        ];
    }

    /** Versions of the labs for a chapter, and the chapter's concepts (GET). */
    public function index(Request $request, $chapterId): JsonResponse
    {
        $a = $this->author();
        $ctx = LabChapterContext::for((int) $chapterId, $a['sub_institute_id']);
        if ($ctx === null) {
            return response()->json(['status' => 0, 'message' => 'Chapter not found.'], 404);
        }

        return response()->json([
            'status' => 1,
            'storage_ready' => LabSpecRepository::tableReady(),
            'chapter' => ['id' => (int) $chapterId, 'name' => $ctx['chapter'], 'subject' => $ctx['subject'], 'standard' => $ctx['standard'], 'concepts' => $ctx['concepts']],
            'versions' => LabSpecRepository::listForChapter((int) $chapterId, $a['sub_institute_id']),
        ]);
    }

    /** One version with its validation, the working preview data and the key (GET ?id= or ?key=&version=). */
    public function show(Request $request): JsonResponse
    {
        $a = $this->author();
        if ($request->filled('id')) {
            $row = LabSpecRepository::row((int) $request->input('id'), $a['sub_institute_id']);
            if ($row === null) {
                return response()->json(['status' => 0, 'message' => 'That lab version was not found.'], 404);
            }

            return response()->json(['status' => 1, 'lab' => $this->detail($row)]);
        }
        $builtin = LabSpecRepository::builtins()[(string) $request->input('key')] ?? null;
        if ($builtin === null) {
            return response()->json(['status' => 0, 'message' => 'That lab was not found.'], 404);
        }

        return response()->json(['status' => 1, 'lab' => $this->detail(['id' => null, 'lab_key' => $builtin['lab_key'], 'version' => 1, 'status' => 'approved', 'source' => 'builtin', 'spec' => $builtin])]);
    }

    /** Checks a specification without saving anything (POST spec_json). Live feedback for the editor. */
    public function validateSpec(Request $request): JsonResponse
    {
        $this->author();
        $spec = LabSpecGenerator::decode((string) $request->input('spec_json'));
        if ($spec === null) {
            return response()->json(['status' => 1, 'validation' => ['errors' => ['That is not valid JSON.'], 'warnings' => [], 'stats' => []], 'preview' => null]);
        }
        $v = LabSpecValidator::validate($spec);

        return response()->json(['status' => 1, 'validation' => $v, 'preview' => $v['errors'] === [] ? LabSpecPreview::build($spec) : null]);
    }

    /** Drafts a lab for the chapter with the AI, saved as a DRAFT (POST chapter_id, note). */
    public function generate(Request $request): JsonResponse
    {
        $a = $this->author();
        $chapterId = (int) $request->input('chapter_id');
        if (! LabSpecRepository::tableReady()) {
            return response()->json(['status' => 0, 'message' => 'Lab storage is not ready yet (the migration has not been applied).'], 409);
        }
        $ctx = LabChapterContext::for($chapterId, $a['sub_institute_id']);
        if ($ctx === null || $ctx['concepts'] === []) {
            return response()->json(['status' => 0, 'message' => 'This chapter has no concepts to build a lab from.'], 422);
        }
        // Which concepts the lab is for, and whether it is a whole-chapter lab (offered only once every concept is studied).
        $level = $request->input('level') === 'chapter' ? 'chapter' : 'concepts';
        $out = app(LabSpecGenerator::class)->generate($chapterId, $ctx, $a['sub_institute_id'], (string) $request->input('note', ''), array_map('strval', (array) $request->input('concepts', [])), $level);
        if ($out['spec'] === null) {
            return response()->json(['status' => 0, 'message' => $out['error'] ?? 'The AI could not draft a lab.'], 502);
        }
        $row = LabSpecRepository::saveDraft($out['spec'], 'ai', $a['user_id'], $a['sub_institute_id'], $out['validation']);

        return response()->json(['status' => 1, 'attempts' => $out['attempts'], 'lab' => $this->detail($row, $out['validation'])]);
    }

    /**
     * Saves an edited specification as a NEW draft version (POST spec_json). An approved version is never changed
     * in place, so every change to a live lab needs approving again; students keep the approved one meanwhile.
     */
    public function save(Request $request): JsonResponse
    {
        $a = $this->author();
        if (! LabSpecRepository::tableReady()) {
            return response()->json(['status' => 0, 'message' => 'Lab storage is not ready yet (the migration has not been applied).'], 409);
        }
        $spec = LabSpecGenerator::decode((string) $request->input('spec_json'));
        if ($spec === null || empty($spec['lab_key']) || empty($spec['chapter_ids'][0])) {
            return response()->json(['status' => 0, 'message' => 'That is not a lab specification.'], 422);
        }
        $v = LabSpecValidator::validate($spec);
        $row = LabSpecRepository::saveDraft($spec, 'teacher', $a['user_id'], $a['sub_institute_id'], $v);

        return response()->json(['status' => 1, 'lab' => $this->detail($row, $v)]);
    }

    /** Approves a draft (POST). Re-validated, mapped to the chapter's concepts, and the previous approved version is retired. */
    public function approve(Request $request, $id): JsonResponse
    {
        $a = $this->author();
        $row = LabSpecRepository::row((int) $id, $a['sub_institute_id']);
        if ($row === null) {
            return response()->json(['status' => 0, 'message' => 'That lab version was not found.'], 404);
        }
        $names = LabChapterContext::conceptNames($row['chapter_id'], $a['sub_institute_id']);
        $result = LabSpecRepository::approve((int) $id, $a['user_id'], $a['sub_institute_id'], $names);
        if (! $result['ok']) {
            return response()->json(['status' => 0, 'message' => 'It cannot be approved yet.', 'errors' => $result['errors']], 422);
        }

        return response()->json(['status' => 1, 'lab' => $this->detail(LabSpecRepository::row((int) $id, $a['sub_institute_id']))]);
    }

    /** Rejects a draft (POST reason). */
    public function reject(Request $request, $id): JsonResponse
    {
        $a = $this->author();
        if (! LabSpecRepository::reject((int) $id, $a['sub_institute_id'], (string) $request->input('reason', ''))) {
            return response()->json(['status' => 0, 'message' => 'Only a draft can be rejected.'], 422);
        }

        return response()->json(['status' => 1]);
    }
}
