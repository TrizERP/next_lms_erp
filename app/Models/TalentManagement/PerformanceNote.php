<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ported from G2G's `App\Models\Performance\PerformanceNote`.
 *
 * Free text on a review. note_type discriminates the screen's two tabs:
 * 'comment' -> "Comments (n)", 'note' -> "Notes (n)".
 */
class PerformanceNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 's_performance_notes';
    protected $guarded = ['id'];

    protected $casts = [
        'sub_institute_id' => 'integer',
        'review_id'        => 'integer',
        'cycle_id'         => 'integer',
        'user_id'          => 'integer',
        'author_id'        => 'integer',
        'created_by'       => 'integer',
        'updated_by'       => 'integer',
    ];
}
