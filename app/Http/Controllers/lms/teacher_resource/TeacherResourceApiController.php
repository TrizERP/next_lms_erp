<?php

namespace App\Http\Controllers\lms\teacher_resource;

use App\Http\Controllers\Controller;
use App\Models\lms\teacherResourceModel;
use App\Models\settings\tblcustomfieldsModel;
use App\Models\settings\tblfields_dataModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TeacherResourceApiController extends Controller
{
    protected function bootstrapRequestContext(Request $request): array
    {
        $context = [
            'sub_institute_id' => (string) $request->input('sub_institute_id', $request->session()->get('sub_institute_id')),
            'syear' => (string) $request->input('syear', $request->session()->get('syear')),
            'user_id' => (string) $request->input('user_id', $request->session()->get('user_id')),
            'term_id' => (string) $request->input('term_id', $request->session()->get('term_id')),
            'user_profile_id' => (string) $request->input('user_profile_id', $request->session()->get('user_profile_id')),
            'user_profile_name' => (string) $request->input('user_profile_name', $request->session()->get('user_profile_name')),
            'client_id' => (string) $request->input('client_id', $request->session()->get('client_id')),
        ];

        foreach ($context as $key => $value) {
            if ($value !== '') {
                $request->session()->put($key, $value);
            }
        }

        return $context;
    }

    protected function validateContext(array $context): ?JsonResponse
    {
        if ($context['sub_institute_id'] === '') {
            return response()->json([
                'status' => 0,
                'message' => 'sub_institute_id is required.',
            ], 422);
        }

        if ($context['syear'] === '') {
            return response()->json([
                'status' => 0,
                'message' => 'syear is required.',
            ], 422);
        }

        return null;
    }

    protected function getSelectedParams(Request $request): array
    {
        return [
            'standard_id' => (string) $request->input('standard_id', ''),
            'subject_id' => (string) $request->input('subject_id', ''),
            'chapter_id' => (string) $request->input('chapter_id', ''),
            'topic_id' => (string) $request->input('topic_id', ''),
            'mappedValues' => (string) $request->input('mappedValues', ''),
        ];
    }

    protected function getCustomFields(string $subInstituteId)
    {
        return tblcustomfieldsModel::where(['status' => '1', 'table_name' => 'lms_teacher_resource'])
            ->whereRaw('(sub_institute_id = ' . (int) $subInstituteId . ' OR common_to_all = 1) and user_type="" ')
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    protected function getFieldsDataMap(): array
    {
        $fieldsData = tblfields_dataModel::get()->toArray();
        $finalFieldsData = [];

        foreach ($fieldsData as $value) {
            $fieldId = $value['field_id'];
            if (!isset($finalFieldsData[$fieldId])) {
                $finalFieldsData[$fieldId] = [];
            }
            $finalFieldsData[$fieldId][] = [
                'display_text' => $value['display_text'],
                'display_value' => $value['display_value'],
            ];
        }

        return $finalFieldsData;
    }

    protected function getMappingCollections(string $chapterId = '', string $topicId = ''): array
    {
        $lmsMappingType = DB::table('lms_mapping_type')
            ->where('status', 1)
            ->where('parent_id', 0)
            ->when($chapterId !== '', function ($query) use ($chapterId) {
                $query->where(function ($q) use ($chapterId) {
                    $q->where('globally', 1)
                        ->orWhere('chapter_id', $chapterId);
                });
            }, function ($query) {
                $query->where('globally', 1);
            })
            ->when($topicId !== '', function ($query) use ($topicId) {
                $query->where(function ($q) use ($topicId) {
                    $q->where('topic_id', 0)
                        ->orWhere('topic_id', $topicId);
                });
            }, function ($query) {
                $query->where('topic_id', 0);
            })
            ->get()
            ->toArray();

        $mapType = [];
        $mapVal = [];
        foreach ($lmsMappingType as $value) {
            $mapType[$value->id] = $value->name;
            $mappedValues = DB::table('lms_mapping_type')
                ->where(['parent_id' => $value->id, 'status' => 1])
                ->get()
                ->toArray();

            foreach ($mappedValues as $mappedValue) {
                if (!isset($mapVal[$value->id])) {
                    $mapVal[$value->id] = [];
                }
                $mapVal[$value->id][$mappedValue->id] = $mappedValue->name;
            }
        }

        $mappingTypes = array_map(function ($value) {
            return [
                'id' => (string) ($value['id'] ?? ''),
                'name' => (string) ($value['name'] ?? ''),
            ];
        }, json_decode(json_encode($lmsMappingType), true));

        return [
            'lms_mapping_type' => $mappingTypes,
            'mapType' => $mapType,
            'mapVal' => $mapVal,
        ];
    }

    protected function normalizeMappedValues(?string $mappingValue, array $mapType, array $mapVal): array
    {
        if (!$mappingValue) {
            return [];
        }

        $decoded = json_decode($mappingValue, true);
        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $typeId => $valueId) {
            $normalized[] = [
                'type_id' => (string) $typeId,
                'type_name' => $mapType[$typeId] ?? '-',
                'value_id' => (string) $valueId,
                'value_name' => $mapVal[$typeId][$valueId] ?? '-',
            ];
        }

        return $normalized;
    }

    protected function buildFileUrl(array $record): string
    {
        if (($record['file_name'] ?? '') === '') {
            return '';
        }

        if (($record['file_type'] ?? '') === 'link') {
            return (string) $record['file_name'];
        }

        if (($record['file_folder'] ?? '') !== '') {
            return Storage::disk('digitalocean')->url('public' . $record['file_folder'] . '/' . $record['file_name']);
        }

        return '';
    }

    protected function getResources(Request $request, string $subInstituteId): array
    {
        $selected = $this->getSelectedParams($request);

        $query = teacherResourceModel::select('lms_teacher_resource.*', 'c.chapter_name', 't.name as topic_name')
            ->join('chapter_master as c', 'lms_teacher_resource.chapter_id', 'c.id')
            ->leftJoin('topic_master as t', 'lms_teacher_resource.topic_id', 't.id')
            ->where('lms_teacher_resource.sub_institute_id', $subInstituteId);

        if ($selected['chapter_id'] !== '') {
            $query->where('lms_teacher_resource.chapter_id', $selected['chapter_id']);
        }

        if ($selected['standard_id'] !== '') {
            $query->where('lms_teacher_resource.standard_id', $selected['standard_id']);
        }

        if ($selected['mappedValues'] !== '') {
            $explodeData = array_filter(explode(',', $selected['mappedValues']));
            if (count($explodeData) > 0) {
                $query->whereNotNull('mapping_value');
                $query->where(function ($subQuery) use ($explodeData) {
                    foreach ($explodeData as $value) {
                        $subQuery->orWhere('mapping_value', 'LIKE', '%"' . $value . '"%');
                    }
                });
            }
        }

        return $query->get()->toArray();
    }

    protected function buildResponsePayload(Request $request, array $context): array
    {
        $selected = $this->getSelectedParams($request);
        $customFields = $this->getCustomFields($context['sub_institute_id']);
        $fieldsData = $this->getFieldsDataMap();
        $mappings = $this->getMappingCollections($selected['chapter_id'], $selected['topic_id']);
        $resources = $this->getResources($request, $context['sub_institute_id']);

        $records = array_map(function ($resource) use ($customFields, $mappings) {
            $record = (array) $resource;
            $customFieldValues = [];
            foreach ($customFields as $field) {
                $fieldName = (string) $field->field_name;
                $customFieldValues[$fieldName] = $record[$fieldName] ?? '';
            }

            return [
                'id' => (string) ($record['id'] ?? ''),
                'chapter_name' => (string) ($record['chapter_name'] ?? ''),
                'topic_name' => (string) ($record['topic_name'] ?? ''),
                'title' => (string) ($record['title'] ?? ''),
                'file_name' => (string) ($record['file_name'] ?? ''),
                'file_type' => (string) ($record['file_type'] ?? ''),
                'file_url' => $this->buildFileUrl($record),
                'mapping_value' => (string) ($record['mapping_value'] ?? ''),
                'mapped_values' => $this->normalizeMappedValues($record['mapping_value'] ?? null, $mappings['mapType'], $mappings['mapVal']),
                'custom_field_values' => $customFieldValues,
                'raw' => $record,
            ];
        }, $resources);

        $customFieldPayload = [];
        foreach ($customFields as $field) {
            $customFieldPayload[] = [
                'id' => (string) $field->id,
                'field_label' => (string) $field->field_label,
                'field_name' => (string) $field->field_name,
                'field_type' => (string) $field->field_type,
                'field_message' => (string) $field->field_message,
                'required' => (string) $field->required,
                'sort_order' => (string) $field->sort_order,
                'options' => $fieldsData[$field->id] ?? [],
            ];
        }

        return [
            'status' => 1,
            'message' => 'Success',
            'records' => $records,
            'custom_fields' => $customFieldPayload,
            'mapping_types' => $mappings['lms_mapping_type'],
            'mapping_values' => $mappings['mapVal'],
            'map_types' => $mappings['mapType'],
            'selected' => $selected,
        ];
    }

    protected function buildPayloadFromRequest(Request $request, array $context, bool $isUpdate = false): array
    {
        $fileFolder = '';
        $ext = '';
        $size = '';
        $newFilename = '';

        if ($request->hasFile('teacher_file')) {
            $img = $request->file('teacher_file');
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $fileFolder = '/lms_teacher_resource';
            $newFilename = 'lms_' . date('Y-m-d_h-i-s') . '.' . $ext;
            Storage::disk('digitalocean')->putFileAs('public/lms_teacher_resource/', $img, $newFilename, 'public');
        }

        $payload = [
            'status' => '1',
            'sub_institute_id' => $context['sub_institute_id'],
            'syear' => $context['syear'],
        ];

        if ($context['user_id'] !== '') {
            $payload['created_by'] = $context['user_id'];
        }

        if ($newFilename !== '') {
            $payload['file_folder'] = $fileFolder;
            $payload['file_name'] = $newFilename;
            $payload['file_type'] = $ext;
            $payload['file_size'] = $size;
        }

        $mappingType = $request->input('mapping_type', []);
        $mappingValue = $request->input('mapping_value', []);
        if (!is_array($mappingType)) {
            $mappingType = [];
        }
        if (!is_array($mappingValue)) {
            $mappingValue = [];
        }

        foreach ($request->all() as $key => $value) {
            if (in_array($key, ['_token', '_method', 'submit', 'mapping_type', 'mapping_value', 'teacher_file'], true)) {
                continue;
            }

            if (strpos($key, 'hid_') === 0) {
                $key = str_replace('hid_', '', $key);
            }

            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $payload[$key] = $value;
        }

        $jsonArr = [];
        foreach ($mappingType as $index => $typeId) {
            if ($typeId !== '' && isset($mappingValue[$index]) && $mappingValue[$index] !== '') {
                $jsonArr[$typeId] = $mappingValue[$index];
            }
        }

        $payload['mapping_value'] = !empty($jsonArr) ? json_encode($jsonArr) : null;

        if (!$isUpdate && $newFilename === '') {
            $payload['file_folder'] = '';
            $payload['file_name'] = '';
            $payload['file_type'] = '';
            $payload['file_size'] = '';
        }

        return $payload;
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        return response()->json($this->buildResponsePayload($request, $context));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $resource = teacherResourceModel::where([
            'id' => $id,
            'sub_institute_id' => $context['sub_institute_id'],
        ])->first();

        if (!$resource) {
            return response()->json([
                'status' => 0,
                'message' => 'Teacher resource not found.',
            ], 404);
        }

        $request->merge([
            'standard_id' => $request->input('standard_id', $resource->standard_id),
            'subject_id' => $request->input('subject_id', $resource->subject_id),
            'chapter_id' => $request->input('chapter_id', $resource->chapter_id),
            'topic_id' => $request->input('topic_id', $resource->topic_id),
        ]);

        $payload = $this->buildResponsePayload($request, $context);
        $payload['edit_record'] = $resource;

        return response()->json($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        if (!$request->hasFile('teacher_file')) {
            return response()->json([
                'status' => 0,
                'message' => 'teacher_file is required.',
            ], 422);
        }

        if ((string) $request->input('title', '') === '') {
            return response()->json([
                'status' => 0,
                'message' => 'title is required.',
            ], 422);
        }

        $payload = $this->buildPayloadFromRequest($request, $context);
        teacherResourceModel::insert($payload);

        return response()->json([
            'status' => 1,
            'message' => 'Teacher Resource Added Successfully',
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $resource = teacherResourceModel::where([
            'id' => $id,
            'sub_institute_id' => $context['sub_institute_id'],
        ])->first();

        if (!$resource) {
            return response()->json([
                'status' => 0,
                'message' => 'Teacher resource not found.',
            ], 404);
        }

        if ((string) $request->input('title', '') === '') {
            return response()->json([
                'status' => 0,
                'message' => 'title is required.',
            ], 422);
        }

        $payload = $this->buildPayloadFromRequest($request, $context, true);
        teacherResourceModel::where('id', $id)->update($payload);

        return response()->json([
            'status' => 1,
            'message' => 'Teacher Resource Updated Successfully',
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $context = $this->bootstrapRequestContext($request);
        $validation = $this->validateContext($context);
        if ($validation) {
            return $validation;
        }

        $resource = teacherResourceModel::where([
            'id' => $id,
            'sub_institute_id' => $context['sub_institute_id'],
        ])->first();

        if (!$resource) {
            return response()->json([
                'status' => 0,
                'message' => 'Teacher resource not found.',
            ], 404);
        }

        teacherResourceModel::where('id', $id)->delete();

        return response()->json([
            'status' => 1,
            'message' => 'Teacher Resource Deleted Successfully',
        ]);
    }
}
