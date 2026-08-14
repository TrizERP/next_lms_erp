<?php

namespace App\Http\Controllers\api\easy_com;

use App\Models\easy_com\manage_sms_api\manage_sms_api;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SMS API Master  (table: sms_api_details)
 *
 * Mirrors easy_com\manage_sms_api\manage_sms_api_controller, with the fixes the
 * Blade flow needs when driven statelessly:
 *
 *  - every query is scoped to the JWT's sub_institute_id (the Blade update()/
 *    destroy() ran `where(id = ?)` with NO tenant filter, so a crafted id could
 *    update or delete another institute's gateway);
 *  - required fields are validated (the Blade form only had HTML `required`);
 *  - the gateway URL template is checked so a save cannot silently produce a
 *    broken SMS URL;
 *  - update() 404s on an unknown/foreign id instead of reporting "Data Saved"
 *    after affecting 0 rows.
 */
class SmsApiMasterApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/sms-api */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = manage_sms_api::where('sub_institute_id', $this->subInstituteId())
                ->orderBy('id', 'desc')
                ->get();

            return $this->success($rows, 'Success');
        });
    }

    /** GET /api/easy_com/sms-api/{id} — powers the Edit screen. */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('SMS API configuration not found.', 404);
            }

            return $this->success($row, 'Success');
        });
    }

    /** POST /api/easy_com/sms-api */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validated($request);

            $row = manage_sms_api::create($data + [
                'sub_institute_id' => $this->subInstituteId(),
                'is_active'        => (int) $request->input('is_active', 1),
            ]);

            return $this->success($row, 'SMS API configuration saved.', 201);
        });
    }

    /**
     * PUT/PATCH /api/easy_com/sms-api/{id}
     *
     * The Update flow the Blade edit form performs (method_field("PUT") ->
     * manage_sms_api.update), plus tenant scoping and validation.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('SMS API configuration not found.', 404);
            }

            $data = $this->validated($request, $row->id);

            $row->fill($data);
            $row->is_active = (int) $request->input('is_active', $row->is_active ?? 1);

            // Nothing actually changed - report it instead of a misleading "saved".
            if (! $row->isDirty()) {
                return $this->success($row->fresh(), 'No changes to save.');
            }

            $row->save();

            return $this->success($row->fresh(), 'SMS API configuration updated.');
        });
    }

    /** DELETE /api/easy_com/sms-api/{id} */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('SMS API configuration not found.', 404);
            }

            $row->delete();

            return $this->success(null, 'SMS API configuration deleted.');
        });
    }

    /* ------------------------------------------------------------------ */

    /** Tenant-scoped lookup - never trust a bare id from the client. */
    private function findOwned($id): ?manage_sms_api
    {
        return manage_sms_api::where('id', $id)
            ->where('sub_institute_id', $this->subInstituteId())
            ->first();
    }

    /**
     * The gateway URL is assembled by sendSMS() as:
     *   url . pram . [template_id] . mobile_var . <mobile> . text_var . <text> . last_var
     * so `url` must be a real http(s) endpoint and the separators must be present.
     */
    private function validated(Request $request, $ignoreId = null): array
    {
        $data = $this->validate($request, [
            'url'        => 'required|string|max:2000',
            'pram'       => 'required|string|max:500',
            'mobile_var' => 'required|string|max:255',
            'text_var'   => 'required|string|max:255',
            'last_var'   => 'nullable|string|max:500',
        ], [], [
            'pram'       => 'parameter',
            'mobile_var' => 'mobile variable',
            'text_var'   => 'text variable',
            'last_var'   => 'last variable',
        ]);

        $data['url'] = trim($data['url']);
        $data['last_var'] = $data['last_var'] ?? '';

        if (! preg_match('#^https?://#i', $data['url'])) {
            $this->throwValidation(['url' => ['The URL must start with http:// or https://.']]);
        }

        // Only one active gateway per institute is meaningful - sendSMS() takes
        // first(). Block a second one instead of silently ignoring it.
        $duplicate = manage_sms_api::where('sub_institute_id', $this->subInstituteId())
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($duplicate) {
            $this->throwValidation([
                'url' => ['An SMS gateway is already configured for this institute. Edit or delete it first.'],
            ]);
        }

        return $data;
    }

    private function throwValidation(array $errors): void
    {
        throw \Illuminate\Validation\ValidationException::withMessages($errors);
    }
}
