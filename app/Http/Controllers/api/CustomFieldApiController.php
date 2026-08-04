<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomFieldApiController extends Controller
{
    use GetsJwtToken;

    private function failure(string $message, int $status = 422, $errors = null)
    {
        return response()->json(['status_code' => 0, 'message' => $message, 'errors' => $errors, 'data' => []], $status);
    }

    private function context(Request $request)
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json(['status_code' => 2, 'message' => 'Token Auth Failed', 'data' => []], 401);
            }
        } catch (\Exception $exception) {
            return response()->json(['status_code' => 2, 'message' => $exception->getMessage(), 'data' => []], 401);
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $token = preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization'));
        $parts = explode('.', $token);
        $payload = [];
        if (count($parts) === 3) {
            $decoded = base64_decode(strtr($parts[1], '-_', '+/'));
            $payload = json_decode($decoded ?: '{}', true) ?: [];
        }
        $actorId = (int) ($payload['id'] ?? 0);
        $tenantId = (int) ($payload['sub_institute_id'] ?? 0);
        if ($actorId !== $request->integer('user_id') || $tenantId !== $request->integer('sub_institute_id')) {
            return response()->json(['status_code' => 2, 'message' => 'Token context does not match the request.', 'data' => []], 403);
        }

        $actor = DB::table('tbluser as user')
            ->join('tbluserprofilemaster as profile', 'profile.id', '=', 'user.user_profile_id')
            ->select('user.id', 'user.user_profile_id', 'user.sub_institute_id', 'profile.name as profile_name')
            ->where('user.id', $actorId)->where('user.sub_institute_id', $tenantId)->where('user.status', 1)->first();
        if (! $actor) {
            return response()->json(['status_code' => 2, 'message' => 'Active user context was not found.', 'data' => []], 403);
        }
        $request->attributes->set('custom_field_actor', $actor);

        return null;
    }

    private function guard(Request $request, string $action)
    {
        if ($response = $this->context($request)) {
            return $response;
        }
        $actor = $request->attributes->get('custom_field_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) {
            return null;
        }

        $menuId = DB::table('tblmenumaster')->where('status', 1)->whereIn('link', ['settings.custom_fields'])->value('id');
        $individual = $menuId ? DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)->where('sub_institute_id', $actor->sub_institute_id)->first() : null;
        $rights = $individual ?: ($menuId ? DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)->first() : null);
        $column = ['view' => 'can_view', 'add' => 'can_add', 'edit' => 'can_edit', 'delete' => 'can_delete'][$action];
        if (! (bool) ($rights->{$column} ?? false)) {
            return $this->failure("You do not have permission to {$action} custom fields.", 403);
        }
        return null;
    }

    public function index(Request $request)
    {
        if ($response = $this->guard($request, 'view')) {
            return $response;
        }
        $tenantId = $request->integer('sub_institute_id');

        $fields = tblcustomfieldsModel::query()
            ->where(function ($query) use ($tenantId) {
                $query->where('sub_institute_id', $tenantId)
                    ->orWhere('common_to_all', 1);
            })
            ->where('status', 1)
            ->where('is_deleted', 'N')
            ->orderBy('table_name', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->get();

        $data = $fields->map(function ($field) {
            $options = tblfields_dataModel::where('field_id', $field->id)->get(['id', 'display_text', 'display_value']);
            return [
                'id' => $field->id,
                'table_name' => $field->table_name,
                'table_alias' => $field->table_alias,
                'tab_sort_order' => $field->tab_sort_order,
                'field_name' => $field->field_name,
                'column_header' => $field->column_header,
                'field_label' => $field->field_label,
                'user_type' => $field->user_type,
                'field_type' => $field->field_type,
                'field_message' => $field->field_message,
                'file_size_max' => $field->file_size_max,
                'required' => $field->required,
                'common_to_all' => $field->common_to_all,
                'sort_order' => $field->sort_order,
                'is_deleted' => $field->is_deleted,
                'sub_institute_id' => $field->sub_institute_id,
                'options' => $options,
            ];
        });

        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => $data]);
    }

    public function store(Request $request)
    {
        if ($response = $this->guard($request, 'add')) {
            return $response;
        }
        $tenantId = $request->integer('sub_institute_id');

        $validator = Validator::make($request->all(), [
            'table_name' => 'required|string|max:50',
            'field_name' => 'required|string|max:50',
            'field_label' => 'required|string|max:50',
            'field_type' => 'required|string|max:50',
            'field_message' => 'nullable|string|max:50',
            'file_size_max' => 'nullable|string|max:50',
            'sort_order' => 'required|integer|min:1',
            'required' => 'nullable|boolean',
            'common_to_all' => 'nullable|boolean',
            'user_type' => 'nullable|string|max:50',
            'column_header' => 'nullable|string|max:50',
            'table_alias' => 'nullable|string|max:10',
            'tab_sort_order' => 'nullable|integer',
            'display_name' => 'nullable|array',
            'display_name.*' => 'nullable|string|max:50',
            'f_value' => 'nullable|array',
            'f_value.*' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $fieldType = $request->input('field_type');
        if (in_array($fieldType, ['checkbox', 'dropdown'], true)) {
            $displayNames = array_filter((array) $request->input('display_name', []));
            $fValues = array_filter((array) $request->input('f_value', []));
            if (empty($displayNames) || empty($fValues)) {
                return $this->failure('display_name and f_value are required for checkbox and dropdown fields.', 422);
            }
            if (count($displayNames) !== count($fValues)) {
                return $this->failure('display_name and f_value must have the same number of entries.', 422);
            }
        }

        $field = new tblcustomfieldsModel([
            'table_name' => $request->input('table_name'),
            'table_alias' => $request->input('table_alias'),
            'tab_sort_order' => $request->input('tab_sort_order', 1),
            'field_name' => strtolower(str_replace(' ', '_', $request->input('field_name'))),
            'column_header' => $request->input('column_header', ''),
            'field_label' => $request->input('field_label'),
            'user_type' => $request->input('user_type', ''),
            'field_type' => $fieldType,
            'field_message' => $request->input('field_message', ''),
            'file_size_max' => $request->input('file_size_max'),
            'status' => 1,
            'sort_order' => $request->integer('sort_order'),
            'required' => $request->boolean('required') ? 1 : 0,
            'is_deleted' => 'N',
            'common_to_all' => $request->boolean('common_to_all') ? 1 : 0,
            'sub_institute_id' => $tenantId,
        ]);
        $field->save();
        $fieldId = $field->id;

        if (in_array($fieldType, ['checkbox', 'dropdown'], true)) {
            $displayNames = (array) $request->input('display_name', []);
            $fValues = (array) $request->input('f_value', []);
            $now = now();
            $rows = [];
            foreach ($displayNames as $index => $text) {
                if (trim((string) $text) === '') {
                    continue;
                }
                $rows[] = [
                    'field_id' => $fieldId,
                    'display_text' => $text,
                    'display_value' => $fValues[$index] ?? $text,
                    'created_on' => $now,
                ];
            }
            if (!empty($rows)) {
                DB::table('tblfields_data')->insert($rows);
            }
        }

        $this->ensureColumnExists($request->input('table_name'), strtolower(str_replace(' ', '_', $request->input('field_name'))));

        return response()->json(['status_code' => 1, 'message' => 'Field added successfully', 'data' => ['id' => $fieldId]]);
    }

    public function show(Request $request, $id)
    {
        if ($response = $this->guard($request, 'view')) {
            return $response;
        }
        $tenantId = $request->integer('sub_institute_id');

        $field = tblcustomfieldsModel::where(function ($query) use ($tenantId) {
            $query->where('sub_institute_id', $tenantId)->orWhere('common_to_all', 1);
        })->where('id', $id)->where('status', 1)->where('is_deleted', 'N')->first();

        if (! $field) {
            return $this->failure('Field not found.', 404);
        }

        $options = tblfields_dataModel::where('field_id', $field->id)->get(['id', 'display_text', 'display_value']);

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => [
                'id' => $field->id,
                'table_name' => $field->table_name,
                'table_alias' => $field->table_alias,
                'tab_sort_order' => $field->tab_sort_order,
                'field_name' => $field->field_name,
                'column_header' => $field->column_header,
                'field_label' => $field->field_label,
                'user_type' => $field->user_type,
                'field_type' => $field->field_type,
                'field_message' => $field->field_message,
                'file_size_max' => $field->file_size_max,
                'required' => $field->required,
                'common_to_all' => $field->common_to_all,
                'sort_order' => $field->sort_order,
                'is_deleted' => $field->is_deleted,
                'sub_institute_id' => $field->sub_institute_id,
                'options' => $options,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->guard($request, 'edit')) {
            return $response;
        }
        $tenantId = $request->integer('sub_institute_id');

        $field = tblcustomfieldsModel::where(function ($query) use ($tenantId) {
            $query->where('sub_institute_id', $tenantId)->orWhere('common_to_all', 1);
        })->where('id', $id)->where('is_deleted', 'N')->first();

        if (! $field) {
            return $this->failure('Field not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'field_label' => 'required|string|max:50',
            'field_message' => 'nullable|string|max:50',
            'sort_order' => 'required|integer|min:1',
            'file_size_max' => 'nullable|string|max:50',
            'required' => 'nullable|boolean',
            'common_to_all' => 'nullable|boolean',
            'display_name' => 'nullable|array',
            'display_name.*' => 'nullable|string|max:50',
            'f_value' => 'nullable|array',
            'f_value.*' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $fieldType = $request->input('field_type', $field->field_type);
        if (in_array($fieldType, ['checkbox', 'dropdown'], true)) {
            $displayNames = array_filter((array) $request->input('display_name', []));
            $fValues = array_filter((array) $request->input('f_value', []));
            if (empty($displayNames) || empty($fValues)) {
                return $this->failure('display_name and f_value are required for checkbox and dropdown fields.', 422);
            }
            if (count($displayNames) !== count($fValues)) {
                return $this->failure('display_name and f_value must have the same number of entries.', 422);
            }
        }

        $field->field_label = $request->input('field_label');
        $field->field_message = $request->input('field_message', $field->field_message);
        $field->required = $request->boolean('required') ? 1 : 0;
        $field->common_to_all = $request->boolean('common_to_all') ? 1 : 0;
        $field->file_size_max = $request->input('file_size_max');
        $field->sort_order = $request->integer('sort_order');
        $field->user_type = $request->input('user_type', $field->user_type);
        $field->column_header = $request->input('column_header', $field->column_header);
        $field->table_alias = $request->input('table_alias', $field->table_alias);
        $field->tab_sort_order = $request->input('tab_sort_order', $field->tab_sort_order);
        $field->save();

        if (in_array($fieldType, ['checkbox', 'dropdown'], true)) {
            tblfields_dataModel::where('field_id', $id)->delete();
            $displayNames = (array) $request->input('display_name', []);
            $fValues = (array) $request->input('f_value', []);
            $now = now();
            $rows = [];
            foreach ($displayNames as $index => $text) {
                if (trim((string) $text) === '') {
                    continue;
                }
                $rows[] = [
                    'field_id' => $id,
                    'display_text' => $text,
                    'display_value' => $fValues[$index] ?? $text,
                    'created_on' => $now,
                ];
            }
            if (!empty($rows)) {
                DB::table('tblfields_data')->insert($rows);
            }
        }

        return response()->json(['status_code' => 1, 'message' => 'Custom field updated successfully.', 'data' => ['id' => $field->id]]);
    }

    public function destroy(Request $request, $id)
    {
        if ($response = $this->guard($request, 'delete')) {
            return $response;
        }
        $tenantId = $request->integer('sub_institute_id');

        $field = tblcustomfieldsModel::where(function ($query) use ($tenantId) {
            $query->where('sub_institute_id', $tenantId)->orWhere('common_to_all', 1);
        })->where('id', $id)->where('is_deleted', 'N')->first();

        if (! $field) {
            return $this->failure('Field not found.', 404);
        }

        $field->status = 0;
        $field->is_deleted = 'Y';
        $field->save();

        return response()->json(['status_code' => 1, 'message' => 'Custom field deleted successfully.', 'data' => []]);
    }

    public function updateSortOrder(Request $request)
    {
        if ($response = $this->guard($request, 'edit')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'order' => 'required|array',
            'order.*' => 'integer|exists:tblcustom_fields,id',
        ]);

        if ($validator->fails()) {
            return $this->failure($validator->messages()->first(), 422, $validator->errors());
        }

        $order = $request->input('order', []);
        foreach ($order as $index => $id) {
            tblcustomfieldsModel::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['status_code' => 1, 'message' => 'Sort order updated successfully.', 'data' => []]);
    }

    private function ensureColumnExists(string $table, string $column): void
    {
        $column = strtolower(str_replace(' ', '_', $column));
        if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
            DB::getSchemaBuilder()->table($table, function ($table) use ($column) {
                $table->string($column)->nullable();
            });
        }
    }
}
