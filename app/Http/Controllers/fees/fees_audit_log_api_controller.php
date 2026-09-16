<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only listing API over the existing system_audit_logs table, scoped
 * to module=fees by default. SELECT-only — never writes. Sprint 2
 * "Audit Log Listing API".
 */
class fees_audit_log_api_controller extends Controller
{
    public function index(Request $request)
    {
        try {
            // system_audit_logs is expected (2026_08_24_090000_create_...
            // migration) but is not guaranteed to be migrated on every
            // environment this controller runs against. Degrade to an
            // explicit, empty JSON response rather than an uncaught
            // QueryException / HTML error page.
            if (! Schema::hasTable('system_audit_logs')) {
                return response()->json([
                    'status' => 1,
                    'data' => [],
                    'meta' => ['current_page' => 1, 'per_page' => 0, 'total' => 0, 'last_page' => 1],
                    'message' => 'system_audit_logs table is not present in this environment yet.',
                ]);
            }

            $query = DB::table('system_audit_logs')
                ->where('module', $request->input('module', 'fees'));

            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }
            if ($request->filled('entity_type')) {
                $query->where('entity_type', $request->input('entity_type'));
            }
            if ($request->filled('entity_id')) {
                $query->where('entity_id', $request->input('entity_id'));
            }
            if ($request->filled('sub_institute_id')) {
                $query->where('sub_institute_id', $request->input('sub_institute_id'));
            }
            if ($request->filled('from_date')) {
                $query->whereDate('created_at', '>=', $request->input('from_date'));
            }
            if ($request->filled('to_date')) {
                $query->whereDate('created_at', '<=', $request->input('to_date'));
            }

            $perPage = (int) $request->input('per_page', 25);
            $perPage = ($perPage > 0 && $perPage <= 200) ? $perPage : 25;

            $paginator = $query->orderByDesc('id')->paginate($perPage);

            return response()->json([
                'status' => 1,
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 0,
                'message' => 'Unable to load audit logs.',
                'data' => [],
            ], 500);
        }
    }
}
