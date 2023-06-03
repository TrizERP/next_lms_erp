<?php

namespace Database\Seeders;

use App\Models\HrmsDepartment;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $data = ["Muljibhai Mehta International School",
            "Non-Teaching  Department",
            "Teaching Department",
            "Vice Principal Other",
            "Principal Other",
            "General Manager Other",
            "Accounts Department",
            "Secretorial Department",
            "Office Admin Department",
            "Admin &amp; Maintenance Department",
            "Laboratory Department",
            "Library Department",
            "IT &amp; Computers Department",
            "Swimming Department",
            "Martial Art Department",
            "Academic Division",
            "Pre-Primary Teacher Other",
            "Primary Teacher Other",
            "Secondary Teacher Other",
            "Senior Secondary Teachers Other",
            "Visiting Faculty Teachers",
            "Art/Craft Department",
            "Physical Training Education Other",
            "Examination Department",
            "Nurse Department",
            "Vehicle Department",
            "Music &amp; Dance Department",
            "House Keeping Department",
            "Pre-Primary Other",
            "Accounts &amp; Finance Department",
            "Trustee Department",
            "Accountant Team",
            "Accounts Assistant Team",
            "Exam Attendant Team",
            "Computer Lab Assistant Team",
            "Receptionist Team",
            "Labarotary Attendant Department",
            "Office Attendant Team",
            "Admin Assistant Team",
            "Liabrary Assistant Team",
            "Driver Team",
            "Personal Assistant Team",
            "Gardener  Team",
            "Labarotary Assistant  Team",
            "Swimming Pool Incharge Team",
            "Liabrarian  Team",
            "Nurse Team",
            "Counselor Team"];
        foreach ($data as $key => $value) {
            HrmsDepartment::updateOrCreate(['department' => $value]);
        }

    }
}
