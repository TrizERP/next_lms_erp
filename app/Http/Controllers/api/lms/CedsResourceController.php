<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\Ceds\CedsFitService;
use App\Services\Ceds\CedsScope;
use App\Services\Ceds\ResourceResolver;
use App\Services\Ceds\ResourceRowLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Teaching method -> resource formats: READ ONLY.
 *
 * GET with a chapter (and optionally a teaching method P1-P8) returns, for each of the six
 * formats D1-D6, how well it fits the method (S/P/W, with a one-line warning and alternatives
 * for a weak pairing) and what already exists for the chapter. It stores nothing, generates
 * nothing and adapts nothing; generation and adaptation are reported as disabled.
 *
 * TENANCY. The school comes from the verified session (api.session), never from the request.
 * The caller must be able to see the chapter, and only rows of the tenants that school may
 * read are looked at (see ResourceRowLoader). The route is meant to sit behind `api.session`
 * and `staff.only`, like the other teacher-side LMS endpoints.
 */
class CedsResourceController extends Controller
{
    public function __construct(
        private CedsFitService $fit,
        private ResourceRowLoader $loader,
        private ResourceResolver $resolver,
        private CedsScope $scope,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chapter_id' => 'required|integer|min:1',
            'pedagogy' => ['nullable', 'string', Rule::in(array_keys($this->fit->pedagogies()))],
        ]);

        $tenant = (int) $request->session()->get('sub_institute_id');
        if ($tenant <= 0) {
            return response()->json(['status_code' => 0, 'message' => 'No school in this session.'], 403);
        }

        $tenants = $this->loader->visibleTenants($tenant);
        $chapter = $this->loader->visibleChapter((int) $data['chapter_id'], $tenants);
        if ($chapter === null) {
            return response()->json(['status_code' => 0, 'message' => 'Chapter not found.'], 404);
        }
        // Switched on for Std 9 Science, Chapter 1 only (config ceds_fit_matrix.scope). Checked after visibility so
        // a chapter this school cannot see still reads as "not found".
        if (!$this->scope->allows($chapter)) {
            return response()->json(['status_code' => 0, 'message' => $this->scope->message()], 403);
        }


        $rows = $this->loader->load((int) $chapter->id, $tenants);
        $resolved = $this->resolver->resolve((int) $chapter->id, $rows['content'], $rows['labs'], $tenants, $this->fit->formats());

        $pedagogy = $data['pedagogy'] ?? null;
        $formats = [];
        foreach ($pedagogy !== null ? $this->fit->formatsForPedagogy($pedagogy) : array_map(fn ($d) => ['format' => $d], array_keys($this->fit->formats())) as $fit) {
            $r = $resolved[$fit['format']];
            unset($r['can_generate']);
            // `note` already means the fit qualifier ("suits the explain phase only"); keep the resolver's apart.
            $r['resolver_note'] = $r['note'];
            unset($r['note']);
            // D1 is met by existing resources; the places they fall short are recorded in config, not filled.
            if ($fit['format'] === 'D1') {
                $r['gaps'] = (array) config('ceds_fit_matrix.d1_known_gaps', []);
            }
            $formats[] = $fit + $r;
        }

        return response()->json([
            'status_code' => 1,
            'chapter' => ['id' => (int) $chapter->id, 'name' => $chapter->chapter_name],
            'pedagogies' => $this->fit->pedagogies(),
            'pedagogy' => $pedagogy,
            'period_minutes' => $this->fit->defaultPeriodMinutes(),
            'formats' => $formats,
            // Version 1 shows and reuses what is stored. Nothing is generated or adapted from here.
            'generation' => ['enabled' => false, 'adapt_enabled' => false],
        ]);
    }
}
