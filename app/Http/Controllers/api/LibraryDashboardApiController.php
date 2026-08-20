<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stateless aggregate for the Next.js Library dashboard.
 *
 * Modeled on FeesDashboardApiController: accepts tenant/year in the request
 * body (no browser session required).
 *
 * Column usage (library_books.sub_institute_id, library_items.deleted_at,
 * library_book_circulations.sub_institute_id/syear, item_code as a FK to
 * library_items.id) is confirmed from the live
 * App\Http\Controllers\library\LibraryReportController, not from migrations —
 * several of these columns were added outside the tracked migration set.
 *
 * "Not yet returned" is `return_date IS NULL OR return_date LIKE '0000-00%'`,
 * matching LibraryReportController::bookIssueDueReport exactly — this legacy
 * table stores an unreturned copy as either a NULL or a zero-date string.
 */
class LibraryDashboardApiController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sub_institute_id' => ['required'],
            'syear' => ['required'],
        ]);

        $subInstituteId = (string) $validated['sub_institute_id'];
        $syear = (string) $validated['syear'];
        $today = now()->toDateString();

        $totalTitles = (int) DB::table('library_books')
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        $totalItems = (int) DB::table('library_items')
            ->where('sub_institute_id', $subInstituteId)
            ->whereNull('deleted_at')
            ->count();

        // Columns qualified with the table name: recent_issues below joins in
        // tblstudent and library_books, which also carry a sub_institute_id
        // column, so an unqualified where() becomes ambiguous once that clone
        // picks up the joins.
        $circulation = DB::table('library_book_circulations')
            ->where('library_book_circulations.sub_institute_id', $subInstituteId)
            ->where('library_book_circulations.syear', $syear);

        $currentlyIssued = (int) (clone $circulation)
            ->whereRaw("(return_date IS NULL OR return_date LIKE '0000-00%')")
            ->count();

        $overdue = (int) (clone $circulation)
            ->whereRaw("(return_date IS NULL OR return_date LIKE '0000-00%')")
            ->whereDate('due_date', '<', $today)
            ->count();

        $byMaterialType = DB::table('library_items as li')
            ->join('library_books as lb', 'lb.id', '=', 'li.book_id')
            ->where('li.sub_institute_id', $subInstituteId)
            ->where('lb.sub_institute_id', $subInstituteId)
            ->whereNull('li.deleted_at')
            ->selectRaw("COALESCE(NULLIF(TRIM(lb.material_resource_type), ''), 'Unspecified') as material_type, COUNT(*) as total")
            ->groupBy('lb.material_resource_type')
            ->get();

        $recentIssues = (clone $circulation)
            ->join('tblstudent as s', 's.id', '=', 'library_book_circulations.student_id')
            ->join('library_books as lb', 'lb.id', '=', 'library_book_circulations.book_id')
            ->selectRaw("library_book_circulations.id, CONCAT_WS(' ', s.first_name, s.last_name) as student_name, lb.title as book_title, library_book_circulations.issued_date, library_book_circulations.due_date, library_book_circulations.return_date")
            ->orderByDesc('library_book_circulations.issued_date')
            ->limit(8)
            ->get();

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'context' => [
                'sub_institute_id' => (int) $subInstituteId,
                'syear' => (int) $syear,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => [
                'total_titles' => $totalTitles,
                'total_items' => $totalItems,
                'currently_issued' => $currentlyIssued,
                'overdue' => $overdue,
            ],
            'items_by_material_type' => $byMaterialType,
            'recent_issues' => $recentIssues,
        ]);
    }
}
