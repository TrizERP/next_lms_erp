<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmsLeaveWorkflowSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hrms_leave_workflow_settings';
    protected $guarded = ['id'];

    protected $casts = [
        'reporting_manager_enabled' => 'boolean',
        'department_head_enabled'   => 'boolean',
        'hr_enabled'                => 'boolean',
        'multi_level_enabled'       => 'boolean',
        'escalation_enabled'        => 'boolean',
        'multi_level_count'         => 'integer',
        'escalation_time'           => 'integer',
    ];

    /** Values used when an institute has never saved a workflow. */
    public static function defaults(): array
    {
        return [
            'reporting_manager_enabled' => true,
            'department_head_enabled'   => true,
            'hr_enabled'                => false,
            'multi_level_enabled'       => false,
            'multi_level_count'         => 2,
            'escalation_enabled'        => true,
            'escalation_time'           => 24,
            'escalation_unit'           => 'hours',
            'escalate_to'               => 'hr',
        ];
    }
}
