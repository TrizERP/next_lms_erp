<?php

namespace App\Models\G2gLms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LmsIntegration extends Model
{
    use SoftDeletes;

    protected $table = 'lms_integrations';

    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'connected_at'     => 'datetime',
        'last_sync_at'     => 'datetime',
    ];
}
