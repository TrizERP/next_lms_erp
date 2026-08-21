<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from `App\Models\talent\talent_jobposting` (hp_erp). The source
 * model had no `$fillable` at all (mass-assignment wasn't used — the
 * controller set attributes one by one), so this uses guarded=[] to keep the
 * assignment style available to the ported controller without silently
 * dropping columns.
 */
class TalentJobPosting extends Model
{
    use SoftDeletes;

    protected $table = 'talent_job_postings';

    protected $guarded = ['id'];
}
