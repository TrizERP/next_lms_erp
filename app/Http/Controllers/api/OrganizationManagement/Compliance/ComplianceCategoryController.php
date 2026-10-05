<?php

namespace App\Http\Controllers\api\OrganizationManagement\Compliance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrganizationManagement\ComplianceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Compliance Category master CRUD - Compliance Management, frontend-completion
 * pass. New controller; no prior category management existed anywhere for
 * this module (verified in the earlier audit). Follows the same
 * tenant-scoped-session / AuditLog conventions as ComplianceLibraryController.
 *
 * A school may only create/edit/delete categories scoped to its OWN tenant.
 * The platform-wide defaults (sub_institute_id NULL, seeded by
 * 2026_09_28_100000_create_compliance_categories_table) are read-only for
 * every tenant - editing/deleting one returns 403 rather than silently
 * mutating a row every other school also sees.
 */
class ComplianceCategoryController extends Controller
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

    /** GET /organization-management/compliance-library/categories */
    public function index(Request $request)
    {
        $tenant = $this->tenant();

        $query = ComplianceCategory::availableFor($tenant);
        if (!$request->boolean('include_inactive')) {
            $query->active();
        }

        $categories = $query->orderBy('sort_order')->orderBy('name')->get();

        return $this->response($categories->map(fn (ComplianceCategory $category) => $this->present($category))->values());
    }

    /** POST /organization-management/compliance-library/categories */
    public function store(Request $request)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('compliance_categories', 'name')->where('sub_institute_id', $tenant)->whereNull('deleted_at')],
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        $data['sub_institute_id'] = $tenant;
        $data['created_by'] = $actorId;
        $data['updated_by'] = $actorId;
        $data['status'] = $data['status'] ?? true;

        $category = ComplianceCategory::create($data);

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_category_created',
            'entity_type' => 'compliance_categories',
            'entity_id' => $category->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($category), 'Category created successfully', 201);
    }

    /** PUT/PATCH /organization-management/compliance-library/categories/{id} */
    public function update(Request $request, $id)
    {
        $tenant = $this->tenant();
        $actorId = $this->actorId();

        $category = ComplianceCategory::where('id', $id)->first();
        if (!$category) {
            return $this->error('Category not found', 404);
        }
        if ((int) $category->sub_institute_id !== $tenant) {
            return $this->error(
                $category->sub_institute_id === null
                    ? 'This is a platform default category and cannot be modified.'
                    : 'Category not found',
                403
            );
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:191', Rule::unique('compliance_categories', 'name')->where('sub_institute_id', $tenant)->whereNull('deleted_at')->ignore($category->id)],
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        $data['updated_by'] = $actorId;
        $category->fill($data);
        $category->save();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_category_updated',
            'entity_type' => 'compliance_categories',
            'entity_id' => $category->id,
            'new_values' => $data,
        ]);

        return $this->response($this->present($category->fresh()), 'Category updated successfully');
    }

    /** DELETE /organization-management/compliance-library/categories/{id} */
    public function destroy(Request $request, $id)
    {
        $tenant = $this->tenant();

        $category = ComplianceCategory::where('id', $id)->first();
        if (!$category) {
            return $this->error('Category not found', 404);
        }
        if ((int) $category->sub_institute_id !== $tenant) {
            return $this->error(
                $category->sub_institute_id === null
                    ? 'This is a platform default category and cannot be deleted.'
                    : 'Category not found',
                403
            );
        }

        $category->delete();

        AuditLog::record([
            'module' => 'organization_management',
            'action' => 'compliance_category_deleted',
            'entity_type' => 'compliance_categories',
            'entity_id' => (int) $id,
        ]);

        return $this->response(['id' => (int) $id], 'Category deleted successfully');
    }

    private function present(ComplianceCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'name' => $category->name,
            'description' => $category->description,
            'sort_order' => (int) $category->sort_order,
            'status' => (bool) $category->status,
            'is_global' => $category->sub_institute_id === null,
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
