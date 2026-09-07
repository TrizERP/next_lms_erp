<?php

namespace App\Http\Controllers\G2gLms;

use App\Http\Controllers\Controller;
use App\Http\Controllers\G2gLms\Concerns\ResolvesLmsIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Trainers, vendors and integrations for Administration & Governance.
 *
 * Ported from hp_erp's `App\Http\Controllers\Api\LmsPartnerController`. All
 * three tables (`lms_trainers`, `lms_vendors`, `lms_integrations`) are new to
 * this codebase - created by this package's own migrations, matching the
 * source's column shapes (audit columns, timestamps, soft deletes) exactly,
 * so - unlike GovernanceController - these three CRUD blocks need no schema
 * adaptation.
 *
 * The one adaptation IS in `trainers()`'s session-count join: the source
 * reads `lms_virtual_classroom.trainer_id` / `.trainer_email` / `.trainer_name`,
 * none of which exist on this target's `lms_virtual_classroom` (confirmed by
 * reading its migration - it is the original K12 session table, unrelated to
 * hp_erp's). Package 2 is separately adding a nullable `trainer_id` column
 * there; this controller checks for it at request time via
 * `Schema::hasColumn()` so it starts working the moment that migration lands,
 * with no further change here. `trainer_email`/`trainer_name` are not being
 * added by any package, so the source's fuzzy name/email fallback match is
 * dropped - `unlinked_session_count` always reads 0 rather than silently
 * fabricating a count from columns that do not exist.
 */
class PartnerController extends Controller
{
    use ResolvesLmsIdentity;

    private function guardAdmin(Request $request)
    {
        $context = $this->lmsContext($request);
        if ($this->isLmsStaffAdmin($context)) {
            return null;
        }

        return $this->lmsError('Your profile is not permitted to manage partners.', 403);
    }

    private function invalid($validator)
    {
        return response()->json(['status' => false, 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
    }

    private function fail(\Throwable $e, string $message)
    {
        return response()->json(['status' => false, 'message' => $message, 'error' => $e->getMessage()], 500);
    }

    private function findScoped(string $table, $id, $subInstituteId)
    {
        return DB::table($table)->where('id', $id)->where('sub_institute_id', $subInstituteId)->whereNull('deleted_at')->first();
    }

    /** The caller's own user id - never the subject of a write. */
    private function actorId(Request $request): ?int
    {
        $userId = $this->lmsContext($request)['user_id'];

        return $userId ?: null;
    }

    /* ─── Trainers ─────────────────────────────────────────────────────────── */

    private function trainerRules(): array
    {
        return [
            'name'           => 'required|string|max:191',
            'email'          => 'nullable|email|max:191',
            'phone'          => 'nullable|string|max:50',
            'trainer_type'   => 'nullable|string|in:internal,external',
            'vendor_id'      => 'nullable|integer',
            'user_id_link'   => 'nullable|integer',
            'specialisation' => 'nullable|string|max:191',
            'bio'            => 'nullable|string|max:2000',
            'qualifications' => 'nullable|string|max:1000',
            'hourly_rate'    => 'nullable|numeric|min:0',
            'currency'       => 'nullable|string|max:10',
            'status'         => 'nullable|boolean',
        ];
    }

    private function trainerPayload(Request $request): array
    {
        return [
            'name'           => $request->input('name'),
            'email'          => $request->input('email'),
            'phone'          => $request->input('phone'),
            'trainer_type'   => $request->input('trainer_type', 'internal'),
            'vendor_id'      => $request->input('vendor_id') ?: null,
            'user_id'        => $request->input('user_id_link') ?: null,
            'specialisation' => $request->input('specialisation'),
            'bio'            => $request->input('bio'),
            'qualifications' => $request->input('qualifications'),
            'hourly_rate'    => $request->input('hourly_rate'),
            'currency'       => $request->input('currency'),
            'status'         => $request->boolean('status', true) ? 1 : 0,
        ];
    }

    /** GET /trainers */
    public function trainers(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        if (!$sid) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $query = DB::table('lms_trainers as t')
                ->leftJoin('lms_vendors as v', 'v.id', '=', 't.vendor_id')
                ->where('t.sub_institute_id', $sid)
                ->whereNull('t.deleted_at');

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('t.name', 'like', "%{$search}%")
                      ->orWhere('t.email', 'like', "%{$search}%")
                      ->orWhere('t.specialisation', 'like', "%{$search}%");
                });
            }
            if (($status = $request->input('status')) !== null && $status !== '') {
                $query->where('t.status', (int) $status);
            }
            if ($type = $request->input('trainer_type')) {
                $query->where('t.trainer_type', $type);
            }

            $trainers = $query->orderBy('t.name')->get([
                't.id', 't.name', 't.email', 't.phone', 't.trainer_type', 't.vendor_id',
                't.user_id', 't.specialisation', 't.bio', 't.qualifications',
                't.hourly_rate', 't.currency', 't.status', 't.created_at',
                'v.name as vendor_name',
            ])->map(function ($trainer) {
                $trainer->status = (int) $trainer->status;
                return $trainer;
            });

            // Session counts. Only the real trainer_id link is used - see the
            // class docblock for why the source's fuzzy name/email fallback
            // is dropped here.
            $hasTrainerId = Schema::hasColumn('lms_virtual_classroom', 'trainer_id');

            $linkedCounts = $hasTrainerId
                ? DB::table('lms_virtual_classroom')
                    ->where('sub_institute_id', $sid)
                    ->whereNotNull('trainer_id')
                    ->select('trainer_id', DB::raw('COUNT(*) as total'))
                    ->groupBy('trainer_id')->pluck('total', 'trainer_id')
                : collect();

            $trainers->transform(function ($trainer) use ($linkedCounts) {
                $matched = (int) ($linkedCounts[$trainer->id] ?? 0);
                $trainer->session_count = $matched;
                $trainer->linked_session_count = $matched;
                // Always 0 here - see class docblock (trainer_email/trainer_name
                // do not exist on this target's lms_virtual_classroom).
                $trainer->unlinked_session_count = 0;
                return $trainer;
            });

            return response()->json(['status' => true, 'data' => $trainers]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load trainers');
        }
    }

    /** POST /trainers */
    public function storeTrainer(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->trainerRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        try {
            $id = DB::table('lms_trainers')->insertGetId($this->trainerPayload($request) + [
                'sub_institute_id' => $sid,
                'created_by'       => $this->actorId($request),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Trainer added', 'data' => DB::table('lms_trainers')->find($id)], 201);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to add the trainer');
        }
    }

    /** PUT /trainers/{id} */
    public function updateTrainer(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->trainerRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        if (!$this->findScoped('lms_trainers', $id, $sid)) {
            return response()->json(['status' => false, 'message' => 'Trainer not found'], 404);
        }

        try {
            DB::table('lms_trainers')->where('id', $id)->update($this->trainerPayload($request) + [
                'updated_by' => $this->actorId($request),
                'updated_at' => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Trainer updated', 'data' => DB::table('lms_trainers')->find($id)]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to update the trainer');
        }
    }

    /** DELETE /trainers/{id} */
    public function destroyTrainer(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        if (!$this->findScoped('lms_trainers', $id, $sid)) {
            return response()->json(['status' => false, 'message' => 'Trainer not found'], 404);
        }

        try {
            DB::table('lms_trainers')->where('id', $id)->update([
                'deleted_at' => now(),
                'deleted_by' => $this->actorId($request),
            ]);

            return response()->json(['status' => true, 'message' => 'Trainer removed']);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to remove the trainer');
        }
    }

    /* ─── Vendors ──────────────────────────────────────────────────────────── */

    private function vendorRules(): array
    {
        return [
            'name'           => 'required|string|max:191',
            'vendor_code'    => 'nullable|string|max:100',
            'contact_person' => 'nullable|string|max:191',
            'email'          => 'nullable|email|max:191',
            'phone'          => 'nullable|string|max:50',
            'website'        => 'nullable|string|max:191',
            'address'        => 'nullable|string|max:1000',
            'service_type'   => 'nullable|string|max:50',
            'contract_start' => 'nullable|date',
            'contract_end'   => 'nullable|date|after_or_equal:contract_start',
            'contract_value' => 'nullable|numeric|min:0',
            'currency'       => 'nullable|string|max:10',
            'status'         => 'nullable|boolean',
            'notes'          => 'nullable|string|max:2000',
        ];
    }

    private function vendorPayload(Request $request): array
    {
        return [
            'name'           => $request->input('name'),
            'vendor_code'    => $request->input('vendor_code'),
            'contact_person' => $request->input('contact_person'),
            'email'          => $request->input('email'),
            'phone'          => $request->input('phone'),
            'website'        => $request->input('website'),
            'address'        => $request->input('address'),
            'service_type'   => $request->input('service_type'),
            'contract_start' => $request->input('contract_start'),
            'contract_end'   => $request->input('contract_end'),
            'contract_value' => $request->input('contract_value'),
            'currency'       => $request->input('currency'),
            'status'         => $request->boolean('status', true) ? 1 : 0,
            'notes'          => $request->input('notes'),
        ];
    }

    /** GET /vendors */
    public function vendors(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        if (!$sid) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $query = DB::table('lms_vendors')->where('sub_institute_id', $sid)->whereNull('deleted_at');

            if ($search = trim((string) $request->input('search', ''))) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('vendor_code', 'like', "%{$search}%")
                      ->orWhere('contact_person', 'like', "%{$search}%");
                });
            }
            if (($status = $request->input('status')) !== null && $status !== '') {
                $query->where('status', (int) $status);
            }

            $trainerCounts = DB::table('lms_trainers')
                ->where('sub_institute_id', $sid)->whereNull('deleted_at')->whereNotNull('vendor_id')
                ->select('vendor_id', DB::raw('COUNT(*) as total'))
                ->groupBy('vendor_id')->pluck('total', 'vendor_id');

            $today = now()->startOfDay();

            $vendors = $query->orderBy('name')->get()->map(function ($vendor) use ($trainerCounts, $today) {
                $vendor->status = (int) $vendor->status;
                $vendor->trainer_count = (int) ($trainerCounts[$vendor->id] ?? 0);

                if (!$vendor->contract_end) {
                    $vendor->contract_state = 'open';
                    $vendor->days_to_expiry = null;
                } else {
                    $end = \Carbon\Carbon::parse($vendor->contract_end);
                    $vendor->days_to_expiry = (int) $today->diffInDays($end, false);
                    $vendor->contract_state = $vendor->days_to_expiry < 0 ? 'expired' : ($vendor->days_to_expiry <= 60 ? 'expiring' : 'active');
                }

                return $vendor;
            });

            return response()->json(['status' => true, 'data' => $vendors]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load vendors');
        }
    }

    /** POST /vendors */
    public function storeVendor(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->vendorRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        try {
            $id = DB::table('lms_vendors')->insertGetId($this->vendorPayload($request) + [
                'sub_institute_id' => $sid,
                'created_by'       => $this->actorId($request),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Vendor added', 'data' => DB::table('lms_vendors')->find($id)], 201);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to add the vendor');
        }
    }

    /** PUT /vendors/{id} */
    public function updateVendor(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->vendorRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        if (!$this->findScoped('lms_vendors', $id, $sid)) {
            return response()->json(['status' => false, 'message' => 'Vendor not found'], 404);
        }

        try {
            DB::table('lms_vendors')->where('id', $id)->update($this->vendorPayload($request) + [
                'updated_by' => $this->actorId($request),
                'updated_at' => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Vendor updated', 'data' => DB::table('lms_vendors')->find($id)]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to update the vendor');
        }
    }

    /** DELETE /vendors/{id} - refused while trainers still reference it. */
    public function destroyVendor(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        if (!$this->findScoped('lms_vendors', $id, $sid)) {
            return response()->json(['status' => false, 'message' => 'Vendor not found'], 404);
        }

        $linked = DB::table('lms_trainers')->where('vendor_id', $id)->whereNull('deleted_at')->count();
        if ($linked > 0) {
            return response()->json([
                'status'  => false,
                'message' => "{$linked} trainer" . ($linked === 1 ? '' : 's') . ' still linked to this vendor. Reassign them first.',
            ], 422);
        }

        try {
            DB::table('lms_vendors')->where('id', $id)->update([
                'deleted_at' => now(),
                'deleted_by' => $this->actorId($request),
            ]);

            return response()->json(['status' => true, 'message' => 'Vendor removed']);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to remove the vendor');
        }
    }

    /* ─── Integrations ─────────────────────────────────────────────────────── */

    /** GET /integrations */
    public function integrations(Request $request)
    {
        $sid = $this->lmsContext($request)['sub_institute_id'];
        if (!$sid) {
            return response()->json(['status' => false, 'message' => 'sub_institute_id is required'], 422);
        }

        try {
            $integrations = DB::table('lms_integrations')
                ->where('sub_institute_id', $sid)->whereNull('deleted_at')
                ->orderBy('display_name')
                ->get(['id', 'provider', 'display_name', 'category', 'description', 'status', 'connected_at', 'last_sync_at', 'last_error', 'config', 'created_at'])
                ->map(function ($integration) {
                    $decoded = $integration->config ? json_decode($integration->config, true) : null;
                    $integration->config = is_array($decoded) ? $decoded : null;
                    return $integration;
                });

            return response()->json(['status' => true, 'data' => $integrations]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to load integrations');
        }
    }

    private function integrationRules(): array
    {
        return [
            'provider'     => 'required|string|max:100',
            'display_name' => 'required|string|max:191',
            'category'     => 'nullable|string|max:50',
            'description'  => 'nullable|string|max:1000',
            'status'       => 'nullable|string|in:connected,disconnected,error',
            'config'       => 'nullable|array',
        ];
    }

    /** POST /integrations - records that a provider is configured; no OAuth handshake, no secrets. */
    public function storeIntegration(Request $request)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->integrationRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];
        $provider = $request->input('provider');

        $duplicate = DB::table('lms_integrations')->where('sub_institute_id', $sid)->where('provider', $provider)->whereNull('deleted_at')->exists();
        if ($duplicate) {
            return response()->json(['status' => false, 'message' => 'That provider is already configured for this institute.'], 422);
        }

        try {
            $status = $request->input('status', 'disconnected');

            $id = DB::table('lms_integrations')->insertGetId([
                'sub_institute_id' => $sid,
                'provider'         => $provider,
                'display_name'     => $request->input('display_name'),
                'category'         => $request->input('category'),
                'description'      => $request->input('description'),
                'status'           => $status,
                'connected_at'     => $status === 'connected' ? now() : null,
                'config'           => $request->has('config') ? json_encode($request->input('config')) : null,
                'created_by'       => $this->actorId($request),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Integration added', 'data' => DB::table('lms_integrations')->find($id)], 201);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to add the integration');
        }
    }

    /** PUT /integrations/{id} */
    public function updateIntegration(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $validator = Validator::make($request->all(), $this->integrationRules());
        if ($validator->fails()) {
            return $this->invalid($validator);
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];
        $integration = $this->findScoped('lms_integrations', $id, $sid);
        if (!$integration) {
            return response()->json(['status' => false, 'message' => 'Integration not found'], 404);
        }

        try {
            $status = $request->input('status', $integration->status);

            DB::table('lms_integrations')->where('id', $id)->update([
                'display_name' => $request->input('display_name'),
                'category'     => $request->input('category'),
                'description'  => $request->input('description'),
                'status'       => $status,
                'connected_at' => $status === 'connected' ? ($integration->connected_at ?: now()) : null,
                'last_error'   => $status === 'error' ? $request->input('last_error') : null,
                'config'       => $request->has('config') ? json_encode($request->input('config')) : $integration->config,
                'updated_by'   => $this->actorId($request),
                'updated_at'   => now(),
            ]);

            return response()->json(['status' => true, 'message' => 'Integration updated', 'data' => DB::table('lms_integrations')->find($id)]);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to update the integration');
        }
    }

    /** DELETE /integrations/{id} */
    public function destroyIntegration(Request $request, $id)
    {
        if ($guard = $this->guardAdmin($request)) {
            return $guard;
        }

        $sid = $this->lmsContext($request)['sub_institute_id'];

        if (!$this->findScoped('lms_integrations', $id, $sid)) {
            return response()->json(['status' => false, 'message' => 'Integration not found'], 404);
        }

        try {
            DB::table('lms_integrations')->where('id', $id)->update([
                'deleted_at' => now(),
                'deleted_by' => $this->actorId($request),
            ]);

            return response()->json(['status' => true, 'message' => 'Integration removed']);
        } catch (\Throwable $e) {
            return $this->fail($e, 'Failed to remove the integration');
        }
    }
}
