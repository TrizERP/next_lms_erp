<?php

namespace Database\Seeders;

use App\Models\OrganizationManagement\ComplianceLibraryRecord;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample school-compliance rows for the Compliance Library register.
 *
 * Idempotent: a row is skipped when the tenant already has a compliance with
 * the same name. Categories are the platform-wide defaults
 * (compliance_categories.sub_institute_id IS NULL); departments and assignees
 * are picked from the tenant's own hrms_departments / tbluser rows, so nothing
 * here points at an id that does not exist.
 *
 * Run (bypasses the artisan guard): see the session notes, or
 *   php artisan db:seed --class=ComplianceLibrarySampleSeeder
 * with SUB_INSTITUTE_ID=<tenant> in the environment (default 1).
 */
class ComplianceLibrarySampleSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = (int) (getenv('SUB_INSTITUTE_ID') ?: 1);
        $today = Carbon::today();

        $categories = DB::table('compliance_categories')
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('sub_institute_id')->orWhere('sub_institute_id', $tenant))
            ->pluck('id', 'name');

        // One department per distinct name, so the dropdown/filters show variety.
        $departments = DB::table('hrms_departments')
            ->where('sub_institute_id', $tenant)
            ->orderBy('id')
            ->get(['id', 'department'])
            ->unique('department')
            ->values();

        $assignees = DB::table('tbluser')
            ->where('sub_institute_id', $tenant)
            ->orderBy('id')
            ->limit(12)
            ->pluck('id')
            ->values();

        if ($departments->isEmpty() || $assignees->isEmpty()) {
            $this->command?->warn("Tenant {$tenant} has no departments or users; nothing seeded.");
            return;
        }

        // [name, description, category, frequency, priority, due offset (days from today), manual status|null]
        $rows = [
            ['Fire safety audit', 'Annual inspection of extinguishers, alarms, and evacuation routes by a certified fire officer.', 'Fire & Safety', 'Yearly', 'Critical', -12, null],
            ['Fire drill - term 2', 'Whole-school evacuation drill with timing log and teacher sign-off.', 'Fire & Safety', 'Quarterly', 'High', 6, null],
            ['Fire extinguisher refill check', 'Verify expiry and pressure gauge of every extinguisher on campus.', 'Fire & Safety', 'Half-Yearly', 'High', 38, null],
            ['Staff police verification', 'Police clearance certificates collected and filed for all teaching and support staff.', 'HR & Staff', 'Yearly', 'Critical', -30, null],
            ['Teacher qualification records review', 'Confirm degree, B.Ed and CTET documents are on file and valid.', 'HR & Staff', 'Yearly', 'Medium', 75, null],
            ['Drinking water quality test', 'Lab test of drinking water for potability; report attached.', 'Health & Hygiene', 'Quarterly', 'High', 3, null],
            ['Canteen food safety inspection', 'FSSAI hygiene checklist for the canteen kitchen and storage.', 'Health & Hygiene', 'Monthly', 'Medium', 15, null],
            ['Pest control service', 'Scheduled pest-control treatment of classrooms, labs and canteen.', 'Health & Hygiene', 'Quarterly', 'Low', -4, null],
            ['Building structural safety certificate', 'Structural engineer certificate for all buildings and the terrace railing.', 'Building & Infrastructure', 'Yearly', 'Critical', 120, null],
            ['Electrical wiring and earthing audit', 'Licensed electrician inspection of panels, wiring and earthing pits.', 'Building & Infrastructure', 'Yearly', 'High', -20, 'In Progress'],
            ['School bus fitness certificates', 'RTO fitness and permit renewal for every school vehicle.', 'Transport', 'Yearly', 'Critical', 22, null],
            ['Bus attendant and driver verification', 'Licence validity, medical fitness and police verification of drivers and attendants.', 'Transport', 'Half-Yearly', 'High', -9, 'Pending Verification'],
            ['GPS and speed governor check', 'Confirm GPS trackers and speed governors work on all buses.', 'Transport', 'Quarterly', 'Medium', 45, null],
            ['Student data privacy review', 'Review access controls and consent records for student personal data.', 'IT & Data Privacy', 'Half-Yearly', 'High', 28, null],
            ['CCTV coverage and retention check', 'Check camera coverage, uptime and 30-day footage retention.', 'IT & Data Privacy', 'Monthly', 'Medium', 9, null],
            ['Statutory audit and ITR filing', 'Annual accounts audit and income-tax return filing for the trust.', 'Finance', 'Yearly', 'Critical', 90, null],
            ['TDS return - quarterly', 'Quarterly TDS return filing and challan reconciliation.', 'Finance', 'Quarterly', 'High', -2, 'Completed'],
            ['Affiliation renewal documents', 'Board affiliation renewal: land, building, staff and enrolment returns.', 'Government & Regulatory', 'Yearly', 'Critical', 150, null],
            ['UDISE+ data submission', 'Annual UDISE+ school data submission and verification.', 'Government & Regulatory', 'Yearly', 'Medium', 60, null],
            ['POCSO committee meeting', 'Quarterly child-protection committee meeting with minutes and attendance.', 'Child Safety', 'Quarterly', 'Critical', 12, null],
            ['Child safety policy acknowledgement', 'All staff acknowledge the child protection and anti-bullying policy.', 'Child Safety', 'Yearly', 'High', -45, 'Completed'],
            ['Waste segregation and disposal log', 'Review daily waste segregation and the monthly disposal vendor log.', 'Environment', 'Monthly', 'Low', 18, null],
            ['Emergency evacuation plan review', 'Update and republish floor evacuation maps and assembly points.', 'Emergency Management', 'Yearly', 'High', 52, null],
            ['First aid training refresher', 'Refresher training for the first-aid responder team.', 'Emergency Management', 'Half-Yearly', 'Medium', -6, null],
            ['Lift and escalator safety inspection', 'Annual inspection and licence renewal for lifts by the state electrical inspectorate.', 'Building & Infrastructure', 'Yearly', 'High', 34, null],
            ['Rainwater harvesting system check', 'Inspect storage tanks, filters and recharge pits before monsoon.', 'Environment', 'Yearly', 'Low', 95, null],
            ['Playground equipment safety check', 'Inspect swings, slides and fixtures for wear, rust and loose bolts.', 'Building & Infrastructure', 'Quarterly', 'Medium', 7, null],
            ['Laboratory chemical storage audit', 'Check labelling, storage and disposal register for science lab chemicals.', 'Health & Hygiene', 'Half-Yearly', 'High', -15, 'In Progress'],
            ['Medical room stock and doctor visit log', 'Verify medicines stock, expiry dates and the visiting doctor register.', 'Health & Hygiene', 'Monthly', 'Medium', 4, null],
            ['Student health check-up camp', 'Annual health screening for all students with parent reports.', 'Health & Hygiene', 'Yearly', 'Medium', 110, null],
            ['Anti-bullying committee review', 'Review reported incidents and actions with the anti-bullying committee.', 'Child Safety', 'Quarterly', 'High', 25, null],
            ['Visitor management log audit', 'Audit visitor entry logs and ID checks at the main gate.', 'Child Safety', 'Monthly', 'Medium', -3, null],
            ['Staff child-protection training', 'Mandatory child-protection and safe-touch training for all staff.', 'Child Safety', 'Yearly', 'Critical', 68, null],
            ['Cyber safety awareness session', 'Cyber safety and responsible internet use session for students.', 'IT & Data Privacy', 'Half-Yearly', 'Low', 41, null],
            ['Backup and recovery test', 'Restore test of student records and fee data from backup.', 'IT & Data Privacy', 'Quarterly', 'High', -8, null],
            ['Antivirus and patch compliance', 'Confirm all lab and office machines are patched and protected.', 'IT & Data Privacy', 'Monthly', 'Low', 14, null],
            ['Network firewall rules review', 'Review firewall and content-filter rules for the student network.', 'IT & Data Privacy', 'Half-Yearly', 'Medium', 83, null],
            ['GST return filing', 'Monthly GST return for taxable services and canteen sales.', 'Finance', 'Monthly', 'High', 11, null],
            ['Provident fund and ESI remittance', 'Monthly PF and ESI challan payment for eligible staff.', 'Finance', 'Monthly', 'Critical', -1, 'Completed'],
            ['Fee structure approval filing', 'File the approved fee structure with the education department.', 'Finance', 'Yearly', 'High', 130, null],
            ['Scholarship and RTE quota report', 'Report RTE admissions and scholarship disbursement to the authority.', 'Government & Regulatory', 'Yearly', 'Medium', 72, null],
            ['Land use and fire NOC renewal', 'Renew the fire department NOC and land-use certificate.', 'Government & Regulatory', 'Yearly', 'Critical', 200, null],
            ['School management committee meeting', 'Quarterly SMC meeting with minutes sent to the block office.', 'Government & Regulatory', 'Quarterly', 'Medium', 20, null],
            ['Teacher-student ratio declaration', 'Declare section-wise teacher-student ratio to the board.', 'Government & Regulatory', 'Yearly', 'Medium', -25, 'Pending Verification'],
            ['Employment contracts renewal', 'Renew contracts of contractual and visiting faculty.', 'HR & Staff', 'Yearly', 'Medium', 58, null],
            ['Staff health insurance renewal', 'Renew group health insurance and circulate the policy copy.', 'HR & Staff', 'Yearly', 'High', 47, null],
            ['Internal complaints committee report', 'Annual report of the prevention-of-harassment committee.', 'HR & Staff', 'Yearly', 'High', -18, null],
            ['Pollution under control certificates', 'PUC certificates for school vehicles and the generator.', 'Transport', 'Half-Yearly', 'Medium', 16, null],
            ['Bus route safety survey', 'Survey of pickup points, road hazards and stop timings.', 'Transport', 'Half-Yearly', 'Low', 88, null],
            ['Generator and fuel storage inspection', 'Inspect the generator set and diesel storage for leaks and permits.', 'Environment', 'Half-Yearly', 'Medium', 30, null],
            ['Tree plantation and green audit', 'Annual green audit with plantation record.', 'Environment', 'Yearly', 'Low', 160, 'Not Applicable'],
            ['Disaster management mock drill', 'Earthquake and flood mock drill with the local disaster cell.', 'Emergency Management', 'Yearly', 'High', 5, null],
            ['Emergency contact list update', 'Refresh student emergency contacts and the staff call tree.', 'Emergency Management', 'Quarterly', 'Medium', -11, null],
            ['Gas pipeline and canteen LPG inspection', 'Inspect LPG connections and safety valves in the canteen.', 'Fire & Safety', 'Quarterly', 'High', 2, null],
            ['Smoke detector and sprinkler test', 'Functional test of smoke detectors and sprinkler heads in all blocks.', 'Fire & Safety', 'Quarterly', 'High', 33, null],
        ];

        $created = 0;
        foreach ($rows as $i => [$name, $desc, $category, $frequency, $priority, $offset, $manual]) {
            $exists = DB::table('org_compliance_library')
                ->where('sub_institute_id', $tenant)
                ->where('name', $name)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists || !isset($categories[$category])) {
                continue;
            }

            $department = $departments[$i % $departments->count()];
            $due = $today->copy()->addDays($offset);

            $record = new ComplianceLibraryRecord([
                'name' => $name,
                'description' => $desc,
                'category_id' => $categories[$category],
                'department' => $department->department,
                'department_id' => $department->id,
                'assigned_to' => $assignees[$i % $assignees->count()],
                'duedate' => $due,
                'frequency' => $frequency,
                'priority' => $priority,
                'status' => $manual ?? ComplianceLibraryRecord::STATUS_UPCOMING,
                'sub_institute_id' => $tenant,
                'created_by' => $assignees[0],
            ]);
            $record->next_due_date = $record->calculateNextDueDate();
            if ($manual === 'Completed') {
                $record->completed_at = $due->copy()->subDays(1);
                $record->completed_by = $assignees[$i % $assignees->count()];
            }
            // Persist the time-based status (Upcoming / Due Soon / Overdue) like the app does.
            $record->status = $record->deriveStatus();
            $record->save();
            $created++;
        }

        $this->command?->info("Compliance sample data: {$created} created, " . (count($rows) - $created) . " skipped (tenant {$tenant}).");
    }
}
