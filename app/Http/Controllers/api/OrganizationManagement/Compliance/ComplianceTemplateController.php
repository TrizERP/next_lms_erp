<?php

namespace App\Http\Controllers\api\OrganizationManagement\Compliance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrganizationManagement\ComplianceLibraryRecord;
use App\Models\OrganizationManagement\ComplianceTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Compliance Template CRUD - Compliance Management, frontend-completion pass.
 * Backs the register's "Create from Template" action (see
 * ComplianceLibraryController::index()'s `templates` payload for the picker
 * data). Tenant-vs-global ownership rule identical to
 * ComplianceCategoryController - see that controller's docblock.
 */
class ComplianceTemplateController extends Controller
{
    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        $userId = session()->get('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /** GET /organization-management/compliance-library/templates */
    public function index(Request $request)
    {
        $tenant = $this->tenant();

        $query = ComplianceTemplate::with('category')->availableFor($tenant);
        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        $templates = $query->orderBy('sort_order')->orderBy('name')->get();

        return $this->response($templates->map(fn (ComplianceTemplate $template) => $this->present($template))->values());
    }

    /** POST /organization-management/compliance-library/templates */
    public function store(Request $request)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $data = $this->validated($request);
        $data['sub_institute_id'] = $tenant;
        $data['created_by'] = $actorId;
        $data['updated_by'] = $actorId;
        $data['status'] = $data['status'] ?? true;

        $template = ComplianceTemplate::create($data);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_template_created',
            'entity_type' => 'compliance_templates',
            'entity_id' => $template->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($template->fresh('category')), 'Template created successfully', 201);
    }

    /** PUT/PATCH /organization-management/compliance-library/templates/{id} */
    public function update(Request $request, $id)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $template = ComplianceTemplate::find($id);
        if (!$template) {
            return $this->error('Template not found', 404);
        }
        if ((int) $template->sub_institute_id !== $tenant) {
            return $this->error(
                $template->sub_institute_id === null
                    ? 'This is a platform default template and cannot be modified.'
                    : 'Template not found',
                403
            );
        }

        $data = $this->validated($request, sometimes: true);
        $data['updated_by'] = $actorId;
        $template->fill($data);
        $template->save();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_template_updated',
            'entity_type' => 'compliance_templates',
            'entity_id' => $template->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($template->fresh('category')), 'Template updated successfully');
    }

    /** POST /organization-management/compliance-library/templates/{id}/duplicate */
    public function duplicate(Request $request, $id)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $template = ComplianceTemplate::availableFor($tenant)->find($id);
        if (!$template) {
            return $this->error('Template not found', 404);
        }

        $copy = ComplianceTemplate::create([
            'sub_institute_id' => $tenant,
            'name' => $template->name . ' (Copy)',
            'description' => $template->description,
            'category_id' => $template->category_id,
            'default_frequency' => $template->default_frequency,
            'default_custom_frequency_details' => $template->default_custom_frequency_details,
            'default_priority' => $template->default_priority,
            'sort_order' => $template->sort_order,
            'status' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return $this->response($this->present($copy->fresh('category')), 'Template duplicated successfully', 201);
    }

    /** DELETE /organization-management/compliance-library/templates/{id} */
    public function destroy(Request $request, $id)
    {
        $tenant = $this->tenant();

        $template = ComplianceTemplate::find($id);
        if (!$template) {
            return $this->error('Template not found', 404);
        }
        if ((int) $template->sub_institute_id !== $tenant) {
            return $this->error(
                $template->sub_institute_id === null
                    ? 'This is a platform default template and cannot be deleted.'
                    : 'Template not found',
                403
            );
        }

        $template->delete();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_template_deleted',
            'entity_type' => 'compliance_templates',
            'entity_id' => (int) $id,
        ]);

        return $this->response(['id' => (int) $id], 'Template deleted successfully');
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        $required = $sometimes ? 'sometimes|required' : 'required';

        return $request->validate([
            'name' => "{$required}|string|max:191",
            'description' => 'nullable|string',
            'category_id' => 'nullable|integer|exists:compliance_categories,id',
            'default_frequency' => ['nullable', 'string', Rule::in(ComplianceLibraryRecord::FREQUENCIES)],
            'default_custom_frequency_details' => 'nullable|string|max:191',
            'default_priority' => ['nullable', 'string', Rule::in(ComplianceLibraryRecord::PRIORITIES)],
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);
    }

    private function present(ComplianceTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'category_id' => $template->category_id ? (int) $template->category_id : null,
            'category_name' => optional($template->category)->name,
            'default_frequency' => $template->default_frequency,
            'default_custom_frequency_details' => $template->default_custom_frequency_details,
            'default_priority' => $template->default_priority,
            'sort_order' => (int) $template->sort_order,
            'status' => (bool) $template->status,
            'is_global' => $template->sub_institute_id === null,
        ];
    }

    private function response($data, string $message = 'Success', int $code = 200)
    {
        return response()->json(['status' => 1, 'message' => $message, 'data' => $data], $code);
    }

    private function error(string $message, int $code = 400)
    {
        return response()->json(['status' => 0, 'message' => $message], $code);
    }
}
