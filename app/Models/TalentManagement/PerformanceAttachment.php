<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceAttachment`.
 *
 * A file on a review - the "Attachments (n)" tab and the sidebar Docs tab.
 */
class PerformanceAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_attachments';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'review_id'        => 'integer',
        'cycle_id'         => 'integer',
        'user_id'          => 'integer',
        'file_size'        => 'integer',
        'uploaded_by'      => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];
}
