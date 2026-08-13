<?php

namespace App\Http\Controllers\api\easy_com;

use App\Models\WhatappUserDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WhatsApp Cloud API credentials  (table: whatapp_user_details)
 *
 * Mirrors WhatsappController's whatsapp_user_details / whatsappUserDetailsStore /
 * whatsappUserDetailsDestroy with the fixes a stateless client needs:
 *
 *  - index() is scoped to the institute. The Blade version called
 *    `WhatappUserDetail::all()`, which returned EVERY tenant's access tokens;
 *  - destroy() is scoped too (the Blade version deleted by bare id) and returns
 *    JSON - it previously always returned redirect('whatsapp-user-details'),
 *    i.e. HTML, which the Next.js client could not parse;
 *  - store/update are split, so an edit updates the row instead of relying on a
 *    hidden `id` field in a create form;
 *  - the access token is masked in list/read responses.
 *
 * NOTE the legacy table also carries the non-null twilio columns
 * user_whatsapp_sid / user_whatsapp_token. The Blade store never set them, so
 * inserts relied on MySQL's implicit '' default. They are written explicitly
 * here so the insert is valid under STRICT_TRANS_TABLES too.
 */
class WhatsappApiConfigApiController extends BaseEasyComApiController
{
    /** GET /api/easy_com/whatsapp-api */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = WhatappUserDetail::where('sub_institute_id', $this->subInstituteId())
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn ($row) => $this->present($row, true));

            return $this->success($rows, 'Success');
        });
    }

    /** GET /api/easy_com/whatsapp-api/{id} — powers the Edit screen. */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('WhatsApp configuration not found.', 404);
            }

            return $this->success($this->present($row, true), 'Success');
        });
    }

    /** POST /api/easy_com/whatsapp-api */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->rules($request, true);

            if (WhatappUserDetail::where('sub_institute_id', $this->subInstituteId())->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'user_whatsapp_no' => ['A WhatsApp configuration already exists for this institute. Edit or delete it first.'],
                ]);
            }

            $row = new WhatappUserDetail();
            $row->sub_institute_id = $this->subInstituteId();
            $row->user_whatsapp_no = $data['user_whatsapp_no'];
            $row->cloud_api_access_token = $data['cloud_api_access_token'];
            $row->cloud_api_phone_number_id = $data['cloud_api_phone_number_id'];
            $row->api_type = 'cloud_api';
            // Legacy non-nullable twilio columns - unused by the cloud API flow.
            $row->user_whatsapp_sid = '';
            $row->user_whatsapp_token = '';
            $row->created_by = $this->userId();
            $row->created_by_name = session()->get('name') ?: '';
            $row->save();

            return $this->success($this->present($row, true), 'WhatsApp configuration saved.', 201);
        });
    }

    /** PUT/PATCH /api/easy_com/whatsapp-api/{id} */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('WhatsApp configuration not found.', 404);
            }

            $data = $this->rules($request, false);

            $row->user_whatsapp_no = $data['user_whatsapp_no'];
            $row->cloud_api_phone_number_id = $data['cloud_api_phone_number_id'];

            // Blank / masked token => keep the stored credential.
            $token = (string) ($data['cloud_api_access_token'] ?? '');
            if ($token !== '' && ! $this->isMasked($token)) {
                $row->cloud_api_access_token = $token;
            }

            if (! $row->isDirty()) {
                return $this->success($this->present($row, true), 'No changes to save.');
            }

            $row->save();

            return $this->success($this->present($row->fresh(), true), 'WhatsApp configuration updated.');
        });
    }

    /** DELETE /api/easy_com/whatsapp-api/{id} */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('WhatsApp configuration not found.', 404);
            }

            $row->delete();

            return $this->success(null, 'WhatsApp configuration deleted.');
        });
    }

    /* ------------------------------------------------------------------ */

    private function findOwned($id): ?WhatappUserDetail
    {
        return WhatappUserDetail::where('id', $id)
            ->where('sub_institute_id', $this->subInstituteId())
            ->first();
    }

    private function rules(Request $request, bool $tokenRequired): array
    {
        return $this->validate($request, [
            'user_whatsapp_no'          => 'required|string|max:15',
            'cloud_api_access_token'    => ($tokenRequired ? 'required' : 'nullable').'|string',
            'cloud_api_phone_number_id' => 'required|string|max:191',
        ], [], [
            'user_whatsapp_no'          => 'WhatsApp number',
            'cloud_api_access_token'    => 'access token',
            'cloud_api_phone_number_id' => 'phone number id',
        ]);
    }

    private function present(WhatappUserDetail $row, bool $mask): array
    {
        $token = (string) $row->cloud_api_access_token;

        return [
            'id'                        => $row->id,
            'user_whatsapp_no'          => $row->user_whatsapp_no,
            'cloud_api_phone_number_id' => $row->cloud_api_phone_number_id,
            'cloud_api_access_token'    => $mask ? $this->maskToken($token) : $token,
            'has_access_token'          => $token !== '',
            'api_type'                  => $row->api_type,
            'created_by_name'           => $row->created_by_name,
            'created_at'                => $row->created_at,
        ];
    }

    private function maskToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        return strlen($token) <= 8
            ? str_repeat('•', strlen($token))
            : substr($token, 0, 4).str_repeat('•', 8).substr($token, -4);
    }

    private function isMasked(string $value): bool
    {
        return str_contains($value, '•');
    }
}
