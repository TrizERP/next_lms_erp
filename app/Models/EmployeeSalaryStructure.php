<?php

namespace App\Models;

use App\Models\user\tbluserModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryStructure extends Model
{
    use HasFactory;
    public $fillable =['employee_id','employee_salary_data','year','sub_institute_id'];

    public function getUser(){
        return $this->hasOne(tbluserModel::class,'id','employee_id');
    }
}
