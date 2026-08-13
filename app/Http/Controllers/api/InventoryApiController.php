<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InventoryApiController extends Controller
{
    use GetsJwtToken;

    private const MENU_LINKS = [
        'requisitions' => ['add_requisition.index'],
        'requisition-approvals' => ['requisition_approved.index'],
        'quotations' => ['add_inventory_item_quotation.index'],
        'purchase-orders' => ['add_inventory_generate_po.index'],
        'purchase-order-negotiations' => ['add_inventory_negotiate_po.index'],
        'receivables' => ['show_inventory_item_receivable.index'],
        'allocations' => ['show_inventory_item_allocation.index'],
        'returns' => ['show_inventory_item_return.index'],
        'defectives' => ['add_inventory_item_defective.index'],
        'master-setups' => ['add_inventory_master_setup.index'],
        'item-categories' => ['add_inventory_item_category_master.index'],
        'item-sub-categories' => ['add_inventory_item_sub_category_master.index'],
        'items' => ['add_inventory_item.index'],
        'taxes' => ['add_inventory_item_tax_master.index'],
        'vendors' => ['add_inventory_vendor_master.index'],
        'direct-purchases' => ['add_item_direct_purchase.index'],
        'reports/staff-wise' => ['inventory_staff_wise_report.index'],
        'reports/delivery-status' => ['inventory_delivery_status_report.index'],
        'reports/requisitions' => ['inventory_requisition_report.index'],
        'reports/item-wise' => ['inventory_item_wise_report.index'],
        'reports/overall-items' => ['inventory_overall_item_report.index'],
    ];

    private const MASTER_TABLES = [
        'master-setups' => 'inventory_master_setup',
        'item-categories' => 'inventory_item_category_master',
        'item-sub-categories' => 'inventory_item_sub_category_master',
        'items' => 'inventory_item_master',
        'taxes' => 'inventory_tax_master',
        'vendors' => 'inventory_vendor_master',
    ];

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
            'syear' => 'required|integer',
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
        $request->attributes->set('inventory_actor', $actor);
        return null;
    }

    private function guard(Request $request, string $module, string $action)
    {
        if ($response = $this->context($request)) return $response;
        if (! isset(self::MENU_LINKS[$module])) return $this->failure('Unknown Inventory module.', 404);
        $actor = $request->attributes->get('inventory_actor');
        if (in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) return null;

        $menuId = DB::table('tblmenumaster')->where('status', 1)->whereIn('link', self::MENU_LINKS[$module])->value('id');
        $individual = $menuId ? DB::table('tblindividual_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('user_id', $actor->id)->where('sub_institute_id', $actor->sub_institute_id)->first() : null;
        $rights = $individual ?: ($menuId ? DB::table('tblgroupwise_rights')
            ->where('menu_id', $menuId)->where('profile_id', $actor->user_profile_id)
            ->where('sub_institute_id', $actor->sub_institute_id)->first() : null);
        $column = ['view' => 'can_view', 'add' => 'can_add', 'edit' => 'can_edit', 'delete' => 'can_delete'][$action];
        if (! (bool) ($rights->{$column} ?? false)) {
            return $this->failure("You do not have permission to {$action} this Inventory module.", 403);
        }
        return null;
    }

    private function options(Request $request): array
    {
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        return [
            'categories' => DB::table('inventory_item_category_master')->select('id', 'title as name')->where('sub_institute_id', $tenant)->where('status', 'Yes')->orderBy('title')->get(),
            'sub_categories' => DB::table('inventory_item_sub_category_master')->select('id', 'title as name', 'category_id')->where('sub_institute_id', $tenant)->where('status', 'Yes')->orderBy('title')->get(),
            'items' => DB::table('inventory_item_master')->select('id', 'title as name', 'category_id', 'sub_category_id', 'opening_stock', 'minimum_stock')->where('sub_institute_id', $tenant)->where('item_status', 'Active')->orderBy('title')->get(),
            'item_types' => DB::table('inventory_item_type')->select('id', 'title as name')->orderBy('title')->get(),
            'units' => DB::table('master_setup_select')->select('fieldvalue as id', 'fieldvalue as name')->where('type', 'item_unit')->orderBy('fieldvalue')->get(),
            'vendors' => DB::table('inventory_vendor_master')->select('id', 'vendor_name as name')->where('sub_institute_id', $tenant)->orderBy('vendor_name')->get(),
            'taxes' => DB::table('inventory_tax_master')->select('id', 'title as name', 'amount_percentage')->where('sub_institute_id', $tenant)->where('status', 'Yes')->orderBy('sort_order')->get(),
            'statuses' => DB::table('inventory_requisition_status_master')->select('id', 'title as name')->orderBy('sort_order')->get(),
            'yes_no_statuses' => collect([['id' => 'Yes', 'name' => 'Yes'], ['id' => 'No', 'name' => 'No']]),
            'item_statuses' => collect([['id' => 'Active', 'name' => 'Active'], ['id' => 'Inactive', 'name' => 'Inactive']]),
            'requisition_item_settings' => collect([['id' => 'items_with_chain', 'name' => 'Items With Category/Sub-category'], ['id' => 'items_without_chain', 'name' => 'Items Without Category/Sub-category']]),
            'users' => DB::table('tbluser')->select('id', DB::raw("concat_ws(' ', first_name, middle_name, last_name) as name"))->where('sub_institute_id', $tenant)->where('status', 1)->orderBy('first_name')->get(),
            'user_groups' => DB::table('tbluserprofilemaster')->select('id', 'name')->where('sub_institute_id', $tenant)->where('status', 1)->orderBy('sort_order')->get(),
            'departments' => DB::table('hrms_departments')->select('id', 'department as name')->where('sub_institute_id', $tenant)->orderBy('department')->get(),
            'purchase_orders' => DB::table('inventory_generate_po_details')->select('po_number as id', 'po_number as name')->where('sub_institute_id', $tenant)->where('syear', $syear)->distinct()->orderByDesc('po_number')->get(),
            'approved_purchase_orders' => DB::table('inventory_generate_po_details as po')->join('inventory_requisition_status_master as status', 'status.id', '=', 'po.po_approval_status')->select('po.po_number as id', 'po.po_number as name')->where('po.sub_institute_id', $tenant)->where('po.syear', $syear)->where('status.title', 'APPROVED')->distinct()->orderByDesc('po.po_number')->get(),
            'received_items' => DB::table('inventory_item_receivable_details as receipt')->join('inventory_item_master as item', 'item.id', '=', 'receipt.item_id')->select('item.id', 'item.title as name')->where('receipt.sub_institute_id', $tenant)->where('receipt.syear', $syear)->distinct()->orderBy('item.title')->get(),
            'return_items' => DB::table('inventory_allocation_details as allocation')->join('inventory_item_master as item', 'item.id', '=', 'allocation.item_id')->select('item.id', 'item.title as name')->where('allocation.sub_institute_id', $tenant)->where('allocation.syear', $syear)->where('item.item_type_id', 2)->distinct()->orderBy('item.title')->get(),
            'quotations' => DB::table('inventory_item_quotation_details')->select('id', DB::raw("concat('Quotation #', id) as name"))->where('sub_institute_id', $tenant)->where('syear', $syear)->orderByDesc('id')->get(),
            'quotation_vendors' => DB::table('inventory_vendor_master')->select('id', 'vendor_name as name')->where('sub_institute_id', $tenant)->orderBy('vendor_name')->get(),
            'quotation_items' => DB::table('inventory_item_master')->select('id', 'title as name')->where('sub_institute_id', $tenant)->orderBy('title')->get(),
            'po_vendors' => DB::table('inventory_vendor_master as vendor')->join('inventory_item_quotation_details as quotation', 'quotation.vendor_id', '=', 'vendor.id')->select('vendor.id', 'vendor.vendor_name as name')->where('quotation.sub_institute_id', $tenant)->where('quotation.syear', $syear)->distinct()->orderBy('vendor.vendor_name')->get(),
            'po_quotation_items' => DB::table('inventory_item_quotation_details as quotation')->join('inventory_item_master as item', 'item.id', '=', 'quotation.item_id')->select('quotation.item_id as id', 'item.title as name', 'quotation.vendor_id', 'quotation.price')->where('quotation.sub_institute_id', $tenant)->where('quotation.syear', $syear)->orderBy('item.title')->get(),
            'po_numbers' => collect([['id' => $this->nextPoNumber($tenant, $syear), 'name' => $this->nextPoNumber($tenant, $syear)]]),
            'po_items' => DB::table('inventory_negotiate_po_details as inp')->join('inventory_item_master as i', 'i.id', '=', 'inp.item_id')->leftJoin('inventory_item_receivable_details as ir', function ($join) { $join->on('ir.PURCHASE_ORDER_NO', '=', 'inp.po_number')->on('ir.ITEM_ID', '=', 'inp.item_id'); })->select('inp.item_id', 'i.title as item_name', 'inp.qty', DB::raw('IFNULL(ir.PREVIOUS_RECEIVED_QTY,0) as previous_receive_qty'), DB::raw('IFNULL(ir.ACTUAL_RECEIVED_QTY,0) as actual_received_qty'), DB::raw('IFNULL(ir.PENDING_QTY,0) as pending_qty'), 'ir.REMARKS', 'ir.WARRANTY_START_DATE', 'ir.WARRANTY_END_DATE', 'ir.BILL_NO', 'ir.BILL_DATE', 'ir.CHALLAN_NO', 'ir.CHALLAN_DATE')->where('inp.sub_institute_id', $tenant)->where('inp.syear', $syear)->orderByDesc('inp.id')->get(),
            'requisitions' => DB::table('inventory_requisition_details as requisition')->join('inventory_item_master as item', 'item.id', '=', 'requisition.item_id')->select('requisition.id', DB::raw("concat(requisition.requisition_no, ' - ', item.title) as name"))->where('requisition.sub_institute_id', $tenant)->where('requisition.syear', $syear)->orderByDesc('requisition.id')->get(),
            'requisition_users' => $this->requisitionUsers($request),
            'requisition_categories' => DB::table('inventory_item_category_master')->select('id', 'title as name')->where('sub_institute_id', $tenant)->orderBy('title')->get(),
            'requisition_sub_categories' => DB::table('inventory_item_sub_category_master')->select('id', 'title as name', 'category_id')->where('sub_institute_id', $tenant)->orderBy('title')->get(),
            'requisition_items' => DB::table('inventory_item_master')->select('id', 'title as name', 'sub_category_id')->where('sub_institute_id', $tenant)->where('item_status', 'Active')->orderBy('title')->get(),
            'requisition_settings' => $this->requisitionSettings($tenant, $syear),
            'direct_purchase_settings' => $this->directPurchaseSettings($tenant),
            'requisition_numbers' => collect([['id' => $this->nextRequisitionNumber($tenant, $syear), 'name' => $this->nextRequisitionNumber($tenant, $syear)]]),
        ];
    }

    public function poItems(Request $request)
    {
        $poNumber = $request->input('po_number');
        if (!$poNumber) return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => []]);
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $items = DB::table('inventory_negotiate_po_details as inp')
            ->join('inventory_item_master as i', 'i.id', '=', 'inp.item_id')
            ->leftJoin('inventory_item_receivable_details as ir', function ($join) {
                $join->on('ir.PURCHASE_ORDER_NO', '=', 'inp.po_number')->on('ir.ITEM_ID', '=', 'inp.item_id');
            })
            ->select('inp.item_id', 'i.title as item_name', 'inp.qty', DB::raw('IFNULL(ir.PREVIOUS_RECEIVED_QTY,0) as previous_receive_qty'), DB::raw('IFNULL(ir.ACTUAL_RECEIVED_QTY,0) as actual_received_qty'), DB::raw('IFNULL(ir.PENDING_QTY,0) as pending_qty'), 'ir.REMARKS', 'ir.WARRANTY_START_DATE', 'ir.WARRANTY_END_DATE', 'ir.BILL_NO', 'ir.BILL_DATE', 'ir.CHALLAN_NO', 'ir.CHALLAN_DATE')
            ->where('inp.sub_institute_id', $tenant)->where('inp.syear', $syear)->where('inp.po_number', $poNumber)
            ->orderByDesc('inp.id')->get();
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => $items]);
    }

    private function requisitionUsers(Request $request)
    {
        $actor = $request->attributes->get('inventory_actor');
        return DB::table('tbluser')->select('id', DB::raw("concat_ws(' ', first_name, middle_name, last_name) as name"))
            ->where('sub_institute_id', $request->integer('sub_institute_id'))
            ->when(! in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true), fn ($query) => $query->where('id', $actor->id))
            ->orderBy('first_name')->get();
    }

    private function requisitionSettings(int $tenant, int $syear)
    {
        $setting = DB::table('inventory_master_setup')->where('sub_institute_id', $tenant)->where('syear', $syear)->value('item_setting_for_requisition');
        return $setting ? collect([['id' => $setting, 'name' => $setting]]) : collect();
    }

    private function directPurchaseSettings(int $tenant)
    {
        $setting = DB::table('inventory_master_setup')->where('sub_institute_id', $tenant)->orderByDesc('id')->value('item_setting_for_requisition');
        return $setting ? collect([['id' => $setting, 'name' => $setting]]) : collect();
    }

    private function nextRequisitionNumber(int $tenant, int $syear): string
    {
        $last = (int) DB::table('inventory_requisition_details')->where('sub_institute_id', $tenant)->where('syear', $syear)
            ->selectRaw('coalesce(max(substring(requisition_no, 5, 6)), 1) as last_number')->value('last_number');
        $next = $last + 1;
        if (strlen((string) $next) === 1) return 'REQ-00'.$next;
        if (strlen((string) $next) === 2) return 'REQ-0'.$next;
        return strlen((string) $next) === 3 ? 'REQ-'.$syear.$next : 'REQ-'.$next;
    }

    private function nextPoNumber(int $tenant, int $syear): string
    {
        $last = (int) DB::table('inventory_generate_po_details')->where('sub_institute_id', $tenant)->where('syear', $syear)
            ->selectRaw('coalesce(max(substring(po_number, 6)), 0) as last_number')->value('last_number');
        return $syear.'/'.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function index(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'view')) return $response;
        $records = $this->records($request, $module);
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => array_merge(['records' => $records], $this->options($request))]);
    }

    public function reportIndex(Request $request, string $module)
    {
        return $this->index($request, 'reports/'.$module);
    }

    private function records(Request $request, string $module)
    {
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        if (isset(self::MASTER_TABLES[$module])) {
            $table = self::MASTER_TABLES[$module];
            if ($module === 'master-setups') {
                return DB::table($table)->select('ID as id', 'GST_REGISTRATION_NO as gst_registration_no', 'GST_REGISTRATION_DATE as gst_registration_date', 'CST_REGISTRATION_NO as cst_registration_no', 'CST_REGISTRATION_DATE as cst_registration_date', 'PO_NO_PREFIX as po_no_prefix', 'ITEM_SETTING_FOR_REQUISITION as item_setting_for_requisition')->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear)->orderByDesc('ID')->get();
            }
            if (in_array($module, ['vendors', 'taxes'], true)) {
                return DB::table($table)->where('sub_institute_id', $tenant)->orderByDesc('id')->get();
            }
            if ($module === 'item-categories') {
                return DB::table($table)->where('sub_institute_id', $tenant)->orderByDesc('id')->get();
            }
            if ($module === 'item-sub-categories') {
                return DB::table('inventory_item_sub_category_master as sub_category')
                    ->join('inventory_item_category_master as category', 'category.id', '=', 'sub_category.category_id')
                    ->select('sub_category.*', 'category.title as category_name')
                    ->where('sub_category.sub_institute_id', $tenant)->orderByDesc('sub_category.id')->get();
            }
            if ($module === 'items') {
                return DB::table('inventory_item_master as item')
                    ->join('inventory_item_category_master as category', 'category.id', '=', 'item.category_id')
                    ->join('inventory_item_sub_category_master as sub_category', 'sub_category.id', '=', 'item.sub_category_id')
                    ->join('inventory_item_type as item_type', 'item_type.id', '=', 'item.item_type_id')
                    ->select('item.*', 'category.title as category_name', 'sub_category.title as sub_category_name', 'item_type.title as item_type_name')
                    ->where('item.sub_institute_id', $tenant)->orderByDesc('item.id')->get();
            }
            return DB::table($table)->where('sub_institute_id', $tenant)->where('syear', $syear)->orderByDesc('id')->get();
        }
        switch ($module) {
            case 'requisitions':
            case 'requisition-approvals':
            case 'reports/requisitions':
                $query = DB::table('inventory_requisition_details as requisition')
                    ->leftJoin('inventory_item_master as item', 'item.id', '=', 'requisition.item_id')
                    ->leftJoin('tbluser as user', 'user.id', '=', 'requisition.requisition_by')
                    ->leftJoin('tbluser as approver', 'approver.id', '=', 'requisition.requisition_approved_by')
                    ->leftJoin('inventory_requisition_status_master as status', 'status.id', '=', 'requisition.requisition_status')
                    ->select('requisition.*', 'item.title as item_name', 'item.opening_stock', DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as requisition_by_name"), DB::raw("concat_ws(' ', approver.first_name, approver.middle_name, approver.last_name) as requisition_approved_by"), 'status.title as status')
                    ->where('requisition.sub_institute_id', $tenant);
                if ($module !== 'reports/requisitions') $query->where('requisition.syear', $syear);
                if ($module === 'requisitions') {
                    $actor = $request->attributes->get('inventory_actor');
                    if (! in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) $query->where('requisition.requisition_by', $actor->id);
                }
                $this->dateFilters($query, $request, 'requisition.requisition_date');
                if ($request->filled('requisition_by')) $query->where('requisition.requisition_by', $request->integer('requisition_by'));
                if ($request->filled('item_id')) $query->where('requisition.item_id', $request->integer('item_id'));
                if ($request->filled('requisition_status')) $query->where('requisition.requisition_status', $request->integer('requisition_status'));
                return $query->orderByDesc('requisition.requisition_no')->get();
            case 'quotations':
                return DB::table('inventory_item_quotation_details as quotation')
                    ->leftJoin('inventory_item_master as item', 'item.id', '=', 'quotation.item_id')
                    ->join('inventory_vendor_master as vendor', 'vendor.id', '=', 'quotation.vendor_id')
                    ->join('inventory_requisition_status_master as status', 'status.id', '=', 'quotation.approved_status')
                    ->join('tbluser as approver', 'approver.id', '=', 'quotation.approved_by')
                    ->select('quotation.*', 'item.title as item_name', 'vendor.vendor_name', 'status.title as approval_status', DB::raw("concat_ws(' ', approver.first_name, approver.middle_name, approver.last_name) as approved_by_name"))
                    ->where('quotation.sub_institute_id', $tenant)->where('quotation.syear', $syear)->orderByDesc('quotation.id')->get();
            case 'purchase-orders':
                return DB::table('inventory_generate_po_details as po')->join('inventory_item_master as item', 'item.id', '=', 'po.item_id')->join('inventory_vendor_master as vendor', 'vendor.id', '=', 'po.vendor_id')->leftJoin('inventory_requisition_status_master as status', 'status.id', '=', 'po.po_approval_status')->select('po.*', 'item.title as item_name', 'vendor.vendor_name', 'vendor.company_name', 'status.title as status')->where('po.sub_institute_id', $tenant)->where('po.syear', $syear)->orderByDesc('po.id')->get();
            case 'purchase-order-negotiations':
                return DB::table('inventory_generate_po_details as po')
                    ->join('inventory_item_master as item', 'item.id', '=', 'po.item_id')
                    ->join('inventory_vendor_master as vendor', 'vendor.id', '=', 'po.vendor_id')
                    ->leftJoin('inventory_requisition_status_master as status', 'status.id', '=', 'po.po_approval_status')
                    ->leftJoin('inventory_negotiate_po_details as np', function ($join) {
                        $join->on('np.item_id', '=', 'po.item_id')
                             ->on('np.po_number', '=', 'po.po_number')
                             ->on('np.sub_institute_id', '=', 'po.sub_institute_id')
                             ->on('np.syear', '=', 'po.syear');
                    })
                    ->select('po.*', 'item.title as item_name', 'vendor.vendor_name', 'vendor.company_name', 'status.title as status',
                        DB::raw('COALESCE(np.price, po.price) as price'),
                        DB::raw('COALESCE(np.qty, po.qty) as qty'),
                        DB::raw('COALESCE(np.amount, po.amount) as amount'),
                        DB::raw('COALESCE(np.dis_per, po.dis_per) as dis_per'),
                        DB::raw('COALESCE(np.dis_amount_value, po.dis_amount_value) as dis_amount_value'),
                        DB::raw('COALESCE(np.after_dis_amount, po.after_dis_amount) as after_dis_amount'),
                        DB::raw('COALESCE(np.tax_per, po.tax_per) as tax_per'),
                        DB::raw('COALESCE(np.tax_amount_value, po.tax_amount_value) as tax_amount_value'),
                        DB::raw('COALESCE(np.after_tax_amount, po.after_tax_amount) as after_tax_amount'),
                        DB::raw('COALESCE(np.amount_per_item, po.amount_per_item) as amount_per_item')
                    )
                    ->where('po.sub_institute_id', $tenant)->where('po.syear', $syear)
                    ->orderByDesc('po.id')->get();
            case 'receivables':
                return DB::table('inventory_item_receivable_details as receipt')
                    ->leftJoin('inventory_item_master as item', 'item.id', '=', 'receipt.item_id')
                    ->select(
                        'receipt.ID as id',
                        'receipt.PURCHASE_ORDER_NO as po_number',
                        'receipt.ITEM_ID as item_id',
                        'receipt.ORDER_QTY as qty',
                        'receipt.PREVIOUS_RECEIVED_QTY as previous_received_qty',
                        'receipt.ACTUAL_RECEIVED_QTY as actual_received_qty',
                        'receipt.PENDING_QTY as pending_qty',
                        'receipt.REMARKS as remarks',
                        'receipt.WARRANTY_START_DATE as warranty_start_date',
                        'receipt.WARRANTY_END_DATE as warranty_end_date',
                        'receipt.BILL_NO as bill_no',
                        'receipt.BILL_DATE as bill_date',
                        'receipt.CHALLAN_NO as challan_no',
                        'receipt.CHALLAN_DATE as challan_date',
                        'receipt.RECEIVED_BY as received_by',
                        'receipt.RECEIVED_DATE as received_date',
                        'receipt.GATEPASS_NO as gatepass_no',
                        'receipt.CHEQUE_NO as cheque_no',
                        'receipt.BANK_NAME as bank_name',
                        'item.title as item_name'
                    )
                    ->where('receipt.sub_institute_id', $tenant)->where('receipt.syear', $syear)
                    ->orderByDesc('receipt.id')->get();
            case 'allocations':
                $query = DB::table('inventory_allocation_details as allocation')
                    ->leftJoin('inventory_requisition_details as requisition', 'requisition.id', '=', 'allocation.requisition_details_id')
                    ->leftJoin('inventory_item_master as item', 'item.id', '=', 'allocation.item_id')
                    ->leftJoin('tbluser as user', 'user.id', '=', 'requisition.requisition_by')
                    ->select(
                        'allocation.ID as id',
                        'allocation.REQUISITION_DETAILS_ID as requisition_details_id',
                        'allocation.REQUISITION_ID as requisition_by',
                        'allocation.LOCATION_OF_MATERIAL as location_of_material',
                        'allocation.PERSON_RESPONSIBLE as person_responsible',
                        'allocation.ITEM_ID as item_id',
                        'allocation.CREATED_BY as created_by',
                        'allocation.CREATED_ON as created_on',
                        'item.title as item_name',
                        'requisition.approved_qty',
                        DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as requisition_by_name")
                    )
                    ->where('allocation.sub_institute_id', $tenant)->where('allocation.syear', $syear);
                $this->dateFilters($query, $request, 'requisition.requisition_date');
                if ($request->filled('item_id')) $query->where('allocation.item_id', $request->integer('item_id'));
                if ($request->filled('requisition_by')) $query->where('requisition.requisition_by', $request->integer('requisition_by'));
                return $query->orderByDesc('allocation.id')->get();
            case 'returns':
                return DB::table('inventory_item_return_details as returned')
                    ->leftJoin('inventory_item_master as item', 'item.id', '=', 'returned.item_id')
                    ->leftJoin('tbluser as user', 'user.id', '=', 'returned.requisition_by')
                    ->select(
                        'returned.ID as id',
                        'returned.REQUISITION_ID as requisition_id',
                        'returned.ITEM_ID as item_id',
                        'returned.RETURN_DATE as return_date',
                        'returned.REMARKS as remarks',
                        'returned.RETURN_QTY as return_qty',
                        'returned.REQUISITION_BY as requisition_by',
                        'returned.CREATED_BY as created_by',
                        'returned.CREATED_ON as created_on',
                        'item.title as item_name',
                        DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as user_name")
                    )
                    ->where('returned.sub_institute_id', $tenant)->where('returned.syear', $syear)
                    ->orderByDesc('returned.id')->get();
            case 'defectives':
                return DB::table('inventory_item_defective_details as defective')
                    ->leftJoin('inventory_item_master as item', 'item.id', '=', 'defective.item_id')
                    ->select(
                        'defective.ID as id',
                        'defective.ITEM_ID as item_id',
                        'defective.WARRANTY_START_DATE as warranty_start_date',
                        'defective.WARRANTY_END_DATE as warranty_end_date',
                        'defective.DEFECT_REMARKS as defect_remarks',
                        'defective.ITEM_GIVEN_TO as item_given_to',
                        'defective.ESTIMATED_RECEIVED_DATE as estimated_received_date',
                        'defective.ACTUAL_RECEIVED_DATE as actual_received_date',
                        'defective.REMARKS as remarks',
                        'defective.CREATED_BY as created_by',
                        'defective.CREATED_ON as created_on',
                        'item.title as item_name'
                    )
                    ->where('defective.sub_institute_id', $tenant)->where('defective.syear', $syear)
                    ->orderByDesc('defective.id')->get();
            case 'direct-purchases':
                return DB::table('inventory_item_direct_purchase as purchase')
                    ->join('inventory_vendor_master as vendor', function ($join) { $join->on('vendor.id', '=', 'purchase.vendor_id')->on('vendor.sub_institute_id', '=', 'purchase.sub_institute_id'); })
                    ->leftJoin('inventory_item_category_master as category', function ($join) { $join->on('category.id', '=', 'purchase.category_id')->on('category.sub_institute_id', '=', 'purchase.sub_institute_id'); })
                    ->leftJoin('inventory_item_sub_category_master as sub_category', function ($join) { $join->on('sub_category.id', '=', 'purchase.sub_category_id')->on('sub_category.sub_institute_id', '=', 'purchase.sub_institute_id'); })
                    ->join('inventory_item_master as item', function ($join) { $join->on('item.id', '=', 'purchase.item_id')->on('item.sub_institute_id', '=', 'purchase.sub_institute_id'); })
                    ->join('tbluser as user', function ($join) { $join->on('user.id', '=', 'purchase.created_by')->on('user.sub_institute_id', '=', 'purchase.sub_institute_id')->where('user.status', 1); })
                    ->select('purchase.*', 'vendor.vendor_name', 'category.title as category_name', 'sub_category.title as sub_category_name', 'item.title as item_name', DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as created_by_name"))
                    ->where('purchase.sub_institute_id', $tenant)->where('purchase.syear', $syear)->orderByDesc('purchase.id')->get();
            case 'reports/staff-wise':
                $query = DB::table('inventory_requisition_details as requisition')->join('inventory_item_master as item', 'item.id', '=', 'requisition.item_id')->join('inventory_item_category_master as category', 'category.id', '=', 'item.category_id')->join('tbluser as user', function ($join) { $join->on('user.id', '=', 'requisition.requisition_by')->where('user.status', 1); })->select(DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as requisition_by_name"), 'requisition.requisition_no', 'requisition.requisition_by', 'requisition.item_qty', 'requisition.approved_qty', 'requisition.requisition_approved_date as requisition_date', 'item.title as item_name', 'category.title as category')->where('requisition.sub_institute_id', $tenant);
                $this->dateFilters($query, $request, 'requisition.requisition_approved_date');
                if ($request->filled('requisition_by')) $query->where('requisition.requisition_by', $request->integer('requisition_by'));
                return $query->get();
            case 'reports/delivery-status':
                $query = DB::table('inventory_requisition_details as requisition')->join('inventory_item_master as item', 'item.id', '=', 'requisition.item_id')->join('inventory_allocation_details as allocation', function ($join) { $join->on('allocation.requisition_details_id', '=', 'requisition.id')->on('allocation.item_id', '=', 'requisition.item_id'); })->join('tbluser as user', 'user.id', '=', 'requisition.requisition_by')->leftJoin('tbluser as approver', 'approver.id', '=', 'requisition.requisition_approved_by')->join('inventory_requisition_status_master as status', 'status.id', '=', 'requisition.requisition_status')->select(DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as requisition_by_name"), 'requisition.item_unit', 'requisition.requisition_by', 'requisition.requisition_date', 'requisition.requisition_no', 'item.title as item_name', 'requisition.item_qty', 'requisition.expected_delivery_time', 'requisition.remarks', 'status.title as requisition_status', DB::raw("concat_ws(' ', approver.first_name, approver.middle_name, approver.last_name) as requisition_approved_by"), 'requisition.requisition_approved_remarks', 'requisition.requisition_approved_date', DB::raw("'Delivered' as delivery_status"), 'allocation.created_on as delivery_date')->where('requisition.sub_institute_id', $tenant)->where('requisition.syear', $syear);
                $this->dateFilters($query, $request, 'allocation.created_on');
                if ($request->filled('requisition_by')) $query->where('requisition.requisition_by', $request->integer('requisition_by'));
                return $query->get();
            case 'reports/item-wise':
                $query = DB::table('inventory_requisition_details as requisition')->join('inventory_allocation_details as allocation', 'allocation.requisition_details_id', '=', 'requisition.id')->join('inventory_item_master as item', 'item.id', '=', 'requisition.item_id')->join('tbluser as user', function ($join) { $join->on('user.id', '=', 'requisition.requisition_by')->where('user.status', 1); })->select(DB::raw("concat_ws(' ', user.first_name, user.middle_name, user.last_name) as requisition_by_name"), 'requisition.requisition_no', 'requisition.requisition_by', 'requisition.item_id', 'requisition.item_qty', 'requisition.approved_qty', 'requisition.requisition_approved_date as requisition_date', 'item.title as item_name')->where('requisition.sub_institute_id', $tenant)->where('requisition.syear', $syear);
                $this->dateFilters($query, $request, 'requisition.requisition_approved_date');
                if ($request->filled('item_id')) $query->where('requisition.item_id', $request->integer('item_id'));
                return $query->get();
            case 'reports/overall-items':
                return DB::table('inventory_item_master as item')
                    ->leftJoin('inventory_item_receivable_details as receipt', 'receipt.item_id', '=', 'item.id')
                    ->leftJoin('inventory_allocation_details as allocation', 'allocation.item_id', '=', 'item.id')
                    ->leftJoin('inventory_requisition_details as requisition', 'requisition.id', '=', 'allocation.requisition_details_id')
                    ->leftJoin('inventory_item_lost_details as lost', 'lost.item_id', '=', 'item.id')
                    ->leftJoin('inventory_item_return_details as returned', 'returned.item_id', '=', 'item.id')
                    ->leftJoin('inventory_generate_po_details as po', function ($join) { $join->on('po.item_id', '=', 'item.id')->where('po.po_approval_status', 2); })
                    ->select('item.id', 'item.title as item_name', 'item.description', DB::raw('(item.opening_stock - coalesce(item.direct_purchase_stock, 0)) as opening_inventory_qty'), DB::raw('sum(coalesce(receipt.actual_received_qty, 0)) as purchase_qty'), DB::raw('sum(coalesce(requisition.approved_qty, 0)) as issue_qty'), DB::raw('count(distinct lost.id) as lost_sold_qty'), DB::raw('count(distinct returned.id) as returned_qty'), DB::raw('(item.opening_stock + sum(coalesce(receipt.actual_received_qty, 0)) - sum(coalesce(requisition.approved_qty, 0)) - count(distinct lost.id) + count(distinct returned.id)) as closing_inventory_value'), DB::raw('max(po.qty) as po_qty'), 'item.direct_purchase_stock')
                    ->where('item.item_status', 'Active')->where('item.sub_institute_id', $tenant)
                    ->when($request->filled('item_id'), fn ($query) => $query->where('item.id', $request->integer('item_id')))
                    ->groupBy('item.id', 'item.title', 'item.description', 'item.opening_stock', 'item.direct_purchase_stock')->orderBy('item.title')->get();
        }
        return [];
    }

    private function dateFilters($query, Request $request, string $column): void
    {
        if ($request->filled('from_date')) $query->whereDate($column, '>=', $request->input('from_date'));
        if ($request->filled('to_date')) $query->whereDate($column, '<=', $request->input('to_date'));
    }

    public function store(Request $request, string $module)
    {
        if ($response = $this->guard($request, $module, 'add')) return $response;
        return $this->save($request, $module);
    }

    public function update(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'edit')) return $response;
        return $this->save($request, $module, $id);
    }

    private function save(Request $request, string $module, ?int $id = null)
    {
        if (isset(self::MASTER_TABLES[$module])) return $this->saveMaster($request, $module, $id);
        if (str_starts_with($module, 'reports/')) return $this->failure('Reports are read-only.', 405);
        if ($module === 'purchase-orders' && is_array($request->input('items'))) return $this->savePurchaseOrder($request, $id);
        if ($module === 'purchase-order-negotiations' && is_array($request->input('items'))) return $this->saveNegotiatePo($request, $id);
        if ($module === 'quotations' && is_array($request->input('items'))) return $this->saveQuotations($request, $id);
        if ($module === 'direct-purchases' && is_array($request->input('items'))) return $this->saveDirectPurchases($request, $id);
        if ($module === 'requisition-approvals' && is_array($request->input('approvals'))) return $this->saveRequisitionApprovals($request);
        if ($module === 'requisitions' && $id) return $this->updateRequisition($request, $id);
        if ($module === 'requisitions' && is_array($request->input('items'))) return $this->saveRequisitions($request);
        if ($module === 'receivables' && is_array($request->input('items'))) return $this->saveReceivables($request);

        $rules = $this->workflowRules($module);
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $user = $request->integer('user_id');

        try {
            DB::transaction(function () use ($request, $module, $id, $tenant, $syear, $user) {
                if ($module === 'requisition-approvals') {
                    $requisitionId = $id ?: $request->integer('requisition_id');
                    $row = DB::table('inventory_requisition_details')->where('id', $requisitionId)->where('sub_institute_id', $tenant)->where('syear', $syear)->lockForUpdate()->first();
                    if (! $row) throw new \RuntimeException('Requisition not found.');
                    if ($request->integer('approved_qty') > (float) $row->item_qty) throw new \RuntimeException('Approved quantity cannot exceed requested quantity.');
                    DB::table('inventory_requisition_details')->where('id', $requisitionId)->update(['approved_qty' => $request->input('approved_qty'), 'requisition_status' => $request->integer('requisition_status'), 'requisition_approved_remarks' => $request->input('remarks'), 'requisition_approved_by' => $user, 'requisition_approved_date' => now()->toDateString()]);
                    return;
                }
                [$table, $values] = $this->workflowValues($request, $module, $tenant, $syear, $user, $id);
                if ($values === []) return;
                if ($id) DB::table($table)->where('id', $id)->where('sub_institute_id', $tenant)->where('syear', $syear)->update($values);
                else DB::table($table)->insert($values);

                if ($module === 'direct-purchases') {
                    DB::table('inventory_item_master')->where('id', $request->integer('item_id'))->where('sub_institute_id', $tenant)->increment('opening_stock', $request->input('qty'));
                    DB::table('inventory_item_master')->where('id', $request->integer('item_id'))->update(['direct_purchase_stock' => $request->input('qty')]);
                }
            });
        } catch (\RuntimeException $exception) {
            return $this->failure($exception->getMessage());
        }
        return response()->json(['status_code' => 1, 'message' => $id ? 'Inventory record updated successfully.' : 'Inventory record created successfully.', 'data' => []]);
    }

    private function savePurchaseOrder(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'po_number' => 'required|string|max:50', 'vendor_id' => 'required|integer',
            'delivery_time' => 'nullable|date', 'po_place_of_delivery' => 'required|string',
            'payment_terms' => 'required|string', 'remarks' => 'required|string',
            'transportation_charge' => 'required|numeric|min:0', 'installation_charge' => 'required|numeric|min:0',
            'items' => 'required|array|min:1', 'items.*.item_id' => 'required|integer',
            'items.*.price' => 'required|numeric|min:0', 'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.dis_per' => 'required|numeric|min:0', 'items.*.tax_per' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        if ($id && count($request->input('items')) !== 1) return $this->failure('Only one item can be supplied when editing a PO.');
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        if (! $id && trim((string) $request->input('po_number')) !== $this->nextPoNumber($tenant, $syear)) return $this->failure('The PO number is no longer current. Refresh and try again.', 409);
        try {
            DB::transaction(function () use ($request, $id, $tenant, $syear) {
                foreach ($request->input('items') as $item) {
                    $quoted = DB::table('inventory_item_quotation_details')->where('item_id', (int) $item['item_id'])->where('vendor_id', $request->integer('vendor_id'))->where('sub_institute_id', $tenant)->where('syear', $syear)->exists();
                    if (! $quoted) throw new \RuntimeException('Selected item is not quoted by this vendor.');
                    $amount = (float) $item['price'] * (float) $item['qty'];
                    $discount = $amount * ((float) $item['dis_per'] / 100);
                    $afterDiscount = $amount - $discount;
                    $tax = $afterDiscount * ((float) $item['tax_per'] / 100);
                    $values = [
                        'syear' => $syear, 'sub_institute_id' => $tenant, 'po_number' => $request->input('po_number'),
                        'item_id' => (int) $item['item_id'], 'vendor_id' => $request->integer('vendor_id'),
                        'price' => $item['price'], 'qty' => $item['qty'], 'amount' => $amount,
                        'dis_per' => $item['dis_per'], 'dis_amount_value' => $discount, 'after_dis_amount' => $afterDiscount,
                        'tax_per' => $item['tax_per'], 'tax_amount_value' => $tax, 'after_tax_amount' => $afterDiscount + $tax,
                        'amount_per_item' => $amount, 'transportation_charge' => $request->input('transportation_charge'),
                        'installation_charge' => $request->input('installation_charge'), 'delivery_time' => $request->input('delivery_time') ?: null,
                        'po_place_of_delivery' => $request->input('po_place_of_delivery'), 'payment_terms' => $request->input('payment_terms'),
                        'remarks' => $request->input('remarks'), 'created_by' => $request->integer('user_id'),
                        'created_on' => now(), 'created_ip_address' => $request->ip(),
                    ];
                    if ($id) {
                        $updated = DB::table('inventory_generate_po_details')->where('id', $id)->where('sub_institute_id', $tenant)->where('syear', $syear)->update($values);
                        if (! $updated && ! DB::table('inventory_generate_po_details')->where('id', $id)->where('sub_institute_id', $tenant)->where('syear', $syear)->exists()) throw new \RuntimeException('Purchase order not found.');
                    } else DB::table('inventory_generate_po_details')->insert($values);
                }
            });
        } catch (\RuntimeException $exception) {
            return $this->failure($exception->getMessage());
        }
        return response()->json(['status_code' => 1, 'message' => $id ? 'PO Updated Successfully' : 'PO generated Successfully', 'data' => []]);
    }

    private function saveNegotiatePo(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'po_number' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.dis_per' => 'required|numeric|min:0',
            'items.*.tax_per' => 'required|numeric|min:0',
            'po_approval_status' => 'required|integer',
            'po_approval_remark' => 'nullable|string',
            'transportation_charge' => 'nullable|numeric|min:0',
            'installation_charge' => 'nullable|numeric|min:0',
            'delivery_time' => 'nullable|date',
            'po_place_of_delivery' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'remarks' => 'nullable|string',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $user = $request->integer('user_id');
        $poNumber = $request->input('po_number');
        try {
            DB::transaction(function () use ($request, $tenant, $syear, $user, $poNumber) {
                DB::table('inventory_generate_po_details')
                    ->where('po_number', $poNumber)
                    ->where('sub_institute_id', $tenant)
                    ->where('syear', $syear)
                    ->update([
                        'po_approval_status' => $request->integer('po_approval_status'),
                        'po_approval_remark' => $request->input('po_approval_remark'),
                        'po_approved_by' => $user,
                        'po_approved_date' => now(),
                    ]);
                foreach ($request->input('items') as $item) {
                    $po = DB::table('inventory_generate_po_details')
                        ->where('po_number', $poNumber)
                        ->where('item_id', (int) $item['item_id'])
                        ->where('sub_institute_id', $tenant)
                        ->where('syear', $syear)
                        ->lockForUpdate()
                        ->first();
                    if (! $po) throw new \RuntimeException('Purchase-order item not found.');
                    $amount = (float) $item['price'] * (float) $item['qty'];
                    $discount = $amount * ((float) $item['dis_per'] / 100);
                    $afterDiscount = $amount - $discount;
                    $tax = $afterDiscount * ((float) $item['tax_per'] / 100);
                    $approval = [
                        'po_approval_status' => $request->integer('po_approval_status'),
                        'po_approval_remark' => $request->input('po_approval_remark'),
                        'po_approved_by' => $user,
                        'po_approved_date' => now(),
                    ];
                    DB::table('inventory_negotiate_po_details')->updateOrInsert(
                        ['po_number' => $poNumber, 'item_id' => (int) $item['item_id'], 'sub_institute_id' => $tenant, 'syear' => $syear],
                        array_merge($approval, [
                            'vendor_id' => $po->vendor_id,
                            'price' => $item['price'],
                            'qty' => $item['qty'],
                            'amount' => $amount,
                            'dis_per' => $item['dis_per'],
                            'dis_amount_value' => $discount,
                            'after_dis_amount' => $afterDiscount,
                            'tax_per' => $item['tax_per'],
                            'tax_amount_value' => $tax,
                            'after_tax_amount' => $afterDiscount + $tax,
                            'amount_per_item' => $amount,
                            'transportation_charge' => $request->input('transportation_charge'),
                            'installation_charge' => $request->input('installation_charge'),
                            'delivery_time' => $request->input('delivery_time') ?: null,
                            'po_place_of_delivery' => $request->input('po_place_of_delivery'),
                            'payment_terms' => $request->input('payment_terms'),
                            'remarks' => $request->input('remarks'),
                            'created_by' => $user,
                            'created_on' => now(),
                            'created_ip_address' => $request->ip(),
                        ])
                    );
                }
            });
        } catch (\RuntimeException $exception) {
            return $this->failure($exception->getMessage());
        }
        return response()->json(['status_code' => 1, 'message' => $id ? 'Negotiate PO updated successfully.' : 'Negotiate PO created successfully.', 'data' => []]);
    }

    private function saveQuotations(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|integer', 'remarks' => 'required|string',
            'transportation_charge' => 'required|numeric|min:0', 'installation_charge' => 'required|numeric|min:0',
            'items' => 'required|array|min:1', 'items.*.item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0.01', 'items.*.price' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string|max:50', 'items.*.tax' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        if ($id && count($request->input('items')) !== 1) return $this->failure('Only one item can be supplied when editing a quotation.');
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $user = $request->integer('user_id');
        $vendorExists = DB::table('inventory_vendor_master')->where('id', $request->integer('vendor_id'))->where('sub_institute_id', $tenant)->exists();
        if (! $vendorExists) return $this->failure('Vendor not found for this institute.');
        try {
            DB::transaction(function () use ($request, $id, $tenant, $syear, $user) {
                foreach ($request->input('items') as $item) {
                    $itemExists = DB::table('inventory_item_master')->where('id', (int) $item['item_id'])->where('sub_institute_id', $tenant)->exists();
                    if (! $itemExists) throw new \RuntimeException('Item not found for this institute.');
                    $values = [
                        'item_id' => (int) $item['item_id'], 'vendor_id' => $request->integer('vendor_id'),
                        'transportation_charge' => $request->input('transportation_charge'),
                        'installation_charge' => $request->input('installation_charge'),
                        'qty' => $item['qty'], 'price' => $item['price'], 'total' => (float) $item['qty'] * (float) $item['price'],
                        'unit' => $item['unit'], 'tax' => $item['tax'], 'remarks' => $request->input('remarks'),
                    ];
                    if ($id) {
                        $updated = DB::table('inventory_item_quotation_details')->where('id', $id)->where('sub_institute_id', $tenant)->where('syear', $syear)->update($values);
                        if (! $updated && ! DB::table('inventory_item_quotation_details')->where('id', $id)->where('sub_institute_id', $tenant)->where('syear', $syear)->exists()) throw new \RuntimeException('Quotation not found.');
                    } else {
                        DB::table('inventory_item_quotation_details')->insert(array_merge($values, [
                            'syear' => $syear, 'sub_institute_id' => $tenant, 'approved_status' => 2,
                            'approved_date' => now()->toDateString(), 'approved_by' => $user,
                            'created_by' => $user, 'created_on' => now(), 'created_ip_address' => $request->ip(),
                        ]));
                    }
                }
            });
        } catch (\RuntimeException $exception) {
            return $this->failure($exception->getMessage());
        }
        return response()->json(['status_code' => 1, 'message' => $id ? 'Item Quotation Updated Successfully' : 'Item Quotation Added Successfully', 'data' => []]);
    }

    private function saveDirectPurchases(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'vendor_id' => 'required|integer', 'challan_no' => 'required|string|max:255',
            'challan_date' => 'required|date', 'bill_no' => 'required|string|max:255',
            'bill_date' => 'required|date', 'remarks' => 'required|string',
            'items' => 'required|array|min:1', 'items.*.item_id' => 'required|integer',
            'items.*.item_qty' => 'required|numeric|min:0.01', 'items.*.price' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        if ($id && count($request->input('items')) !== 1) return $this->failure('Only one item can be supplied when editing a direct purchase.');
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $user = $request->integer('user_id');
        try {
            DB::transaction(function () use ($request, $id, $tenant, $syear, $user) {
                if ($id) {
                    $old = DB::table('inventory_item_direct_purchase')->where('id', $id)->where('sub_institute_id', $tenant)->where('syear', $syear)->lockForUpdate()->first();
                    if (! $old) throw new \RuntimeException('Direct purchase not found.');
                    DB::table('inventory_item_master')->where('id', $old->item_id)->where('sub_institute_id', $tenant)->decrement('opening_stock', $old->item_qty);
                }
                foreach ($request->input('items') as $itemInput) {
                    $item = DB::table('inventory_item_master')->where('id', (int) $itemInput['item_id'])->where('sub_institute_id', $tenant)->lockForUpdate()->first();
                    if (! $item || $item->item_status !== 'Active') throw new \RuntimeException('Active inventory item not found.');
                    $quantity = (float) $itemInput['item_qty'];
                    $price = (float) $itemInput['price'];
                    $values = [
                        'vendor_id' => $request->integer('vendor_id'), 'category_id' => $item->category_id,
                        'sub_category_id' => $item->sub_category_id, 'item_id' => $item->id,
                        'item_qty' => $quantity, 'price' => $price, 'amount' => $quantity * $price,
                        'challan_no' => $request->input('challan_no'), 'challan_date' => $request->input('challan_date'),
                        'bill_no' => $request->input('bill_no'), 'bill_date' => $request->input('bill_date'),
                        'remarks' => $request->input('remarks'), 'sub_institute_id' => $tenant, 'syear' => $syear,
                    ];
                    if ($id) DB::table('inventory_item_direct_purchase')->where('id', $id)->update($values);
                    else DB::table('inventory_item_direct_purchase')->insert(array_merge($values, ['created_by' => $user, 'created_on' => now(), 'created_ip' => $request->ip()]));
                    DB::table('inventory_item_master')->where('id', $item->id)->where('sub_institute_id', $tenant)->increment('opening_stock', $quantity);
                    DB::table('inventory_item_master')->where('id', $item->id)->where('sub_institute_id', $tenant)->update(['direct_purchase_stock' => $quantity]);
                }
            });
        } catch (\RuntimeException $exception) {
            return $this->failure($exception->getMessage());
        }
        return response()->json(['status_code' => 1, 'message' => $id ? 'Item Direct Purchase Updated Successfully' : 'Item Direct Purchase Added Successfully', 'data' => []]);
    }

    private function saveRequisitionApprovals(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'approvals' => 'required|array|min:1',
            'approvals.*.id' => 'required|integer',
            'approvals.*.approved_qty' => 'nullable|numeric',
            'approvals.*.requisition_status' => 'nullable|integer',
            'approvals.*.requisition_approved_remarks' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $user = $request->integer('user_id');
        $updated = 0;
        DB::transaction(function () use ($request, $tenant, $syear, $user, &$updated) {
            foreach ($request->input('approvals') as $approval) {
                $query = DB::table('inventory_requisition_details')
                    ->where('id', (int) $approval['id'])->where('sub_institute_id', $tenant)->where('syear', $syear);
                if (! $query->exists()) continue;
                $query->update([
                        'requisition_approved_remarks' => $approval['requisition_approved_remarks'] ?? null,
                        'approved_qty' => $approval['approved_qty'] ?? null,
                        'requisition_status' => $approval['requisition_status'] ?? null,
                        'requisition_approved_by' => $user,
                        'requisition_approved_date' => now()->toDateString(),
                    ]);
                $updated++;
            }
        });
        if ($updated === 0) return $this->failure('No matching requisitions were updated.', 404);
        return response()->json(['status_code' => 1, 'message' => 'Requisitions Approved successfully', 'data' => []]);
    }

    private function saveRequisitions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'requisition_by' => 'required|integer',
            'requisition_date' => 'required|date',
            'requisition_no' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.item_qty' => 'required|integer|min:1',
            'items.*.item_unit' => 'required|string|max:50',
            'items.*.expected_delivery_time' => 'nullable|date',
            'items.*.remarks' => 'required|string|max:50',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $actor = $request->attributes->get('inventory_actor');
        if (! in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true) && $request->integer('requisition_by') !== (int) $actor->id) {
            return $this->failure('You can only create a requisition for your own user account.', 403);
        }
        if (! DB::table('inventory_master_setup')->where('sub_institute_id', $tenant)->where('syear', $syear)->exists()) {
            return $this->failure('Please add Master setup for add requisition.');
        }
        $expectedNumber = $this->nextRequisitionNumber($tenant, $syear);
        if (trim((string) $request->input('requisition_no')) !== $expectedNumber) {
            return $this->failure('The requisition number is no longer current. Refresh and try again.', 409);
        }
        $allowedItems = DB::table('inventory_item_master')->where('sub_institute_id', $tenant)->where('item_status', 'Active')->pluck('id')->map(fn ($value) => (int) $value)->all();
        $rows = [];
        foreach ($request->input('items') as $item) {
            if (! in_array((int) $item['item_id'], $allowedItems, true)) return $this->failure('One of the selected items is not active for this institute.');
            $rows[] = [
                'syear' => $syear, 'sub_institute_id' => $tenant, 'requisition_no' => $expectedNumber,
                'requisition_by' => $request->integer('requisition_by'), 'requisition_date' => $request->input('requisition_date'),
                'item_id' => (int) $item['item_id'], 'item_qty' => (int) $item['item_qty'], 'item_unit' => $item['item_unit'],
                'expected_delivery_time' => $item['expected_delivery_time'] ?: null, 'requisition_status' => 1,
                'remarks' => $item['remarks'], 'created_by' => $request->integer('user_id'),
                'created_ip_address' => $request->ip(), 'created_at' => now(),
            ];
        }
        DB::table('inventory_requisition_details')->insert($rows);
        return response()->json(['status_code' => 1, 'message' => 'Requisition Added Successfully', 'data' => []]);
    }

    private function updateRequisition(Request $request, int $id)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer', 'item_qty' => 'required|integer|min:1',
            'item_unit' => 'required|string|max:50', 'expected_delivery_time' => 'nullable|date',
            'remarks' => 'required|string|max:50',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $actor = $request->attributes->get('inventory_actor');
        $query = DB::table('inventory_requisition_details')->where('id', $id)->where('sub_institute_id', $tenant)->where('syear', $syear);
        if (! in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) $query->where('requisition_by', $actor->id);
        if (! $query->exists()) return $this->failure('Requisition not found.', 404);
        $query->update([
            'item_id' => $request->integer('item_id'), 'item_qty' => $request->integer('item_qty'),
            'item_unit' => $request->input('item_unit'), 'expected_delivery_time' => $request->input('expected_delivery_time') ?: null,
            'requisition_status' => 1, 'remarks' => $request->input('remarks'), 'updated_at' => now(),
        ]);
        return response()->json(['status_code' => 1, 'message' => 'Requisition Details Updated Successfully', 'data' => []]);
    }

    private function workflowRules(string $module): array
    {
        return [
            'requisitions' => ['requisition_by' => 'required|integer', 'requisition_date' => 'required|date', 'item_id' => 'required|integer', 'requisition_qty' => 'required|numeric|min:0.01', 'item_unit' => 'required|string|max:50', 'expected_delivery_time' => 'nullable|date'],
            'requisition-approvals' => ['requisition_id' => 'required_without:id|integer', 'requisition_status' => 'required|integer', 'approved_qty' => 'required|numeric|min:0'],
            'quotations' => ['vendor_id' => 'required|integer', 'item_id' => 'required|integer', 'qty' => 'required|numeric|min:0.01', 'rate' => 'required|numeric|min:0', 'unit' => 'required|string|max:50'],
            'purchase-orders' => ['vendor_id' => 'required|integer', 'item_id' => 'required|integer', 'price' => 'required|numeric|min:0', 'qty' => 'required|numeric|min:0.01'],
            'purchase-order-negotiations' => ['po_number' => 'required|string', 'item_id' => 'required|integer', 'price' => 'required|numeric|min:0', 'qty' => 'required|numeric|min:0.01', 'dis_per' => 'required|numeric|min:0', 'tax_per' => 'required|numeric|min:0', 'po_approval_status' => 'required|integer'],
            'receivables' => ['po_number' => 'required|string', 'item_id' => 'required|integer', 'actual_received_qty' => 'required|numeric|min:0.01'],
            'allocations' => ['item_id' => 'required|integer', 'requisition_by' => 'required|integer', 'location_of_material' => 'required|string', 'person_responsible' => 'required|string'],
            'returns' => ['requisition_by' => 'required|integer', 'item_id' => 'required|integer', 'return_qty' => 'required|numeric|min:0.01'],
            'defectives' => ['item_id' => 'required|integer', 'defect_remarks' => 'required|string', 'warranty_start_date' => 'nullable|date', 'warranty_end_date' => 'nullable|date', 'estimated_received_date' => 'nullable|date', 'actual_received_date' => 'nullable|date'],
            'direct-purchases' => ['vendor_id' => 'required|integer', 'item_id' => 'required|integer', 'qty' => 'required|numeric|min:0.01', 'rate' => 'required|numeric|min:0'],
        ][$module] ?? [];
    }

    private function workflowValues(Request $request, string $module, int $tenant, int $syear, int $user, ?int $id = null): array
    {
        $common = ['sub_institute_id' => $tenant, 'syear' => $syear];
        switch ($module) {
            case 'requisitions':
                $number = DB::table('inventory_requisition_details')->where('sub_institute_id', $tenant)->where('syear', $syear)->max('requisition_no');
                return ['inventory_requisition_details', array_merge($common, ['requisition_no' => ((int) $number) + 1, 'requisition_by' => $request->integer('requisition_by'), 'requisition_date' => $request->input('requisition_date'), 'item_id' => $request->integer('item_id'), 'item_qty' => $request->input('requisition_qty'), 'item_unit' => $request->input('item_unit'), 'expected_delivery_time' => $request->input('expected_delivery_time'), 'requisition_status' => 1, 'remarks' => $request->input('remarks'), 'department_id' => $request->input('department_id'), 'user_group_id' => $request->input('user_group_id'), 'created_by' => $user, 'created_ip_address' => $request->ip()])];
            case 'quotations':
                return ['inventory_item_quotation_details', array_merge($common, ['item_id' => $request->integer('item_id'), 'vendor_id' => $request->integer('vendor_id'), 'transportation_charge' => $request->input('transportation_charge'), 'installation_charge' => $request->input('installation_charge'), 'qty' => $request->input('qty'), 'price' => $request->input('rate'), 'total' => $request->input('qty') * $request->input('rate'), 'unit' => $request->input('unit'), 'tax' => $request->input('tax_id'), 'remarks' => $request->input('remarks'), 'approved_status' => 2, 'approved_by' => $user, 'approved_date' => now()->toDateString(), 'created_by' => $user, 'created_on' => now(), 'created_ip_address' => $request->ip()])];
            case 'purchase-orders':
                $amount = (float) $request->input('price') * (float) $request->input('qty');
                $discount = $amount * ((float) $request->input('dis_per', 0) / 100);
                $afterDiscount = $amount - $discount;
                $tax = $afterDiscount * ((float) $request->input('tax_per', 0) / 100);
                return ['inventory_generate_po_details', array_merge($common, ['po_number' => 'PO-'.$syear.'-'.str_pad((string) (DB::table('inventory_generate_po_details')->where('sub_institute_id', $tenant)->where('syear', $syear)->count() + 1), 5, '0', STR_PAD_LEFT), 'item_id' => $request->integer('item_id'), 'vendor_id' => $request->integer('vendor_id'), 'price' => $request->input('price'), 'qty' => $request->input('qty'), 'amount' => $amount, 'dis_per' => $request->input('dis_per', 0), 'dis_amount_value' => $discount, 'after_dis_amount' => $afterDiscount, 'tax_per' => $request->input('tax_per', 0), 'tax_amount_value' => $tax, 'after_tax_amount' => $afterDiscount + $tax, 'amount_per_item' => $amount, 'transportation_charge' => $request->input('transportation_charge'), 'installation_charge' => $request->input('installation_charge'), 'delivery_time' => $request->input('delivery_time'), 'po_place_of_delivery' => $request->input('po_place_of_delivery'), 'payment_terms' => $request->input('payment_terms'), 'remarks' => $request->input('remarks'), 'created_by' => $user, 'created_on' => now(), 'created_ip_address' => $request->ip()])];
            case 'purchase-order-negotiations':
                $po = DB::table('inventory_generate_po_details')->where('po_number', $request->input('po_number'))->where('item_id', $request->integer('item_id'))->where('sub_institute_id', $tenant)->where('syear', $syear)->lockForUpdate()->first();
                if (! $po) throw new \RuntimeException('Purchase-order item not found.');
                $amount = (float) $request->input('price') * (float) $request->input('qty');
                $discount = $amount * ((float) $request->input('dis_per') / 100);
                $afterDiscount = $amount - $discount;
                $tax = $afterDiscount * ((float) $request->input('tax_per') / 100);
                $approval = ['po_approval_status' => $request->integer('po_approval_status'), 'po_approval_remark' => $request->input('po_approval_remark'), 'po_approved_by' => $user, 'po_approved_date' => now()];
                DB::table('inventory_generate_po_details')->where('po_number', $request->input('po_number'))->where('sub_institute_id', $tenant)->where('syear', $syear)->update($approval);
                DB::table('inventory_negotiate_po_details')->updateOrInsert(
                    ['po_number' => $request->input('po_number'), 'item_id' => $request->integer('item_id'), 'sub_institute_id' => $tenant, 'syear' => $syear],
                    array_merge($approval, ['vendor_id' => $po->vendor_id, 'price' => $request->input('price'), 'qty' => $request->input('qty'), 'amount' => $amount, 'dis_per' => $request->input('dis_per'), 'dis_amount_value' => $discount, 'after_dis_amount' => $afterDiscount, 'tax_per' => $request->input('tax_per'), 'tax_amount_value' => $tax, 'after_tax_amount' => $afterDiscount + $tax, 'amount_per_item' => $amount, 'created_by' => $user, 'created_on' => now(), 'created_ip_address' => $request->ip()])
                );
                return ['inventory_generate_po_details', []];
            case 'receivables':
                $po = DB::table('inventory_negotiate_po_details')->where('po_number', $request->input('po_number'))->where('item_id', $request->integer('item_id'))->where('sub_institute_id', $tenant)->where('syear', $syear)->first();
                if (! $po) {
                    $po = DB::table('inventory_generate_po_details')->where('po_number', $request->input('po_number'))->where('item_id', $request->integer('item_id'))->where('sub_institute_id', $tenant)->where('syear', $syear)->first();
                }
                if (! $po) throw new \RuntimeException('Approved purchase-order item not found.');
                $previousQuery = DB::table('inventory_item_receivable_details')->where('PURCHASE_ORDER_NO', $request->input('po_number'))->where('ITEM_ID', $request->integer('item_id'))->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear);
                if ($id) $previousQuery->where('ID', '!=', $id);
                $previous = (float) $previousQuery->sum('ACTUAL_RECEIVED_QTY');
                $received = (float) $request->input('actual_received_qty');
                if ($previous + $received > (float) $po->qty) throw new \RuntimeException('Received quantity cannot exceed the pending PO quantity.');
                $values = ['PURCHASE_ORDER_NO' => $request->input('po_number'), 'ITEM_ID' => $request->integer('item_id'), 'ORDER_QTY' => $po->qty, 'PREVIOUS_RECEIVED_QTY' => $previous, 'ACTUAL_RECEIVED_QTY' => $received, 'PENDING_QTY' => (float) $po->qty - $previous - $received, 'RECEIVED_BY' => $user, 'RECEIVED_DATE' => now()];
                foreach (['remarks' => 'REMARKS', 'warranty_start_date' => 'WARRANTY_START_DATE', 'warranty_end_date' => 'WARRANTY_END_DATE', 'bill_no' => 'BILL_NO', 'bill_date' => 'BILL_DATE', 'challan_no' => 'CHALLAN_NO', 'challan_date' => 'CHALLAN_DATE'] as $requestKey => $dbKey) {
                    if ($request->has($requestKey)) $values[$dbKey] = $request->input($requestKey);
                }
                if (! $id) $values = array_merge($values, ['CREATED_BY' => $user, 'CREATED_ON' => now(), 'CREATED_IP_ADDRESS' => $request->ip()]);
                return ['inventory_item_receivable_details', array_merge($common, $values)];
            case 'allocations':
                $itemId = $request->integer('item_id');
                $requisitionById = $request->integer('requisition_by');
                $requisitionDetailsId = null;
                if ($id) {
                    $existing = DB::table('inventory_allocation_details')->where('ID', $id)->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear)->first();
                    if ($existing) {
                        $requisitionDetailsId = $existing->REQUISITION_DETAILS_ID;
                    }
                }
                $needsNewRequisition = !$requisitionDetailsId;
                if (!$needsNewRequisition && $requisitionDetailsId) {
                    $currentRequisition = DB::table('inventory_requisition_details')->where('id', $requisitionDetailsId)->where('sub_institute_id', $tenant)->where('syear', $syear)->first();
                    if ($currentRequisition && ($currentRequisition->item_id != $itemId || $currentRequisition->requisition_by != $requisitionById)) {
                        $needsNewRequisition = true;
                    }
                }
                if ($needsNewRequisition) {
                    $requisition = DB::table('inventory_requisition_details as requisition')->join('inventory_requisition_status_master as status', 'status.id', '=', 'requisition.requisition_status')->select('requisition.*')->where('requisition.item_id', $itemId)->where('requisition.requisition_by', $requisitionById)->where('requisition.sub_institute_id', $tenant)->where('requisition.syear', $syear)->where('status.title', 'APPROVED')->orderByDesc('requisition.id')->first();
                    if (! $requisition) throw new \RuntimeException('Approved requisition not found.');
                    $requisitionDetailsId = $requisition->id;
                }
                $values = ['REQUISITION_DETAILS_ID' => $requisitionDetailsId, 'REQUISITION_ID' => $requisitionById, 'LOCATION_OF_MATERIAL' => $request->input('location_of_material'), 'PERSON_RESPONSIBLE' => $request->input('person_responsible'), 'ITEM_ID' => $itemId];
                if (! $id) $values = array_merge($values, ['CREATED_BY' => $user, 'CREATED_ON' => now(), 'CREATED_IP_ADDRESS' => $request->ip()]);
                return ['inventory_allocation_details', array_merge($common, $values)];
            case 'returns':
                $itemId = $request->integer('item_id');
                $requisitionById = $request->integer('requisition_by');
                $allocation = null;
                $existingReturnId = null;
                if ($id) {
                    $existingReturn = DB::table('inventory_item_return_details')->where('ID', $id)->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear)->first();
                    if ($existingReturn) {
                        $existingReturnId = $existingReturn->ID;
                        $allocation = DB::table('inventory_allocation_details as allocation')->join('inventory_requisition_details as requisition', 'requisition.id', '=', 'allocation.requisition_details_id')->select('allocation.REQUISITION_ID', 'requisition.id as requisition_details_id', 'requisition.approved_qty')->where('allocation.ITEM_ID', $itemId)->where('requisition.requisition_by', $requisitionById)->where('allocation.SUB_INSTITUTE_ID', $tenant)->where('allocation.SYEAR', $syear)->orderByDesc('allocation.ID')->first();
                    }
                }
                if (!$allocation) {
                    $allocation = DB::table('inventory_allocation_details as allocation')->join('inventory_requisition_details as requisition', 'requisition.id', '=', 'allocation.requisition_details_id')->select('allocation.REQUISITION_ID', 'requisition.id as requisition_details_id', 'requisition.approved_qty')->where('allocation.ITEM_ID', $itemId)->where('requisition.requisition_by', $requisitionById)->where('allocation.SUB_INSTITUTE_ID', $tenant)->where('allocation.SYEAR', $syear)->orderByDesc('allocation.ID')->first();
                }
                if (! $allocation) throw new \RuntimeException('Allocated item was not found for this user.');
                $returnedQuery = DB::table('inventory_item_return_details')->where('REQUISITION_ID', $allocation->REQUISITION_ID)->where('ITEM_ID', $itemId)->where('REQUISITION_BY', $requisitionById)->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear);
                if ($existingReturnId) $returnedQuery->where('ID', '!=', $existingReturnId);
                $alreadyReturned = (float) $returnedQuery->sum('RETURN_QTY');
                if ($alreadyReturned + (float) $request->input('return_qty') > (float) $allocation->approved_qty) throw new \RuntimeException('Return quantity cannot exceed the allocated balance.');
                $values = ['REQUISITION_ID' => $allocation->REQUISITION_ID, 'ITEM_ID' => $itemId, 'RETURN_DATE' => now()->toDateString(), 'REMARKS' => $request->input('remarks'), 'RETURN_QTY' => $request->input('return_qty'), 'REQUISITION_BY' => $requisitionById];
                if (! $id) $values = array_merge($values, ['CREATED_BY' => $user, 'CREATED_ON' => now(), 'CREATED_IP_ADDRESS' => $request->ip()]);
                return ['inventory_item_return_details', array_merge($common, $values)];
            case 'defectives':
                $values = ['ITEM_ID' => $request->integer('item_id')];
                foreach (['warranty_start_date' => 'WARRANTY_START_DATE', 'warranty_end_date' => 'WARRANTY_END_DATE', 'defect_remarks' => 'DEFECT_REMARKS', 'item_given_to' => 'ITEM_GIVEN_TO', 'estimated_received_date' => 'ESTIMATED_RECEIVED_DATE', 'actual_received_date' => 'ACTUAL_RECEIVED_DATE', 'remarks' => 'REMARKS'] as $requestKey => $dbKey) {
                    if ($request->has($requestKey)) $values[$dbKey] = $request->input($requestKey);
                }
                if (! $id) $values = array_merge($values, ['CREATED_BY' => $user, 'CREATED_ON' => now(), 'CREATED_IP_ADDRESS' => $request->ip()]);
                return ['inventory_item_defective_details', array_merge($common, $values)];
            case 'direct-purchases':
                $item = DB::table('inventory_item_master')->where('id', $request->integer('item_id'))->where('sub_institute_id', $tenant)->first();
                if (! $item) throw new \RuntimeException('Inventory item not found.');
                return ['inventory_item_direct_purchase', array_merge($common, ['vendor_id' => $request->integer('vendor_id'), 'category_id' => $item->category_id, 'sub_category_id' => $item->sub_category_id, 'item_id' => $request->integer('item_id'), 'item_qty' => $request->input('qty'), 'price' => $request->input('rate'), 'amount' => $request->input('qty') * $request->input('rate'), 'challan_no' => $request->input('challan_no'), 'challan_date' => $request->input('challan_date'), 'bill_no' => $request->input('bill_no'), 'bill_date' => $request->input('bill_date'), 'remarks' => $request->input('remarks'), 'created_by' => $user, 'created_on' => now(), 'created_ip' => $request->ip()])];
        }
        throw new \RuntimeException('Unknown Inventory workflow.');
    }

    private function saveReceivables(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'po_number' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.actual_received_qty' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $user = $request->integer('user_id');
        $poNumber = $request->input('po_number');
        $po = DB::table('inventory_negotiate_po_details')->where('po_number', $poNumber)->where('sub_institute_id', $tenant)->where('syear', $syear)->first();
        if (!$po) $po = DB::table('inventory_generate_po_details')->where('po_number', $poNumber)->where('sub_institute_id', $tenant)->where('syear', $syear)->first();
        if (!$po) throw new \RuntimeException('Approved purchase-order not found.');
        try {
            DB::transaction(function () use ($request, $tenant, $syear, $user, $poNumber) {
                $items = $request->input('items', []);
                foreach ($items as $k => $item) {
                    $itemId = (int) $item['item_id'];
                    $received = (float) $item['actual_received_qty'];
                    $poItem = DB::table('inventory_negotiate_po_details')->where('po_number', $poNumber)->where('item_id', $itemId)->where('sub_institute_id', $tenant)->where('syear', $syear)->first();
                    if (!$poItem) throw new \RuntimeException("PO item not found for item_id=$itemId.");
                    $qty = (float) $poItem->qty;
                    $previous = (float) DB::table('inventory_item_receivable_details')->where('PURCHASE_ORDER_NO', $poNumber)->where('ITEM_ID', $itemId)->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear)->sum('ACTUAL_RECEIVED_QTY');
                    if ($previous + $received > $qty) throw new \RuntimeException("Received quantity cannot exceed PO quantity for item_id=$itemId.");
                    $values = [
                        'PURCHASE_ORDER_NO' => $poNumber,
                        'ITEM_ID' => $itemId,
                        'ORDER_QTY' => $qty,
                        'PREVIOUS_RECEIVED_QTY' => $previous,
                        'ACTUAL_RECEIVED_QTY' => $received,
                        'PENDING_QTY' => $qty - $previous - $received,
                        'RECEIVED_BY' => $user,
                        'RECEIVED_DATE' => now(),
                    ];
                    foreach (['remarks', 'warranty_start_date', 'warranty_end_date', 'bill_no', 'bill_date', 'challan_no', 'challan_date'] as $key) {
                        if ($request->has("items.$k.$key")) $values[strtoupper($key)] = $item[$key] ?? null;
                    }
                    $existing = DB::table('inventory_item_receivable_details')->where('PURCHASE_ORDER_NO', $poNumber)->where('ITEM_ID', $itemId)->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear)->first();
                    if ($existing) {
                        DB::table('inventory_item_receivable_details')->where('ID', $existing->ID)->where('SUB_INSTITUTE_ID', $tenant)->where('SYEAR', $syear)->update($values);
                    } else {
                        $values = array_merge($values, ['SUB_INSTITUTE_ID' => $tenant, 'SYEAR' => $syear, 'CREATED_BY' => $user, 'CREATED_ON' => now(), 'CREATED_IP_ADDRESS' => $request->ip()]);
                        DB::table('inventory_item_receivable_details')->insert($values);
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }
        return response()->json(['status_code' => 1, 'message' => 'Item Received successfully.', 'data' => []]);
    }

    private function saveMaster(Request $request, string $module, ?int $id)
    {
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $rules = [
            'master-setups' => ['gst_registration_no' => 'required|string|max:255', 'gst_registration_date' => 'required|date', 'cst_registration_no' => 'required|string|max:255', 'cst_registration_date' => 'required|date', 'po_no_prefix' => 'required|string|max:255', 'item_setting_for_requisition' => 'required|string|max:100'],
            'item-categories' => ['title' => 'required|string|max:255', 'description' => 'required|string|max:255', 'status' => ['required', Rule::in(['Yes', 'No'])]],
            'item-sub-categories' => ['category_id' => ['required', 'integer', Rule::exists('inventory_item_category_master', 'id')->where(fn ($query) => $query->where('sub_institute_id', $tenant))], 'title' => 'required|string|max:255', 'description' => 'required|string|max:255', 'status' => ['required', Rule::in(['Yes', 'No'])]],
            'items' => ['category_id' => 'required|integer', 'sub_category_id' => 'required|integer', 'item_type_id' => 'required|integer', 'title' => 'required|string|max:255', 'description' => 'required|string|max:255', 'item_status' => 'required|string|max:255'],
            'taxes' => ['title' => 'required|string|max:255', 'amount_percentage' => 'required|numeric|min:0', 'status' => ['required', Rule::in(['Yes', 'No'])]],
            'vendors' => ['vendor_name' => 'required|string|max:255'],
        ][$module];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());

        $allowed = [
            'master-setups' => ['gst_registration_no', 'gst_registration_date', 'cst_registration_no', 'cst_registration_date', 'po_no_prefix', 'item_setting_for_requisition'],
            'item-categories' => ['title', 'description', 'status'],
            'item-sub-categories' => ['category_id', 'title', 'description', 'status'],
            'items' => ['category_id', 'sub_category_id', 'item_type_id', 'title', 'description', 'opening_stock', 'minimum_stock', 'item_status'],
            'taxes' => ['title', 'amount_percentage', 'description_1', 'status', 'sort_order'],
            'vendors' => ['vendor_name', 'contact_number', 'short_name', 'sort_order', 'address', 'email', 'file_number', 'file_location', 'company_name', 'business_type', 'office_address', 'office_contact_person', 'office_number', 'office_email', 'tin_no', 'tin_date', 'registration_no', 'registration_date', 'serivce_tax_no', 'serivce_tax_date', 'pan_no', 'bank_account_no', 'bank_name', 'bank_branch', 'bank_ifsc_code'],
        ][$module];
        $values = $request->only($allowed);
        if ($module === 'items') {
            if ($request->hasFile('item_attachment')) {
                $values['item_attachment'] = $request->file('item_attachment')->storeAs(
                    'public/inventory_item',
                    now()->format('YmdHis').'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $request->file('item_attachment')->getClientOriginalName())
                );
                $values['item_attachment'] = basename($values['item_attachment']);
            } elseif (! $id) {
                $values['item_attachment'] = '';
            }
        }
        if ($module === 'master-setups') {
            if ($request->hasFile('logo')) {
                $values['logo'] = $request->file('logo')->storeAs(
                    'public/inventory_master',
                    now()->format('YmdHis').'_'.preg_replace('/[^A-Za-z0-9._-]/', '_', $request->file('logo')->getClientOriginalName())
                );
                $values['logo'] = basename($values['logo']);
            } elseif (! $id) {
                $values['logo'] = '';
            }
        }
        $values = array_merge($values, ['sub_institute_id' => $tenant, 'syear' => $syear]);
        if (in_array($module, ['taxes', 'vendors'], true)) {
            $values['created_by'] = $request->integer('user_id');
            $values['created_on'] = now();
            $values['created_ip_address'] = $request->ip();
        }
        $table = self::MASTER_TABLES[$module];
        $instituteWide = in_array($module, ['item-categories', 'item-sub-categories', 'items', 'taxes', 'vendors'], true);
        if ($id) {
            $query = DB::table($table)->where('id', $id)->where('sub_institute_id', $tenant);
            if (! $instituteWide) $query->where('syear', $syear);
            $affected = $query->update($values);
            $exists = DB::table($table)->where('id', $id)->where('sub_institute_id', $tenant);
            if (! $instituteWide) $exists->where('syear', $syear);
            if (! $affected && ! $exists->exists()) return $this->failure('Record not found.', 404);
        } else {
            DB::table($table)->insert($values);
        }
        return response()->json(['status_code' => 1, 'message' => $id ? 'Inventory master updated successfully.' : 'Inventory master created successfully.', 'data' => []]);
    }

    public function destroy(Request $request, string $module, int $id)
    {
        if ($response = $this->guard($request, $module, 'delete')) return $response;
        if (! isset(self::MASTER_TABLES[$module]) && ! in_array($module, ['requisitions', 'quotations', 'purchase-orders', 'defectives', 'direct-purchases'], true)) return $this->failure('This Inventory module does not support deletion.', 405);
        $table = self::MASTER_TABLES[$module] ?? ['requisitions' => 'inventory_requisition_details', 'quotations' => 'inventory_item_quotation_details', 'purchase-orders' => 'inventory_generate_po_details', 'defectives' => 'inventory_item_defective_details', 'direct-purchases' => 'inventory_item_direct_purchase'][$module];
        $query = DB::table($table)->where('id', $id)->where('sub_institute_id', $request->integer('sub_institute_id'));
        if ($module === 'requisitions') {
            $actor = $request->attributes->get('inventory_actor');
            if (! in_array(strtolower((string) $actor->profile_name), ['admin', 'super admin'], true)) $query->where('requisition_by', $actor->id);
        }
        if (! in_array($module, ['item-categories', 'item-sub-categories', 'items', 'taxes', 'vendors'], true)) $query->where('syear', $request->integer('syear'));
        if (! $query->delete()) return $this->failure('Record not found.', 404);
        return response()->json(['status_code' => 1, 'message' => 'Inventory record deleted successfully.', 'data' => []]);
    }

    public function poItems(Request $request)
    {
        if ($response = $this->guard($request, 'receivables', 'view')) return $response;
        $poNumber = $request->query('po_number');
        if (! $poNumber) return $this->failure('PO number is required.', 422);
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $items = DB::table('inventory_generate_po_details as gp')
            ->join('inventory_item_master as i', 'i.id', '=', 'gp.item_id')
            ->leftJoin('inventory_item_receivable_details as ir', function ($join) {
                $join->on('ir.ITEM_ID', '=', 'gp.item_id')
                     ->on('ir.PURCHASE_ORDER_NO', '=', 'gp.po_number');
            })
            ->leftJoin('tbluser as tu', function ($join) {
                $join->on('tu.id', '=', 'ir.RECEIVED_BY')
                     ->where('tu.status', '=', 1);
            })
            ->selectRaw("
                gp.po_number,
                gp.item_id,
                i.title as item_name,
                gp.qty,
                IFNULL(ir.PREVIOUS_RECEIVED_QTY, 0) as previous_receive_qty,
                IFNULL(ir.ACTUAL_RECEIVED_QTY, 0) as actual_received_qty,
                (gp.qty - IFNULL(ir.PREVIOUS_RECEIVED_QTY,0)) as pending_qty,
                ir.REMARKS as remarks,
                ir.WARRANTY_START_DATE as warranty_start_date,
                ir.WARRANTY_END_DATE as warranty_end_date,
                ir.BILL_NO as bill_no,
                ir.BILL_DATE as bill_date,
                ir.CHALLAN_NO as challan_no,
                ir.CHALLAN_DATE as challan_date,
                CONCAT_WS(' ', tu.first_name, tu.middle_name, tu.last_name) as received_by
            ")
            ->where('gp.po_number', $poNumber)
            ->where('gp.sub_institute_id', $tenant)
            ->where('gp.syear', $syear)
            ->get();
        return response()->json(['status_code' => 1, 'message' => 'Success', 'data' => $items]);
    }

    public function saveReceivables(Request $request)
    {
        if ($response = $this->guard($request, 'receivables', 'add')) return $response;
        $validator = Validator::make($request->all(), [
            'po_number' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.actual_received_qty' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) return $this->failure($validator->messages()->first(), 422, $validator->errors());
        $tenant = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $user = $request->integer('user_id');
        $poNumber = $request->input('po_number');
        try {
            DB::transaction(function () use ($request, $tenant, $syear, $user, $poNumber) {
                foreach ($request->input('items') as $item) {
                    $po = DB::table('inventory_generate_po_details')
                        ->where('po_number', $poNumber)
                        ->where('item_id', (int) $item['item_id'])
                        ->where('sub_institute_id', $tenant)
                        ->where('syear', $syear)
                        ->lockForUpdate()
                        ->first();
                    if (! $po) throw new \RuntimeException('Purchase-order item not found.');
                    $previous = (float) DB::table('inventory_item_receivable_details')
                        ->where('PURCHASE_ORDER_NO', $poNumber)
                        ->where('ITEM_ID', (int) $item['item_id'])
                        ->where('SUB_INSTITUTE_ID', $tenant)
                        ->where('SYEAR', $syear)
                        ->sum('ACTUAL_RECEIVED_QTY');
                    $received = (float) ($item['actual_received_qty'] ?? 0);
                    if ($previous + $received > (float) $po->qty) throw new \RuntimeException('Received quantity cannot exceed the pending PO quantity.');
                    $values = [
                        'PURCHASE_ORDER_NO' => $poNumber,
                        'ITEM_ID' => (int) $item['item_id'],
                        'ORDER_QTY' => $po->qty,
                        'PREVIOUS_RECEIVED_QTY' => $previous,
                        'ACTUAL_RECEIVED_QTY' => $received,
                        'PENDING_QTY' => (float) $po->qty - $previous - $received,
                        'RECEIVED_BY' => $user,
                        'RECEIVED_DATE' => now(),
                        'CREATED_BY' => $user,
                        'CREATED_ON' => now(),
                        'CREATED_IP_ADDRESS' => $request->ip(),
                    ];
                    foreach (['remarks' => 'REMARKS', 'warranty_start_date' => 'WARRANTY_START_DATE', 'warranty_end_date' => 'WARRANTY_END_DATE', 'bill_no' => 'BILL_NO', 'bill_date' => 'BILL_DATE', 'challan_no' => 'CHALLAN_NO', 'challan_date' => 'CHALLAN_DATE'] as $requestKey => $dbKey) {
                        if (isset($item[$requestKey])) $values[$dbKey] = $item[$requestKey];
                    }
                    DB::table('inventory_item_receivable_details')->updateOrInsert(
                        ['PURCHASE_ORDER_NO' => $poNumber, 'ITEM_ID' => (int) $item['item_id'], 'SUB_INSTITUTE_ID' => $tenant, 'SYEAR' => $syear],
                        $values
                    );
                }
            });
        } catch (\RuntimeException $exception) {
            return $this->failure($exception->getMessage());
        }
        return response()->json(['status_code' => 1, 'message' => 'Item Received successfully.', 'data' => []]);
    }
}
