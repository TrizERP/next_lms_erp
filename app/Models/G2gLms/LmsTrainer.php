<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsTrainer extends Model
{
    use SoftDeletes;

    protected $table = 'lms_trainers';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'user_id'          => 'integer',
        'vendor_id'        => 'integer',
        'status'           => 'boolean',
        'hourly_rate'      => 'decimal:2',
    ];

    public function vendor()
    {
        return $this->belongsTo(LmsVendor::class, 'vendor_id');
    }
}
