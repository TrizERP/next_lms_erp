<?php

namespace App\Services\Dashboard;

/**
 * Every ordinary figure the role dashboards already know how to work out, in one plain shape a chat-built widget can show.
 *
 * The dashboard computes these for each role (RoleWorkspaceApiController::WIDGETS decides which role gets which). Here each
 * one is given a name, a sentence about what it is, and an adapter that turns its raw answer into one of five views:
 * number, facts, list, bars, series. The assistant can only pick from the entries a role is given, and the figures still
 * come from the same code and the same role limits as the dashboard itself, so nothing is reachable here that is not
 * already on that role's dashboard.
 *
 * Phone numbers are never passed through: a list shows who and where, not how to call them.
 */
class WidgetLibrary
{
    /** @return array<string, array{label:string, about:string, keywords:array<int,string>, widget:string, filters?:array<int,string>}> */
    public static function entries(): array
    {
        return [
            'students_total' => ['label' => 'Total students', 'about' => 'How many students are enrolled this year', 'keywords' => ['total students', 'how many students', 'enrolled']],
            'staff_total' => ['label' => 'Total staff', 'about' => 'Active staff members', 'keywords' => ['total staff', 'how many staff', 'employees', 'teachers count']],
            'attendance_today' => ['label' => 'Attendance today', 'about' => 'Present, absent and not yet marked today', 'keywords' => ['attendance today', 'present today', 'absent count']],
            'attendance_trend' => ['label' => 'Attendance, last days', 'about' => 'Daily attendance rate over the last two weeks', 'keywords' => ['attendance trend', 'attendance rate']],
            'class_attendance' => ['label' => 'Attendance by class', 'about' => 'Attendance rate of each class on the latest marked day', 'keywords' => ['class attendance', 'attendance by class']],
            'absent_by_class' => ['label' => 'Absent students by class', 'about' => 'How many students were absent in each class on the latest marked day (numbers only)', 'keywords' => ['absent by class', 'absent per class']],
            'chronic_by_class' => ['label' => 'Repeated absentees by class', 'about' => 'How many students in each class were absent on most of the last marked days (numbers only)', 'keywords' => ['repeated absent by class', 'irregular by class']],
            'unmarked_classes' => ['label' => 'Classes yet to mark attendance', 'about' => 'Classes with students that have not marked attendance today', 'keywords' => ['attendance not marked', 'unmarked classes', 'yet to mark']],
            'low_attendance_classes' => ['label' => 'Classes with low attendance', 'about' => 'Classes below 85% on the latest marked day, lowest first', 'keywords' => ['low attendance', 'lowest attendance classes'], 'widget' => 'class_attendance'],
            'fees_by_mode' => ['label' => 'Fees by payment mode', 'about' => 'Fees collected this year, by cash, cheque, online and so on', 'keywords' => ['payment mode', 'cash cheque online']],
            'fees_by_class' => ['label' => 'Fees collected by class', 'about' => 'Fees collected this year in each class', 'keywords' => ['fees by class', 'class wise fees']],
            'enquiries_by_month' => ['label' => 'Enquiries by month', 'about' => 'Admission enquiries received in each of the last 6 months', 'keywords' => ['enquiries by month', 'monthly enquiries']],
            'enquiries_by_source' => ['label' => 'Enquiries by source', 'about' => 'Where this year\'s enquiries came from', 'keywords' => ['enquiry source', 'where enquiries come from']],
            'enquiries_by_class' => ['label' => 'Enquiries by class', 'about' => 'Which class this year\'s enquiries are for', 'keywords' => ['enquiries by class', 'class wise enquiries']],
            'students_joined_by_month' => ['label' => 'Students joined by month', 'about' => 'Students whose enrolment started in each of the last 6 months', 'keywords' => ['joined by month', 'new students by month']],
            'staff_by_role' => ['label' => 'Staff by role', 'about' => 'Active staff in each role (teacher, clerk and so on)', 'keywords' => ['staff by role', 'role wise staff']],
            'leaves_by_type' => ['label' => 'Leave by type', 'about' => 'Leave taken or asked for in the last 12 months, by type of leave', 'keywords' => ['leave by type', 'type of leave']],
            'homework_by_month' => ['label' => 'Homework given by month', 'about' => 'How many homeworks were given in each of the last 6 months', 'keywords' => ['homework by month', 'homework monthly']],
            'homework_by_class' => ['label' => 'Homework given by class', 'about' => 'How many homeworks each class was given this year', 'keywords' => ['homework by class', 'class wise homework']],
            'complaints_by_month' => ['label' => 'Complaints by month', 'about' => 'Complaints received in each of the last 6 months', 'keywords' => ['complaints by month']],
            'library_issues_by_month' => ['label' => 'Library books issued by month', 'about' => 'Books issued in each of the last 6 months', 'keywords' => ['books issued', 'library issues']],
            'items_by_category' => ['label' => 'Store items by category', 'about' => 'How many items the store holds in each category', 'keywords' => ['items by category', 'store category']],
            'absent_today' => ['label' => 'Absent students today', 'about' => 'Students marked absent today with their class', 'keywords' => ['absent students', 'who is absent', 'absentees']],
            'chronic_absentees' => ['label' => 'Repeated absentees', 'about' => 'Students absent on most of the last marked days', 'keywords' => ['chronic', 'repeated absence', 'irregular students']],
            'substitutes_needed' => ['label' => 'Substitutes needed', 'about' => 'Periods today whose teacher is on leave and has no proxy', 'keywords' => ['substitute', 'proxy', 'uncovered periods']],
            'staff_on_leave_today' => ['label' => 'Staff on leave today', 'about' => 'How many staff are on approved leave today', 'keywords' => ['staff leave', 'on leave']],
            'staff_on_leave_list' => ['label' => 'Staff on leave (names)', 'about' => 'Who is on leave today and until when', 'keywords' => ['who is on leave', 'leave list']],
            'leaves_pending' => ['label' => 'Leave requests waiting', 'about' => 'Leave requests not yet approved', 'keywords' => ['pending leave', 'leave approval']],
            'my_leaves_pending' => ['label' => 'My leave requests waiting', 'about' => 'Your own leave requests not yet approved', 'keywords' => ['my leave']],
            'birthdays_students' => ['label' => 'Student birthdays', 'about' => 'Upcoming student birthdays (can be limited to today)', 'keywords' => ['student birthday', 'birthdays students', 'birthday'], 'widget' => 'birthdays', 'filters' => ['today']],
            'birthdays_staff' => ['label' => 'Staff birthdays', 'about' => 'Upcoming staff birthdays (can be limited to today)', 'keywords' => ['staff birthday', 'teacher birthday'], 'widget' => 'birthdays', 'filters' => ['today']],
            'upcoming_events' => ['label' => 'Upcoming events and holidays', 'about' => 'The school calendar for the coming days', 'keywords' => ['calendar', 'events', 'holiday']],
            'upcoming_exams' => ['label' => 'Upcoming exams', 'about' => 'Exams coming up', 'keywords' => ['exam', 'exams', 'test schedule']],
            'recent_circulars' => ['label' => 'Recent circulars', 'about' => 'The latest circulars and notices', 'keywords' => ['circular', 'notice', 'announcement']],
            'fees_today' => ['label' => 'Fees collected today', 'about' => 'Rupees collected today', 'keywords' => ['fees today', 'collection today']],
            'fees_period' => ['label' => 'Fees collected (period)', 'about' => 'Fees collected in the selected period against the one before', 'keywords' => ['fees period', 'fees this month']],
            'fees_month' => ['label' => 'Fees this month vs last month', 'about' => 'This month so far against the same days last month', 'keywords' => ['fees month', 'monthly fees comparison']],
            'fee_trend' => ['label' => 'Fee collection trend', 'about' => 'Daily fees collected over the last days', 'keywords' => ['fee trend', 'collection trend']],
            'recent_fee_receipts' => ['label' => 'Recent fee receipts', 'about' => 'The latest receipts issued', 'keywords' => ['receipts', 'recent payments']],
            'admissions_year' => ['label' => 'Admission enquiries this year', 'about' => 'Enquiries received this academic year', 'keywords' => ['enquiries total']],
            'admissions_period' => ['label' => 'Admission enquiries (period)', 'about' => 'Enquiries in the selected period against the one before', 'keywords' => ['enquiries period']],
            'admission_funnel' => ['label' => 'Admission funnel', 'about' => 'Enquiries, registrations and admitted', 'keywords' => ['funnel', 'conversion', 'admitted']],
            'recent_enquiries' => ['label' => 'Recent enquiries', 'about' => 'The latest admission enquiries', 'keywords' => ['new enquiries']],
            'followups_due' => ['label' => 'Enquiry follow-ups due', 'about' => 'Enquiries that need a follow-up', 'keywords' => ['follow up', 'followup']],
            'visitors_today' => ['label' => 'Visitors today', 'about' => 'Visitors today and how many are still inside', 'keywords' => ['visitors', 'visitor']],
            'visitors_period' => ['label' => 'Visitors (period)', 'about' => 'Visitors in the selected period against the one before', 'keywords' => ['visitors period']],
            'complaints' => ['label' => 'Complaints', 'about' => 'Complaints received and how many are still open', 'keywords' => ['complaint', 'grievance']],
            'tasks_pending_all' => ['label' => 'Open tasks (all)', 'about' => 'Tasks still open across the school', 'keywords' => ['tasks open', 'pending tasks']],
            'tasks_pending_me' => ['label' => 'My open tasks', 'about' => 'Tasks assigned to you that are still open', 'keywords' => ['my tasks', 'to do']],
            'task_aging' => ['label' => 'How late tasks are', 'about' => 'Open tasks grouped by how long past their due date', 'keywords' => ['late tasks', 'overdue tasks']],
            'parent_msgs_pending' => ['label' => 'Parent messages waiting', 'about' => 'Parent messages not yet answered', 'keywords' => ['parent messages', 'parent queries']],
            'parent_msg_age' => ['label' => 'How long parents wait', 'about' => 'Unanswered parent messages by waiting time', 'keywords' => ['parent waiting']],
            'recent_parent_messages' => ['label' => 'Recent parent messages', 'about' => 'The latest messages from parents', 'keywords' => ['parent message list']],
            'pending_approvals' => ['label' => 'Approvals waiting', 'about' => 'Leaves, requisitions and purchase orders waiting for a decision', 'keywords' => ['approvals', 'pending approvals']],
            'students_by_class' => ['label' => 'Students by class', 'about' => 'Enrolled students in each class', 'keywords' => ['strength by class', 'class strength']],
            'gender_split' => ['label' => 'Boys and girls', 'about' => 'Students by gender', 'keywords' => ['gender', 'boys girls']],
            'homework_rate' => ['label' => 'Homework completion by class', 'about' => 'Share of homework handed in, class by class', 'keywords' => ['homework']],
            'strength_changes' => ['label' => 'Joiners and leavers', 'about' => 'Students who joined, left or took a transfer certificate', 'keywords' => ['joined', 'left school', 'tc', 'strength change']],
            'transport_summary' => ['label' => 'Transport', 'about' => 'Students using transport and the fullest routes', 'keywords' => ['transport', 'bus', 'route']],
            'hostel_summary' => ['label' => 'Hostel', 'about' => 'Hostels, rooms and residents', 'keywords' => ['hostel', 'rooms', 'residents']],
            'library_overdue' => ['label' => 'Library books overdue', 'about' => 'Books not returned on time', 'keywords' => ['library', 'overdue books']],
            'stock_reorder' => ['label' => 'Stock at reorder level', 'about' => 'Items whose recorded stock is at or below the reorder level', 'keywords' => ['stock', 'reorder', 'inventory']],
            'requisitions' => ['label' => 'Requisitions', 'about' => 'Requisitions by status', 'keywords' => ['requisition']],
            'recent_requisitions' => ['label' => 'Recent requisitions', 'about' => 'The latest requisitions', 'keywords' => ['requisition list']],
            'po_status' => ['label' => 'Purchase orders', 'about' => 'Purchase orders awaiting a decision and decided', 'keywords' => ['purchase order', 'po']],
            'purchases_month' => ['label' => 'Purchases this month', 'about' => 'Number and value of purchases this month', 'keywords' => ['purchases']],
            'items_total' => ['label' => 'Items in store', 'about' => 'Number of items in the store master', 'keywords' => ['items']],
            'vendors_total' => ['label' => 'Vendors', 'about' => 'Number of vendors', 'keywords' => ['vendors', 'suppliers']],
            'inward_month' => ['label' => 'Inward entries this month', 'about' => 'Letters and parcels received this month', 'keywords' => ['inward']],
            'outward_month' => ['label' => 'Outward entries this month', 'about' => 'Letters and parcels sent this month', 'keywords' => ['outward']],
            'petty_cash_month' => ['label' => 'Petty cash this month', 'about' => 'Petty cash spent this month', 'keywords' => ['petty cash']],
            'exam_evaluation' => ['label' => 'Exam evaluation progress', 'about' => 'Answer sheets received, evaluated and approved', 'keywords' => ['evaluation', 'answer sheets']],
            'attendance_pending' => ['label' => 'Attendance not yet marked', 'about' => 'Your classes whose attendance is not marked today', 'keywords' => ['attendance pending']],
            'homework_pending' => ['label' => 'Homework not handed in', 'about' => 'Students yet to hand in homework in your classes', 'keywords' => ['homework pending']],
            'assignments_to_grade' => ['label' => 'Assignments to grade', 'about' => 'Submitted assignments waiting for you', 'keywords' => ['grade', 'assignments']],
            'payroll' => ['label' => 'Staff salary paid', 'about' => 'Total salary paid in the latest payroll month (totals only)', 'keywords' => ['salary', 'payroll']],
        ];
    }

    /**
     * The subjects a figure can belong to, for "show me more like this". `core` are the few figures that open first when a subject is
     * clicked; the rest of `entries` are the deeper detail, reached by clicking a card (see RELATED). `entries` are library keys;
     * `next` are the subjects people usually go to from there.
     */
    public const TOPICS = [
        'students' => ['label' => 'Students', 'core' => ['students_total', 'students_by_class', 'gender_split'], 'entries' => ['students_total', 'students_by_class', 'gender_split', 'strength_changes', 'students_joined_by_month', 'birthdays_students'], 'next' => ['attendance', 'academics', 'fees']],
        'attendance' => ['label' => 'Attendance', 'core' => ['attendance_today', 'attendance_trend', 'class_attendance'], 'entries' => ['attendance_today', 'attendance_trend', 'class_attendance', 'low_attendance_classes', 'absent_by_class', 'chronic_by_class', 'unmarked_classes', 'absent_today', 'chronic_absentees'], 'next' => ['students', 'staff', 'academics']],
        'staff' => ['label' => 'Staff', 'core' => ['staff_total', 'staff_on_leave_today', 'leaves_pending'], 'entries' => ['staff_total', 'staff_on_leave_today', 'staff_on_leave_list', 'leaves_pending', 'leaves_by_type', 'staff_by_role', 'substitutes_needed', 'birthdays_staff', 'payroll'], 'next' => ['attendance', 'communication']],
        'fees' => ['label' => 'Fees', 'core' => ['fees_today', 'fees_period', 'fee_trend'], 'entries' => ['fees_today', 'fees_period', 'fees_month', 'fee_trend', 'fees_by_mode', 'fees_by_class', 'recent_fee_receipts'], 'next' => ['admissions', 'students']],
        'admissions' => ['label' => 'Admissions', 'core' => ['admissions_year', 'admissions_period', 'admission_funnel'], 'entries' => ['admissions_year', 'admissions_period', 'admission_funnel', 'enquiries_by_month', 'enquiries_by_source', 'enquiries_by_class', 'recent_enquiries', 'followups_due', 'visitors_today', 'visitors_period'], 'next' => ['fees', 'students']],
        'academics' => ['label' => 'Academics', 'core' => ['upcoming_exams', 'homework_rate', 'homework_by_class'], 'entries' => ['upcoming_exams', 'exam_evaluation', 'homework_rate', 'homework_by_month', 'homework_by_class', 'homework_pending', 'assignments_to_grade'], 'next' => ['students', 'attendance']],
        'communication' => ['label' => 'Messages and tasks', 'core' => ['recent_circulars', 'parent_msgs_pending', 'tasks_pending_all', 'pending_approvals'], 'entries' => ['recent_circulars', 'parent_msgs_pending', 'parent_msg_age', 'recent_parent_messages', 'complaints', 'complaints_by_month', 'tasks_pending_all', 'tasks_pending_me', 'task_aging', 'pending_approvals', 'upcoming_events'], 'next' => ['students', 'staff']],
        'facilities' => ['label' => 'Facilities and store', 'core' => ['transport_summary', 'hostel_summary', 'library_issues_by_month', 'items_by_category'], 'entries' => ['transport_summary', 'hostel_summary', 'library_issues_by_month', 'items_by_category', 'library_overdue', 'stock_reorder', 'requisitions', 'po_status', 'purchases_month', 'items_total', 'vendors_total', 'inward_month', 'outward_month', 'petty_cash_month'], 'next' => ['students', 'communication']],
    ];

    /**
     * "Because you looked at this": for each figure, the figures people look at next, across subjects. Clicking a card inside an
     * opened section shows these under it. A figure with no entry here falls back to the other figures of its subject.
     */
    public const RELATED = [
        'students_total' => ['strength_changes', 'students_joined_by_month', 'birthdays_students', 'attendance_today'],
        'students_by_class' => ['homework_by_class', 'class_attendance', 'enquiries_by_class'],
        'gender_split' => ['birthdays_students', 'strength_changes', 'students_joined_by_month'],
        'strength_changes' => ['students_joined_by_month', 'admissions_period', 'students_by_class'],
        'students_joined_by_month' => ['strength_changes', 'enquiries_by_month', 'students_by_class'],
        'birthdays_students' => ['birthdays_staff', 'upcoming_events'],
        'attendance_today' => ['absent_by_class', 'class_attendance', 'unmarked_classes', 'chronic_by_class', 'low_attendance_classes', 'attendance_trend', 'absent_today', 'chronic_absentees'],
        'attendance_trend' => ['low_attendance_classes', 'absent_by_class', 'class_attendance', 'unmarked_classes', 'chronic_by_class'],
        'class_attendance' => ['absent_by_class', 'low_attendance_classes', 'chronic_by_class', 'unmarked_classes', 'attendance_trend', 'absent_today', 'chronic_absentees'],
        'low_attendance_classes' => ['absent_by_class', 'chronic_by_class', 'class_attendance', 'attendance_trend'],
        'absent_by_class' => ['chronic_by_class', 'class_attendance', 'low_attendance_classes', 'attendance_trend', 'absent_today'],
        'chronic_by_class' => ['absent_by_class', 'class_attendance', 'low_attendance_classes', 'chronic_absentees'],
        'unmarked_classes' => ['class_attendance', 'attendance_today', 'absent_by_class'],
        'absent_today' => ['absent_by_class', 'chronic_absentees', 'class_attendance', 'chronic_by_class'],
        'chronic_absentees' => ['chronic_by_class', 'absent_today', 'absent_by_class', 'class_attendance'],
        'staff_total' => ['staff_by_role', 'birthdays_staff', 'payroll', 'leaves_by_type'],
        'staff_on_leave_today' => ['leaves_by_type', 'staff_on_leave_list', 'substitutes_needed', 'staff_by_role'],
        'staff_on_leave_list' => ['substitutes_needed', 'leaves_by_type', 'leaves_pending'],
        'leaves_pending' => ['leaves_by_type', 'pending_approvals', 'staff_on_leave_list'],
        'leaves_by_type' => ['leaves_pending', 'staff_on_leave_today', 'staff_by_role'],
        'staff_by_role' => ['staff_total', 'leaves_by_type', 'birthdays_staff'],
        'substitutes_needed' => ['staff_on_leave_list', 'staff_on_leave_today', 'attendance_today'],
        'birthdays_staff' => ['birthdays_students', 'upcoming_events', 'staff_by_role'],
        'payroll' => ['staff_by_role', 'staff_total', 'leaves_by_type', 'fees_month'],
        'fees_today' => ['fees_month', 'fees_by_mode', 'fees_by_class', 'recent_fee_receipts'],
        'fees_period' => ['fees_by_mode', 'fees_by_class', 'fees_month'],
        'fees_month' => ['fees_by_mode', 'fees_by_class', 'fee_trend'],
        'fee_trend' => ['fees_month', 'fees_by_mode', 'fees_by_class'],
        'fees_by_mode' => ['fees_by_class', 'fees_month', 'fee_trend', 'recent_fee_receipts'],
        'fees_by_class' => ['fees_by_mode', 'fees_month', 'students_by_class'],
        'recent_fee_receipts' => ['fees_today', 'fees_by_mode', 'fee_trend'],
        'admissions_year' => ['enquiries_by_month', 'enquiries_by_source', 'enquiries_by_class', 'recent_enquiries'],
        'admissions_period' => ['enquiries_by_month', 'enquiries_by_source', 'visitors_period'],
        'admission_funnel' => ['enquiries_by_source', 'enquiries_by_class', 'recent_enquiries', 'followups_due', 'fees_period'],
        'enquiries_by_month' => ['enquiries_by_source', 'enquiries_by_class', 'admissions_year', 'visitors_period'],
        'enquiries_by_source' => ['enquiries_by_class', 'enquiries_by_month', 'admission_funnel'],
        'enquiries_by_class' => ['enquiries_by_source', 'enquiries_by_month', 'students_by_class'],
        'recent_enquiries' => ['followups_due', 'admission_funnel', 'enquiries_by_source', 'visitors_today'],
        'followups_due' => ['recent_enquiries', 'admission_funnel', 'visitors_today'],
        'visitors_today' => ['visitors_period', 'recent_enquiries', 'complaints'],
        'visitors_period' => ['visitors_today', 'admissions_period'],
        'upcoming_exams' => ['exam_evaluation', 'homework_by_class', 'homework_by_month', 'class_attendance'],
        'exam_evaluation' => ['upcoming_exams', 'assignments_to_grade'],
        'homework_rate' => ['homework_by_class', 'homework_by_month', 'homework_pending', 'class_attendance'],
        'homework_by_month' => ['homework_by_class', 'homework_rate', 'homework_pending'],
        'homework_by_class' => ['homework_by_month', 'homework_rate', 'students_by_class'],
        'homework_pending' => ['homework_rate', 'assignments_to_grade', 'homework_by_class'],
        'assignments_to_grade' => ['homework_pending', 'upcoming_exams'],
        'recent_circulars' => ['upcoming_events', 'inward_month', 'parent_msgs_pending'],
        'parent_msgs_pending' => ['parent_msg_age', 'recent_parent_messages', 'complaints', 'complaints_by_month'],
        'parent_msg_age' => ['parent_msgs_pending', 'recent_parent_messages'],
        'recent_parent_messages' => ['parent_msgs_pending', 'parent_msg_age'],
        'complaints' => ['complaints_by_month', 'parent_msgs_pending', 'visitors_today'],
        'complaints_by_month' => ['complaints', 'parent_msgs_pending'],
        'tasks_pending_all' => ['task_aging', 'tasks_pending_me', 'pending_approvals'],
        'tasks_pending_me' => ['task_aging', 'tasks_pending_all'],
        'task_aging' => ['tasks_pending_all', 'pending_approvals'],
        'pending_approvals' => ['leaves_pending', 'requisitions', 'po_status', 'tasks_pending_all'],
        'upcoming_events' => ['recent_circulars', 'birthdays_students', 'upcoming_exams'],
        'transport_summary' => ['students_total', 'complaints'],
        'hostel_summary' => ['students_total', 'strength_changes'],
        'library_issues_by_month' => ['library_overdue', 'items_by_category'],
        'library_overdue' => ['library_issues_by_month', 'students_total'],
        'items_by_category' => ['stock_reorder', 'vendors_total', 'purchases_month', 'requisitions'],
        'stock_reorder' => ['requisitions', 'po_status', 'purchases_month', 'items_by_category'],
        'requisitions' => ['recent_requisitions', 'po_status', 'stock_reorder'],
        'recent_requisitions' => ['requisitions', 'po_status'],
        'po_status' => ['requisitions', 'purchases_month', 'vendors_total'],
        'purchases_month' => ['po_status', 'vendors_total', 'petty_cash_month'],
        'items_total' => ['items_by_category', 'stock_reorder', 'vendors_total'],
        'vendors_total' => ['purchases_month', 'po_status'],
        'inward_month' => ['outward_month', 'recent_circulars'],
        'outward_month' => ['inward_month'],
        'petty_cash_month' => ['purchases_month', 'fees_month'],
    ];

    /** What a role may ask for: only entries whose dashboard widget the role is given. */
    public static function forRole(array $roleWidgets): array
    {
        return array_filter(self::entries(), fn ($e, $k) => in_array($e['widget'] ?? $k, $roleWidgets, true), ARRAY_FILTER_USE_BOTH);
    }

    public static function exists(string $key): bool
    {
        return isset(self::entries()[$key]);
    }

    /** The dashboard widget an entry reads from. */
    public static function widgetOf(string $key): ?string
    {
        $e = self::entries()[$key] ?? null;

        return $e ? ($e['widget'] ?? $key) : null;
    }

    /**
     * Turn the raw answer into a view the page can draw. Null when there is nothing usable (no data).
     *
     * @param  array{only?:string}  $opts
     */
    public static function present(string $key, mixed $raw, array $opts = []): ?array
    {
        if ($raw === null) {
            return null;
        }
        $raw = json_decode(json_encode($raw), true);
        $n = fn ($x) => is_numeric($x) ? $x + 0 : null;
        $number = fn ($v, string $unit = 'count', ?string $note = null) => ['kind' => 'number', 'value' => $n($v), 'unit' => $unit, 'note' => $note];
        $facts = fn (array $items) => ['kind' => 'facts', 'items' => array_values(array_filter($items, fn ($i) => $i['value'] !== null))];
        $f = fn (string $label, $v, string $unit = 'count') => ['label' => $label, 'value' => $n($v), 'unit' => $unit];
        $list = fn (array $items, ?int $total = null) => ['kind' => 'list', 'total' => $total ?? count($items), 'items' => array_slice($items, 0, 10)];
        $bars = fn (array $rows, string $unit = 'count') => ['kind' => 'bars', 'unit' => $unit, 'rows' => array_slice($rows, 0, 20)];
        $row = fn ($title, $sub = null, $tag = null) => ['title' => trim((string) $title), 'sub' => $sub === null ? null : trim((string) $sub), 'tag' => $tag];

        return match ($key) {
            'students_total', 'staff_total', 'staff_on_leave_today', 'leaves_pending', 'my_leaves_pending', 'admissions_year', 'tasks_pending_all', 'parent_msgs_pending', 'items_total', 'vendors_total', 'inward_month', 'outward_month' => $number($raw),
            'fees_today', 'petty_cash_month' => $number($raw, 'rupees'),
            'attendance_today' => $facts([$f('Present', $raw['present'] ?? null), $f('Absent', $raw['absent'] ?? null), $f('Not marked', $raw['not_marked'] ?? null)]),
            'attendance_trend' => ['kind' => 'series', 'unit' => 'percent', 'rows' => collect($raw)->filter(fn ($r) => ($r['rate'] ?? null) !== null)->take(-14)->map(fn ($r) => ['label' => (string) $r['label'], 'value' => $r['rate']])->values()->all()],
            'class_attendance' => $bars(collect($raw['rows'] ?? [])->map(fn ($r) => ['label' => (string) $r['standard_name'], 'value' => $r['rate']])->all(), 'percent') + ['as_of' => $raw['date'] ?? null],
            'fees_by_mode', 'fees_by_class' => $bars(collect($raw['rows'] ?? [])->all(), 'rupees'),
            'enquiries_by_source', 'enquiries_by_class', 'staff_by_role', 'leaves_by_type', 'homework_by_class', 'items_by_category' => $bars(collect($raw['rows'] ?? [])->all()),
            'enquiries_by_month', 'students_joined_by_month', 'homework_by_month', 'complaints_by_month', 'library_issues_by_month' => ['kind' => 'series', 'unit' => 'count', 'rows' => collect($raw['rows'] ?? [])->all()],
            'absent_by_class' => $bars(collect($raw['rows'] ?? [])->map(fn ($r) => ['label' => (string) $r['label'], 'value' => $r['value']])->all()) + ['as_of' => $raw['date'] ?? null],
            'chronic_by_class' => $bars(collect($raw['rows'] ?? [])->map(fn ($r) => ['label' => (string) $r['label'], 'value' => $r['value']])->all()),
            'unmarked_classes' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['label'], null, null))->all(), $raw['total'] ?? null) + ['empty_note' => 'Every class with students has marked attendance today.'],
            'low_attendance_classes' => $list(collect($raw['rows'] ?? [])->filter(fn ($r) => ($r['rate'] ?? 100) < 85)->map(fn ($r) => $row($r['standard_name'], ($r['present'] ?? 0).' present of '.(($r['present'] ?? 0) + ($r['absent'] ?? 0)), ($r['rate'] ?? 0).'%'))->values()->all()) + ['empty_note' => 'No class is below 85% on the latest marked day.'],
            'absent_today' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['name'], $r['class_name'] ?? null))->all(), $raw['total'] ?? null),
            'chronic_absentees' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['name'], $r['class_name'] ?? null, ($r['absences'] ?? 0).' absences'))->all(), $raw['total'] ?? null),
            'substitutes_needed' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['title'], $r['detail'] ?? null))->all(), $raw['uncovered'] ?? null),
            'staff_on_leave_list' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['name'], isset($r['to_date']) ? 'until '.substr((string) $r['to_date'], 0, 10) : null))->all(), $raw['total'] ?? null),
            'birthdays_students', 'birthdays_staff' => self::birthdays($key === 'birthdays_students' ? ($raw['students'] ?? []) : ($raw['staff'] ?? []), $opts['only'] ?? null, $row),
            'upcoming_events' => $list(collect($raw)->map(fn ($r) => $row($r['title'], $r['date'] ?? null, $r['kind'] ?? null))->all()),
            'upcoming_exams', 'tasks_pending_me', 'assignments_to_grade' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['title'], $r['detail'] ?? null, $r['date'] ?? null))->all(), $raw['count'] ?? null),
            'recent_circulars', 'recent_enquiries', 'recent_requisitions' => $list(collect($raw)->map(fn ($r) => $row($r['title'], $r['detail'] ?? null, $r['date'] ?? null))->all()),
            'recent_parent_messages' => $list(collect($raw)->map(fn ($r) => $row($r['title'], $r['detail'] ?? null, $r['date'] ?? null))->all()),
            'fees_period' => $facts([$f($raw['label'] ?? 'This period', $raw['amount'] ?? null, 'rupees'), $f('Before that', $raw['previous'] ?? null, 'rupees')]),
            'fees_month' => $facts([$f($raw['month_label'] ?? 'This month', $raw['this_month'] ?? null, 'rupees'), $f(($raw['last_month_label'] ?? 'Last month').' (same days)', $raw['last_month_same_days'] ?? null, 'rupees')]),
            'fee_trend' => ['kind' => 'series', 'unit' => 'rupees', 'rows' => collect($raw)->take(-14)->map(fn ($r) => ['label' => (string) $r['label'], 'value' => $r['amount']])->values()->all()],
            'recent_fee_receipts' => $list(collect($raw)->map(fn ($r) => $row($r['student_name'], trim(($r['receipt_no'] ?? '').' · '.($r['date'] ?? ''), ' ·'), '₹'.number_format((float) ($r['amount'] ?? 0))))->all()),
            'admissions_period', 'visitors_period' => $facts([$f($raw['label'] ?? 'This period', $raw['count'] ?? null), $f('Before that', $raw['previous'] ?? null)]),
            'admission_funnel' => $bars([['label' => 'Enquiries', 'value' => $raw['enquiries'] ?? 0], ['label' => 'Registered', 'value' => $raw['registered'] ?? 0], ['label' => 'Admitted', 'value' => $raw['admitted'] ?? 0]]),
            'followups_due' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['name'] ?? '', $r['enquiry_no'] ?? null, isset($r['followup_date']) ? substr((string) $r['followup_date'], 0, 10) : null))->all(), $raw['total'] ?? null),
            'visitors_today' => $facts([$f('Visitors', $raw['total'] ?? null), $f('Still inside', $raw['inside'] ?? null)]),
            'complaints' => $facts([$f('Received', $raw['total'] ?? null), $f('Open', $raw['open'] ?? null)]),
            'task_aging' => $facts([$f('Not late', $raw['not_late'] ?? null), $f('Up to 7 days late', $raw['late_7'] ?? null), $f('Up to 30 days late', $raw['late_30'] ?? null), $f('Over 30 days late', $raw['late_over'] ?? null)]),
            'parent_msg_age' => $facts([$f('Under 3 days', $raw['lt3'] ?? null), $f('3 to 7 days', $raw['d3_7'] ?? null), $f('Over 7 days', $raw['gt7'] ?? null)]),
            'pending_approvals' => $facts([$f('Leaves', $raw['leaves'] ?? null), $f('Requisitions', $raw['requisitions'] ?? null), $f('Purchase orders', $raw['purchase_orders'] ?? null)]),
            'students_by_class' => $bars(collect($raw)->map(fn ($r) => ['label' => (string) $r['standard_name'], 'value' => $r['students']])->all()),
            'gender_split' => $bars(collect($raw)->filter(fn ($v) => $v > 0)->map(fn ($v, $k) => ['label' => (string) $k, 'value' => $v])->values()->all()),
            'homework_rate' => $bars(collect($raw['rows'] ?? [])->filter(fn ($r) => ($r['rate'] ?? null) !== null)->map(fn ($r) => ['label' => (string) $r['standard_name'], 'value' => $r['rate']])->values()->all(), 'percent'),
            'strength_changes' => $facts([$f('Joined this month', $raw['joined_month'] ?? null), $f('Joined this year', $raw['joined_year'] ?? null), $f('Left this year', $raw['left_year'] ?? null), $f('Transfer certificates', $raw['tc_year'] ?? null)]),
            'transport_summary' => $bars(collect($raw['top'] ?? [])->map(fn ($r) => ['label' => (string) $r['title'], 'value' => $r['students']])->all()) + ['headline' => ['students' => $raw['students'] ?? null, 'buses' => $raw['buses'] ?? null]],
            'hostel_summary' => $facts([$f('Hostels', $raw['hostels'] ?? null), $f('Rooms', $raw['rooms'] ?? null), $f('Rooms in use', $raw['rooms_in_use'] ?? null), $f('Residents', $raw['residents'] ?? null)]),
            'library_overdue' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['title'], $r['detail'] ?? null, isset($r['days']) ? $r['days'].' days late' : null))->all(), $raw['total'] ?? null),
            'stock_reorder' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['title'], 'recorded '.($r['recorded'] ?? '?').', reorder at '.($r['reorder_at'] ?? '?')))->all(), $raw['total'] ?? null),
            'requisitions' => $facts([$f('Pending', $raw['pending'] ?? null), $f('Approved', $raw['approved'] ?? null), $f('Rejected', $raw['rejected'] ?? null), $f('On hold', $raw['on_hold'] ?? null)]),
            'po_status' => $facts([$f('Awaiting decision', $raw['awaiting'] ?? null), $f('Decided', $raw['decided'] ?? null)]),
            'purchases_month' => $facts([$f('Purchases', $raw['count'] ?? null), $f('Value', $raw['amount'] ?? null, 'rupees')]),
            'exam_evaluation' => $facts([$f('Batches', $raw['batches'] ?? null), $f('Sheets', $raw['sheets'] ?? null), $f('Evaluated', $raw['evaluated'] ?? null), $f('Approved', $raw['approved'] ?? null)]),
            'attendance_pending' => $list(collect($raw['items'] ?? [])->map(fn ($r) => $row($r['label'] ?? ''))->all(), $raw['total'] ?? null),
            'homework_pending' => $facts([$f('Homework items', $raw['total'] ?? null), $f('Students', $raw['students'] ?? null)]),
            'payroll' => $facts([$f('Paid in '.($raw['latest']['label'] ?? 'the latest month'), $raw['latest']['paid'] ?? null, 'rupees'), $f('Staff covered', $raw['latest']['staff'] ?? null), $f('Active staff', $raw['active_staff'] ?? null)]) + ['caution' => $raw['caution'] ?? null],
            default => null,
        };
    }

    private static function birthdays(array $people, ?string $only, callable $row): array
    {
        $people = collect($people);
        if ($only === 'today') {
            $people = $people->filter(fn ($p) => (int) ($p['in_days'] ?? 1) === 0);
        }
        $items = $people->map(fn ($p) => $row($p['name'], $p['detail'] ?? null, match ((int) ($p['in_days'] ?? 1)) { 0 => 'Today', 1 => 'Tomorrow', default => 'in '.($p['in_days'] ?? '?').' days' }))->values()->all();

        return ['kind' => 'list', 'total' => count($items), 'items' => array_slice($items, 0, 10), 'empty_note' => $only === 'today' ? 'Nobody has a birthday today.' : null];
    }
}
