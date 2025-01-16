<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportDynamic extends Model {

    protected $table = "report_dynamic";
    protected $fillable = [
        'id',
        'report_name',
        'fields',
        'created_at',
        'updated_at'
    ];

}
