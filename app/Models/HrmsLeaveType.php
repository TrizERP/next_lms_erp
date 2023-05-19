<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrmsLeaveType extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = ['id'];

    public function setLeaveTypeId()
    {
        $last = HrmsLeaveType::latest()->first()->id ?? null;
        return $last ? 'LTY' . str_pad($last + 1, 3, '0', STR_PAD_LEFT) : 'LTY001';
    }
}
