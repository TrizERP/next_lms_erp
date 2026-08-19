<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * A queued celebration (§8.1).
 *
 * Rows exist only where a real record was broken, a real badge earned or a real
 * milestone crossed. `level` carries the celebration hierarchy so the client
 * knows whether this is an inline tick or a full-screen moment — and the
 * "one genuine celebration per session" budget is applied on read, not by the
 * UI deciding what to suppress.
 */
class GamificationNotification extends Model
{
    protected $table = 'pal_gamification_notifications';

    protected $fillable = [
        'learner_id',
        'type',
        'level',
        'title',
        'body',
        'context',
        'read_at',
    ];

    protected $casts = [
        'context' => 'array',
        'read_at' => 'datetime',
    ];

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
