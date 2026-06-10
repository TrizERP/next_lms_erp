<?php

namespace App\Http\Controllers\MIS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MisSummaryController extends Controller
{
    public function index(Request $request)
    {
        // For role-based (demo from session or request)
        $userRole = session('user_type') ?? $request->get('role', 'Principal'); // Principal, Trustee, Management
        $schoolName = session('school_name') ?? 'Delhi Public School, Gandhinagar';
        $branch = session('branch_name') ?? 'Main Campus';

        $reportDate = Carbon::now()->format('d M Y');
        $reportTime = Carbon::now()->format('h:i A');

        // Sample realistic data for all 17 modules - in production this would query multiple tables
        $modules = [
            'late_comers' => [
                'key' => 'late_comers',
                'title' => 'Late Comers',
                'icon' => 'clock',
                'status' => 'yellow', // green/yellow/red/gray
                'status_label' => 'Attention Required',
                'count' => 2,
                'summary' => '2 staff late',
                'details' => [
                    ['name' => 'Shilpa Ma’am', 'time' => '7:52 AM', 'dept' => 'Science'],
                    ['name' => 'Melvin Sir', 'time' => '7:52 AM', 'dept' => 'Maths']
                ],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'proxy_report' => [
                'key' => 'proxy_report',
                'title' => 'Proxy Report',
                'icon' => 'user-replace',
                'status' => 'green',
                'status_label' => 'Generated',
                'count' => 3,
                'summary' => 'Proxy generated for 3 periods',
                'details' => [],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'parent_communication' => [
                'key' => 'parent_communication',
                'title' => "Today's Parent Communication",
                'icon' => 'chat',
                'status' => 'green',
                'status_label' => 'Yes',
                'count' => 12,
                'summary' => '12 messages sent',
                'details' => [
                    ['parent' => 'Rahul Sharma (Class 5)', 'reply' => 'Yes', 'time' => '10:15 AM'],
                    ['parent' => 'Priya Patel (Class 8)', 'reply' => 'Pending', 'time' => '11:30 AM'],
                ],
                'pending_count' => 3,
                'has_drill' => true,
            ],
            'student_leave' => [
                'key' => 'student_leave',
                'title' => "Today's Student Leave",
                'icon' => 'calendar-minus',
                'status' => 'yellow',
                'status_label' => 'Pending Reply',
                'count' => 5,
                'summary' => '5 leave requests',
                'details' => [
                    ['student' => 'Aarav Mehta (7-B)', 'reason' => 'Fever', 'reply' => 'Pending'],
                    ['student' => 'Sneha Roy (4-A)', 'reason' => 'Family function', 'reply' => 'Approved'],
                ],
                'pending_count' => 2,
                'has_drill' => true,
            ],
            'approve_staff_leave' => [
                'key' => 'approve_staff_leave',
                'title' => 'Approve Staff Leave',
                'icon' => 'user-check',
                'status' => 'red',
                'status_label' => 'Critical',
                'count' => 4,
                'summary' => '4 pending approvals',
                'details' => [
                    ['staff' => 'Anita Desai', 'type' => 'Sick Leave', 'days' => '2'],
                    ['staff' => 'Rohit Verma', 'type' => 'Casual', 'days' => '1'],
                ],
                'pending_count' => 4,
                'has_drill' => true,
            ],
            'inventory' => [
                'key' => 'inventory',
                'title' => 'Inventory',
                'icon' => 'package',
                'status' => 'gray',
                'status_label' => 'Nil',
                'count' => 0,
                'summary' => 'No pending issues',
                'details' => [],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'inward' => [
                'key' => 'inward',
                'title' => 'Inward',
                'icon' => 'inbox-in',
                'status' => 'green',
                'status_label' => '3 Entries',
                'count' => 3,
                'summary' => '3 inward entries today',
                'details' => [
                    ['item' => 'Science Lab Chemicals', 'from' => 'ABC Suppliers', 'qty' => '25 units'],
                    ['item' => 'Office Stationery', 'from' => 'XYZ Traders', 'qty' => '10 boxes'],
                ],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'todays_events' => [
                'key' => 'todays_events',
                'title' => "Today's Events",
                'icon' => 'calendar-event',
                'status' => 'green',
                'status_label' => 'Active',
                'count' => 2,
                'summary' => 'FA1 Exam + Moharram Holiday',
                'details' => [
                    ['event' => 'FA1 Exam - Std 6-10', 'type' => 'Exam', 'time' => 'All Day'],
                    ['event' => 'Moharram Holiday', 'type' => 'Holiday', 'time' => 'Full Day'],
                ],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'pending_tasks' => [
                'key' => 'pending_tasks',
                'title' => 'Pending Tasks',
                'icon' => 'list-check',
                'status' => 'green',
                'status_label' => 'All Done',
                'count' => 0,
                'summary' => 'No pending tasks',
                'details' => [],
                'pending_count' => 0,
                'has_drill' => false,
            ],
            'diagnostic_test' => [
                'key' => 'diagnostic_test',
                'title' => 'Diagnostic Test',
                'icon' => 'clipboard-check',
                'status' => 'yellow',
                'status_label' => 'Pending',
                'count' => 1,
                'summary' => '1 test pending generation',
                'details' => [],
                'pending_count' => 1,
                'has_drill' => true,
            ],
            'homework' => [
                'key' => 'homework',
                'title' => 'Today Given Homework',
                'icon' => 'book-open',
                'status' => 'green',
                'status_label' => 'Yes',
                'count' => 18,
                'summary' => 'Homework assigned in 18 classes',
                'details' => [],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'student_discipline' => [
                'key' => 'student_discipline',
                'title' => 'Student Discipline',
                'icon' => 'shield-check',
                'status' => 'green',
                'status_label' => 'Appropriate',
                'count' => 3,
                'summary' => '3 cases - All minor',
                'details' => [
                    ['student' => 'Karan Singh (9-C)', 'type' => 'Late submission', 'severity' => 'Minor'],
                    ['student' => 'Meera Jain (6-A)', 'type' => 'Uniform issue', 'severity' => 'Minor'],
                ],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'student_attendance' => [
                'key' => 'student_attendance',
                'title' => 'Student Attendance Report',
                'icon' => 'users',
                'status' => 'green',
                'status_label' => 'Submitted',
                'count' => 98.2,
                'summary' => '98.2% attendance',
                'details' => [],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'lesson_diary' => [
                'key' => 'lesson_diary',
                'title' => 'Teachers Lesson Diary',
                'icon' => 'journal-text',
                'status' => 'yellow',
                'status_label' => 'Pending',
                'count' => 7,
                'summary' => '7 teachers pending update',
                'details' => [],
                'pending_count' => 7,
                'has_drill' => true,
            ],
            'visitor' => [
                'key' => 'visitor',
                'title' => 'Visitor',
                'icon' => 'person-badge',
                'status' => 'gray',
                'status_label' => 'Nil',
                'count' => 0,
                'summary' => 'No visitors today',
                'details' => [],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'circular' => [
                'key' => 'circular',
                'title' => 'Circular',
                'icon' => 'file-earmark-text',
                'status' => 'green',
                'status_label' => '2 Issued',
                'count' => 2,
                'summary' => '2 circulars published',
                'details' => [
                    ['title' => 'Annual Sports Day Schedule', 'to' => 'All Parents', 'time' => '9:00 AM'],
                    ['title' => 'Fee Payment Reminder', 'to' => 'Std 1-12', 'time' => '2:00 PM'],
                ],
                'pending_count' => 0,
                'has_drill' => true,
            ],
            'communication' => [
                'key' => 'communication',
                'title' => 'Communication',
                'icon' => 'chat-dots',
                'status' => 'yellow',
                'status_label' => 'Mixed',
                'count' => 24,
                'summary' => '18 Replied / 4 Pending / 2 Not Viewed',
                'details' => [
                    ['channel' => 'WhatsApp Group - Class 7', 'replied' => 12, 'pending' => 1, 'not_viewed' => 0],
                    ['channel' => 'Email - Management', 'replied' => 6, 'pending' => 3, 'not_viewed' => 2],
                ],
                'pending_count' => 4,
                'has_drill' => true,
            ],
        ];

        // Calculate overall operational status
        $greenCount = 0; $yellowCount = 0; $redCount = 0; $grayCount = 0;
        foreach ($modules as $mod) {
            if ($mod['status'] === 'green') $greenCount++;
            elseif ($mod['status'] === 'yellow') $yellowCount++;
            elseif ($mod['status'] === 'red') $redCount++;
            else $grayCount++;
        }

        $totalModules = count($modules);
        $healthScore = round((($greenCount * 100) + ($yellowCount * 60) + ($redCount * 20) + ($grayCount * 40)) / $totalModules);

        if ($redCount > 0) {
            $overallStatus = 'Needs Attention';
            $overallColor = 'red';
        } elseif ($yellowCount > 3) {
            $overallStatus = 'Needs Attention';
            $overallColor = 'yellow';
        } elseif ($yellowCount > 0) {
            $overallStatus = 'Good';
            $overallColor = 'yellow';
        } else {
            $overallStatus = 'Excellent';
            $overallColor = 'green';
        }

        // Chart data for Apex/Chart.js
        $statusDistribution = [
            'labels' => ['Completed (Green)', 'Pending/Attention (Yellow)', 'Critical (Red)', 'Nil (Gray)'],
            'data' => [$greenCount, $yellowCount, $redCount, $grayCount],
            'colors' => ['#10b981', '#f59e0b', '#ef4444', '#6b7280']
        ];

        // Sample attendance trend data (last 7 days mock)
        $attendanceTrend = [
            'labels' => ['19 May', '20 May', '21 May', '22 May', '23 May', '24 May', '25 May'],
            'data' => [97.5, 98.1, 96.8, 99.0, 98.4, 97.9, 98.2]
        ];

        // Filters (for UI, client-side in this version)
        $availableBranches = ['Main Campus', 'East Wing', 'West Campus', 'Pre-Primary'];
        $availableStandards = ['All', 'Pre-Primary', '1-5', '6-8', '9-10', '11-12'];
        $availableDepts = ['All', 'Admin', 'Teaching', 'Support', 'Transport'];

        return view('reportsnew.mis-summary', [
            'modules' => $modules,
            'userRole' => $userRole,
            'schoolName' => $schoolName,
            'branch' => $branch,
            'reportDate' => $reportDate,
            'reportTime' => $reportTime,
            'overallStatus' => $overallStatus,
            'overallColor' => $overallColor,
            'healthScore' => $healthScore,
            'greenCount' => $greenCount,
            'yellowCount' => $yellowCount,
            'redCount' => $redCount,
            'grayCount' => $grayCount,
            'statusDistribution' => $statusDistribution,
            'attendanceTrend' => $attendanceTrend,
            'availableBranches' => $availableBranches,
            'availableStandards' => $availableStandards,
            'availableDepts' => $availableDepts,
            'totalModules' => $totalModules,
        ]);
    }

    // Future: API endpoint for real-time refresh or drilldown data
    public function getModuleDetails(Request $request, $moduleKey)
    {
        // In real impl: switch on $moduleKey and return DB data
        return response()->json(['success' => true, 'module' => $moduleKey, 'data' => []]);
    }
}
