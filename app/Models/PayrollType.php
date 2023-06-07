<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollType extends Model
{
    use HasFactory;

    public $fillable=['payroll_type','payroll_name','amount_type','status'];
}
