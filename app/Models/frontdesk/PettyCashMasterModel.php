<?php

namespace App\Models\frontdesk;

use Illuminate\Database\Eloquent\Model;

class PettyCashMasterModel extends Model
{
    protected $table = "petty_cash_master";

	public $timestamps = false;

    protected $fillable = [
        'id',
        'sub_institute_id',
        'title'
    ];
}
