<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\lms\Prayogshala\PrayogshalaService;
use App\Services\lms\Simulation\SimulationAssetService;
use App\Services\lms\Simulation\SimulationException;
use App\Services\lms\Simulation\SimulationLimits;
use App\Services\lms\Simulation\SimulationLinks;
use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simulation Learning inside Prayogshala: the gallery, the player's data, the concept links, and what the platform tenant's staff may add, change
 * and remove. Ported from hp_erp's LmsSimulationController with K12's tenancy instead of hp_erp's per-organisation gallery.
 *
 *   GET    lms/prayogshala/simulations                            list (filters: chapter_id, topic_id, concept_id, standard_id, subject_id, q)
 *   GET    lms/prayogshala/simulations/{id}                       the card, the current specification, the asset credits, the links
 *   GET    lms/prayogshala/simulations/{id}/assets/{sha256}       the bytes of a file the current specification names
 *   GET    lms/prayogshala/simulations/{id}/links                 the links
 *   POST   lms/prayogshala/simulations/{id}/progress              the caller's own progress
 *   GET    lms/prayogshala/simulations/limits                     (write) what an author may send
 *   GET    lms/prayogshala/simulations/concepts                   (write) the concept picker of the link editor
 *   GET    lms/prayogshala/simulations/{id}/versions              (write)
 *   POST   lms/prayogshala/simulations/assets                     (write) upload a model, image or HDR sky
 *   POST   lms/prayogshala/simulations                            (write) add
 *   PUT    lms/prayogshala/simulations/{id}                       (write) a new version and/or card fields
 *   DELETE lms/prayogshala/simulations/{id}                       (write) soft delete
 *   POST   lms/prayogshala/simulations/{id}/restore               (write)
 *   PUT    lms/prayogshala/simulations/{id}/links                 (write) replace the link set
 *   POST   lms/prayogshala/simulations/{id}/links/{linkId}/review (write) publish / unpublish one link
 *
 * TENANCY. Every route sits behind `api.session`: the tenant and the user are the verified token's, never the request body's. READ is open to any
 * authenticated role over PrayogshalaService::visibleTenants() (own tenant, plus the platform tenant for an is_Lms tenant). A LEARNER (a student
 * or parent token) sees a simulation only when it is published AND linked through at least one published link; staff also see unlinked ones and
 * links in review; only an author also sees drafts. WRITE needs the route gate (`lms.auth` + `perm:lms.content,create`) AND is checked again here:
 * not a learner, and the session tenant must be one of config lms_content.platform_sub_institute_ids. Any other tenant gets 403 on every write and
 * sees every row with `editable: false`.
 *
 * Responses are { success, data, message }; an error adds `errors` and `problems` (the same sentences, as hp_erp's controller does).
 */
class PrayogshalaSimulationApiController extends Controller
{
    /** Profiles that are always a learner's, whatever else the token says. */
    private const LEARNER_PROFILES = ['student', 'parent'];

    public function __construct(
        private readonly SimulationStore $store,
        private readonly SimulationAssetService $assets,
        private readonly SimulationLinks $links,
        private readonly PrayogshalaService $service,
    ) {
    }

    // ---------------------------------------------------------------- reading

    /** GET /lms/prayogshala/simulations */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        if ($tenant <= 0) {
            return $this->unauthenticated();
        }
        $validator = Validator::make($request->query(), [
            'chapter_id' => 'nullable|integer|min:1', 'topic_id' => 'nullable|integer|min:1', 'concept_id' => 'nullable|integer|min:1',
            'standard_id' => 'nullable|integer|min:1', 'subject_id' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->invalid(new SimulationException(collect($validator->errors()->all())->values()->all()));
        }
        $filters = [];
        foreach (SimulationStore::LINK_FILTERS as $key) {
            $filters[$key] = $request->query($key) === null || $request->query($key) === '' ? null : (int) $request->query($key);
        }

        $staff = ! $this->isLearner($request);
        $author = $this->canAuthor($request);
        $rows = $this->store->visible($this->service->visibleTenants($tenant), $staff, $request->query('q'), $filters, $author);
        $ids = $rows->pluck('id')->map(fn ($i) => (int) $i)->all();
        $progress = $this->store->progressMap($tenant, $this->userId($request), $ids);
        $links = $this->links->forSimulations($ids, ! $staff);

        return $this->ok([
            'simulations' => $rows->map(fn ($s) => $this->card($s, $request, $progress[$s->id] ?? null, $links[(int) $s->id] ?? []))->values(),
            'categories' => $this->store->categories($rows),
            'total' => $rows->count(),
            'can_manage' => $author,
        ]);
    }

    /** GET /lms/prayogshala/simulations/{id}: the card, the current specification (staff may name a `version`), the asset credits and the links. */
    public function show(Request $request, $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        if ($tenant <= 0) {
            return $this->unauthenticated();
        }
        $sim = $this->visibleSimulation($request, (int) $id, $tenant);
        if (! $sim) {
            return $this->notFound();
        }
        $staff = ! $this->isLearner($request);
        $json = $this->store->specJson($sim, $this->wantedVersion($request, $staff));
        if ($json === null) {
            return $this->notFound('That version does not exist.');
        }
        $progress = $this->store->progressMap($tenant, $this->userId($request), [(int) $sim->id])[$sim->id] ?? null;
        $spec = json_decode($json);

        return $this->ok($this->card($sim, $request, $progress, $this->links->forSimulation((int) $sim->id, ! $staff), true) + [
            'spec' => $spec,
            'assets' => $this->store->assetCredits($sim, $this->store->assetHashes($spec)),
        ]);
    }

    /**
     * GET /lms/prayogshala/simulations/{id}/assets/{sha256}: the file of a model, image or HDR sky the simulation's CURRENT specification names,
     * streamed from the private bucket. The browser never reads the bucket. It answers only for a hash this simulation (one the caller may see, as
     * in show) names in `assets.models` and that is a registered asset of the simulation's owner or of the platform; anything else, a missing
     * file included, is the usual 404. An image also gets `Content-Security-Policy: default-src 'none'; sandbox` and an inline Content-Disposition,
     * on top of the `nosniff` every file gets.
     */
    public function asset(Request $request, $id, string $sha256): Response
    {
        $tenant = $this->tenant($request);
        if ($tenant <= 0) {
            return $this->unauthenticated();
        }
        $sim = $this->visibleSimulation($request, (int) $id, $tenant);
        $json = $sim ? $this->store->specJson($sim) : null;
        $named = $json === null ? [] : $this->store->assetHashes(json_decode($json));
        $row = in_array($sha256, $named, true) ? $this->store->assetRows($sim, [$sha256])->get($sha256) : null;
        if (! $row) {
            return $this->notFound('That file does not exist.');
        }

        $etag = '"' . $row->sha256 . '"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];
        if (array_intersect($request->getETags(), ['*', $etag, 'W/' . $etag]) !== []) {
            return response('', 304, $headers);
        }

        try {
            $stream = Storage::disk(SimulationStore::ASSET_DISK)->readStream($row->storage_path);
        } catch (FilesystemException) {
            $stream = null;
        }
        if (! is_resource($stream)) {
            return $this->notFound('That file does not exist.');
        }

        // an image is shown by the page, so on top of "nosniff" it carries what stops a browser doing anything with it but showing a picture
        $image = in_array($row->kind, ['image', 'hdr'], true) ? ['Content-Security-Policy' => "default-src 'none'; sandbox", 'Content-Disposition' => 'inline; filename="asset"'] : [];

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers + $image + ['Content-Type' => $row->mime, 'Content-Length' => (string) (int) $row->bytes]);
    }

    /** GET /lms/prayogshala/simulations/{id}/versions (write) */
    public function versions(Request $request, $id): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }
        $sim = $this->store->owned((int) $id, $this->tenant($request));
        if (! $sim) {
            return $this->notFound();
        }

        return $this->ok(['current' => (int) $sim->current_version, 'versions' => $this->store->versions($sim->id)]);
    }

    /** GET /lms/prayogshala/simulations/limits (write): what the platform holds an author to, as data. See SimulationLimits. */
    public function limits(Request $request): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }

        return $this->ok(SimulationLimits::data() + [
            'use' => $this->assets->usage($this->tenant($request)),
            'server' => ['uploadMaxBytes' => SimulationLimits::serverUploadBytes()],
        ]);
    }

    /** GET /lms/prayogshala/simulations/{id}/links: the links (a learner's list holds the published ones only). */
    public function links(Request $request, $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        if ($tenant <= 0) {
            return $this->unauthenticated();
        }
        $sim = $this->visibleSimulation($request, (int) $id, $tenant);
        if (! $sim) {
            return $this->notFound();
        }

        return $this->ok(['links' => $this->links->forSimulation((int) $sim->id, $this->isLearner($request))]);
    }

    /** GET /lms/prayogshala/simulations/concepts?chapter_id=&q= (write): the concept picker of the link editor, the platform tenant's concepts only. */
    public function concepts(Request $request): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }
        $validator = Validator::make($request->query(), ['chapter_id' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100']);
        if ($validator->fails()) {
            return $this->invalid(new SimulationException(collect($validator->errors()->all())->values()->all()));
        }

        return $this->ok(['concepts' => $this->links->pickConcepts(
            $request->filled('chapter_id') ? (int) $request->query('chapter_id') : null,
            is_string($request->query('q')) ? $request->query('q') : null
        )]);
    }

    // ---------------------------------------------------------------- writing

    /**
     * POST /lms/prayogshala/simulations/assets (write; 30 a minute for each person). Multipart: `file` (a binary glTF model, a PNG, JPEG or WebP
     * image, or a Radiance HDR sky), `licence` (80 characters at most) and `credit` (300) which are mandatory, `attested` (1: the person may use the
     * file under that licence) which is mandatory, and `source` (an https address) and `name` (120) which are not. What the file is comes from its
     * first bytes, not its name; a model's contents are inspected before anything is stored, and the tenant's quota is respected. Answers
     * { sha256, kind, mime, bytes, created, name, licence, credit, source_url }: 201 when the file is new, 200 when this tenant already had it. A
     * problem is { message, problems[] } with 422, or 413 when the file is too big, or 503 when it could not be stored.
     */
    public function uploadAsset(Request $request): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }

        try {
            $file = $request->file('file');
            if (! $file instanceof UploadedFile) {
                // PHP throws the whole body away when it is larger than post_max_size, so the file is "missing" for a different reason then
                $limit = SimulationLimits::iniBytes((string) ini_get('post_max_size'));
                if ($limit > 0 && (int) $request->server('CONTENT_LENGTH') > $limit) {
                    throw new SimulationException(['The upload is larger than this server accepts (' . round($limit / 1048576, 1) . ' MB). Send a smaller file, or ask your administrator to raise the limit.'], 413);
                }
                throw new SimulationException(['"file" is required: send the 3D model or image as the multipart field "file".']);
            }
            if (! $file->isValid()) {
                $tooBig = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
                throw new SimulationException([$tooBig ? 'The file is larger than this server accepts (' . round((SimulationLimits::serverUploadBytes() ?? 0) / 1048576, 1) . ' MB). Send a smaller file, or ask your administrator to raise the limit.' : 'The upload did not arrive: ' . $file->getErrorMessage()], $tooBig ? 413 : 422);
            }

            $details = [];
            $wrong = [];
            foreach (['licence', 'credit', 'source', 'name'] as $key) {
                $value = $request->input($key);
                if ($value !== null && ! is_string($value)) {
                    $wrong[] = "\"{$key}\" must be text.";
                }
                $details[$key] = is_string($value) ? $value : null;
            }
            if ($wrong !== []) {
                throw new SimulationException($wrong);
            }
            // the person confirms they may use this file under the licence they named; the row (who, when) is the record of it
            if (! filter_var($request->input('attested'), FILTER_VALIDATE_BOOLEAN)) {
                throw new SimulationException(['Confirm that you may use this file under the licence you named: send "attested" as 1.']);
            }

            $result = $this->assets->upload($this->tenant($request), $this->userId($request), (string) $file->getRealPath(), $file->getClientOriginalName(), $details);
        } catch (SimulationException $e) {
            return $this->invalid($e, 'file');
        }

        return $this->ok($result, $result['created'] ? 201 : 200);
    }

    /**
     * POST /lms/prayogshala/simulations (write). Body: { "spec": { ...a g2g.sim/1 or g2g.sim/2 specification... }, "status"?, "category"?,
     * "tags"?, "cover_url"?, "difficulty"?, "sort_order"?, "note"? }. The owner is the session tenant. The specification is read from the raw
     * body, not from the parsed input, so `{}` stays `{}`.
     */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }

        try {
            [$specJson, $options] = $this->bodyParts($request);
            $sim = $this->store->create($this->tenant($request), $this->userId($request), $specJson, $options);
        } catch (SimulationException $e) {
            return $this->invalid($e);
        }

        return $this->ok($this->card($sim, $request, null, []), 201);
    }

    /**
     * PUT /lms/prayogshala/simulations/{id} (write). A new "spec" becomes a new version; the card fields (category, tags, cover_url, difficulty,
     * status, sort_order) change the card only.
     */
    public function update(Request $request, $id): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }
        $sim = $this->store->owned((int) $id, $this->tenant($request));
        if (! $sim) {
            return $this->notFound();
        }
        $userId = $this->userId($request);

        try {
            [$specJson, $options] = $this->bodyParts($request, false);
            if ($specJson !== null) {
                $sim = $this->store->addVersion($sim, $userId, $specJson, $options['note'] ?? null);
            }
            $sim = $this->store->updateCard($sim, $userId, array_intersect_key($options, array_flip(['category', 'tags', 'cover_url', 'difficulty', 'status', 'sort_order'])));
        } catch (SimulationException $e) {
            return $this->invalid($e);
        }

        return $this->ok($this->card($sim, $request, null, $this->links->forSimulation((int) $sim->id, false)));
    }

    /** DELETE /lms/prayogshala/simulations/{id} (write): removes it from every gallery; it can be restored. */
    public function destroy(Request $request, $id): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }
        $sim = $this->store->owned((int) $id, $this->tenant($request));
        if (! $sim) {
            return $this->notFound();
        }
        $this->store->softDelete($sim, $this->userId($request));

        return $this->ok(['id' => (int) $sim->id, 'deleted' => true]);
    }

    /** POST /lms/prayogshala/simulations/{id}/restore (write): the undo of a delete. */
    public function restore(Request $request, $id): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }
        $tenant = $this->tenant($request);
        $sim = $this->store->owned((int) $id, $tenant, true);
        if (! $sim) {
            return $this->notFound();
        }
        try {
            $this->store->restore($sim, $this->userId($request));
        } catch (SimulationException $e) {
            return $this->invalid($e);
        }

        return $this->ok($this->card($this->store->owned((int) $id, $tenant), $request, null, $this->links->forSimulation((int) $id, false)));
    }

    /**
     * PUT /lms/prayogshala/simulations/{id}/links (write). Body { links: [ { concept_id, role?, citation?, reason?, sort_order? } ] } replaces the
     * link set. The server resolves each concept and COPIES chapter, topic, standard and subject from it: the client supplies none of them. An unknown
     * concept, or one that is not the platform tenant's, is a 422 naming its id. New links start in `review`; an unchanged link keeps its state.
     */
    public function putLinks(Request $request, $id): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }
        $sim = $this->store->owned((int) $id, $this->tenant($request));
        if (! $sim) {
            return $this->notFound();
        }
        $wanted = $request->input('links');
        if (! is_array($wanted) || ($wanted !== [] && ! array_is_list($wanted))) {
            return $this->invalid(new SimulationException(['"links" is required: a list of { concept_id, role?, citation?, reason?, sort_order? }.']), 'links');
        }
        try {
            $saved = $this->links->replace($sim, $wanted, $this->userId($request));
        } catch (SimulationException $e) {
            return $this->invalid($e, 'links');
        }

        return $this->ok(['links' => $saved]);
    }

    /** POST /lms/prayogshala/simulations/{id}/links/{linkId}/review (write). Body { status: "review"|"published" }; stamps who and when. */
    public function reviewLink(Request $request, $id, $linkId): JsonResponse
    {
        if ($denied = $this->writeGate($request)) {
            return $denied;
        }
        $sim = $this->store->owned((int) $id, $this->tenant($request));
        if (! $sim) {
            return $this->notFound();
        }
        try {
            $link = $this->links->review($sim, (int) $linkId, $request->input('status'), $this->userId($request));
        } catch (SimulationException $e) {
            return $this->invalid($e, 'status');
        }

        return $link === null ? $this->notFound('That link does not exist.') : $this->ok(['link' => $link]);
    }

    /**
     * POST /lms/prayogshala/simulations/{id}/progress (any authenticated role). Body: { percent, step, status: "in_progress"|"completed", result? }.
     * Only ever about the caller: the user and the tenant are the token's.
     */
    public function progress(Request $request, $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        if ($tenant <= 0) {
            return $this->unauthenticated();
        }
        $sim = $this->visibleSimulation($request, (int) $id, $tenant);
        if (! $sim) {
            return $this->notFound();
        }
        $row = $this->store->recordProgress($sim, $tenant, $this->userId($request), [
            'percent' => $request->input('percent'),
            'step' => $request->input('step'),
            'status' => $request->input('status'),
            'result' => $request->input('result'),
        ]);

        return $this->ok(['status' => $row->status, 'percent' => (int) $row->percent, 'step' => (int) $row->step, 'completed_at' => $row->completed_at]);
    }

    // ---------------------------------------------------------------- who, and what they may see

    private function tenant(Request $request): int
    {
        return (int) $request->session()->get('sub_institute_id');
    }

    private function userId(Request $request): int
    {
        return (int) $request->session()->get('user_id');
    }

    /** A learner: a student token, or a student or parent profile. Everyone else is staff. */
    private function isLearner(Request $request): bool
    {
        return (bool) $request->session()->get('is_student')
            || in_array(strtolower(trim((string) $request->session()->get('user_profile_name'))), self::LEARNER_PROFILES, true);
    }

    /** May write simulations: not a learner, and the session tenant is one of the platform tenants. (The route also runs lms.auth and perm.) */
    private function canAuthor(Request $request): bool
    {
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));

        return ! $this->isLearner($request) && in_array($this->tenant($request), $platform, true);
    }

    /** The 403 (or 401) for a write the caller may not make; null when it may. */
    private function writeGate(Request $request): ?JsonResponse
    {
        if ($this->tenant($request) <= 0) {
            return $this->unauthenticated();
        }

        return $this->canAuthor($request) ? null : $this->forbidden();
    }

    private function visibleSimulation(Request $request, int $id, int $tenant): ?object
    {
        return $this->store->find($id, $this->service->visibleTenants($tenant), ! $this->isLearner($request), $this->canAuthor($request));
    }

    /** A version other than the current one may be asked for only by staff. */
    private function wantedVersion(Request $request, bool $staff): ?int
    {
        $v = $request->query('version');

        return $staff && is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    // ---------------------------------------------------------------- shapes

    /**
     * The card (the store's), with its links and whether THIS caller may edit it: `editable` is true only for an author, in the owning tenant.
     *
     * @param  list<array<string, mixed>>  $links
     * @return array<string, mixed>
     */
    private function card(object $sim, Request $request, ?object $progress, array $links, bool $withSnapshot = false): array
    {
        return $this->store->card($sim, $progress, $withSnapshot) + [
            'links' => $links,
            'editable' => $this->canAuthor($request) && (int) $sim->sub_institute_id === $this->tenant($request),
        ];
    }

    /**
     * The "spec" of the raw body as JSON text, and the other fields as options.
     *
     * @return array{0: ?string, 1: array<string, mixed>}
     */
    private function bodyParts(Request $request, bool $specRequired = true): array
    {
        $raw = $request->getContent();
        // the specification is at most 1 MB and the other fields are small: a body far over that is refused before anything decodes it
        if (strlen($raw) > SimulationStore::MAX_SPEC_BYTES + 100000) {
            throw new SimulationException(['The request is larger than a simulation may be: the specification is at most 1 MB.'], 413);
        }
        try {
            $body = $raw === '' ? new \stdClass() : json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SimulationException(['The request body is not valid JSON.']);
        }
        if ($body === []) {
            $body = new \stdClass();   // an empty JSON object arrives as an empty array
        }
        if (! $body instanceof \stdClass) {
            throw new SimulationException(['The request body must be a JSON object.']);
        }

        $spec = null;
        if (property_exists($body, 'spec') && $body->spec !== null) {
            $spec = is_string($body->spec) ? $body->spec : json_encode($body->spec, SimulationStore::JSON_FLAGS);
        }
        if ($spec === null && $specRequired) {
            throw new SimulationException(['"spec" is required: the simulation specification.']);
        }

        // Each option is text (or null), the order is a number and the tags are words: anything else is said so here, as a list of problems,
        // instead of being left to fail inside the store as a server error.
        $options = [];
        $problems = [];
        foreach (['status', 'category', 'cover_url', 'difficulty', 'note'] as $key) {
            if (property_exists($body, $key)) {
                $options[$key] = $body->{$key};
                if ($options[$key] !== null && ! is_string($options[$key])) {
                    $problems[] = "\"{$key}\" must be text.";
                }
            }
        }
        if (property_exists($body, 'sort_order')) {
            $options['sort_order'] = $body->sort_order;
            if ($options['sort_order'] !== null && ! is_numeric($options['sort_order'])) {
                $problems[] = '"sort_order" must be a number.';
            }
        }
        if (property_exists($body, 'tags')) {
            $tags = $options['tags'] = $body->tags;
            $isWord = fn ($t) => is_string($t) || is_int($t) || is_float($t);
            if ($tags !== null && ! is_string($tags) && ! (is_array($tags) && count(array_filter($tags, $isWord)) === count($tags))) {
                $problems[] = '"tags" must be a list of words.';
            }
        }
        if ($problems !== []) {
            throw new SimulationException($problems);
        }

        return [$spec, $options];
    }

    // ---------------------------------------------------------------- responses

    /** @param  mixed  $data */
    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'message' => 'SUCCESS'], $status);
    }

    private function notFound(string $message = 'Simulation not found.'): JsonResponse
    {
        return response()->json(['success' => false, 'data' => null, 'message' => $message], 404);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['success' => false, 'data' => null, 'message' => 'You do not have permission to do that. Only staff of the platform tenant can add or change simulations.'], 403);
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json(['success' => false, 'data' => null, 'message' => 'Authentication is required.'], 401);
    }

    /** `$field` is what `errors` is keyed by: the specification for a specification, the file for an upload. */
    private function invalid(SimulationException $e, string $field = 'spec'): JsonResponse
    {
        // `errors` is the shape the front end's ApiError already reads; `problems` is the same list under its own name
        return response()->json(['success' => false, 'data' => null, 'message' => $e->getMessage(), 'errors' => [$field => $e->problems], 'problems' => $e->problems], $e->getCode() >= 400 ? $e->getCode() : 422);
    }
}
