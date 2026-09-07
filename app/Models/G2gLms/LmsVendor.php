<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Training vendor. Controllers (`App\Http\Controllers\G2gLms\PartnerController`)
 * query this table via the query builder directly, matching hp_erp source
 * style; this model exists for callers elsewhere in the app that want an
 * Eloquent relation into `lms_vendors`.
 */
class LmsVendor extends Model
{
    use SoftDeletes;

    protected $table = 'lms_vendors';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'status'           => 'boolean',
        'contract_start'   => 'date',
        'contract_end'     => 'date',
        'contract_value'   => 'decimal:2',
    ];

    public function trainers()
    {
        return $this->hasMany(LmsTrainer::class, 'vendor_id');
    }
}
