<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One logged touchpoint with a student, parent or staff member — a call, meeting, note or
 * follow-up. Backs the Interactions module's base screen and its AI Stack alike.
 */
class InteractionLog extends Model
{
    protected $table = 'interaction_logs';

    protected $fillable = [
        'sub_institute_id',
        'staff_id',
        'related_type',
        'related_id',
        'interaction_type',
        'subject',
        'notes',
        'occurred_at',
        'follow_up_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'follow_up_date' => 'date',
    ];
}
