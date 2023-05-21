<?php

namespace App\Imports;

use App\Models\HrmsEmpLeave;
use App\Models\HrmsLeaveType;
use App\Models\user\tbluserModel;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LeaveImport implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $user_id = tbluserModel::
            where('first_name', explode(' ', $row['employee_name'])[0] ?? '')
            ->orwhere('last_name', explode(' ', $row['employee_name'])[1] ?? '')
            ->first()->id ?? '';
        $leave_type_id = HrmsLeaveType::where('leave_type', 'like', '%' . $row['type'] . '%')->first()->id ?? '';
        $leave_Date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['leave_date']);
        $date = Carbon::parse($leave_Date);
        $daysToAdd = $row['leave'];
        $to_date = $date->addDays($daysToAdd);
        if ($user_id && $leave_type_id) {
            return HrmsEmpLeave::updateOrCreate([
                'user_id' => $user_id,
                'from_date' => $leave_Date->format('Y-m-d'),
                'to_date' => $to_date->format('Y-m-d'),
            ],
                [
                    'leave_type_id' => $leave_type_id,
                    'day_type' => $row['leave'] >= 1 ? 'full' : 'half',
                    'slot' => str_contains($row['slot'], 'first') ? 'first_half' : 'second_half',
                    'comment' => $row['reason'],
                ]);
        }
    }
}
