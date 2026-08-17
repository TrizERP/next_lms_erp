<?php

namespace App\Models\PAL\Gamification;

use Illuminate\Database\Eloquent\Model;

/**
 * Class-level Challenge Mode availability (§6.1).
 *
 * A teacher can switch Challenge Mode off for a whole class — the document
 * names exam periods explicitly. The most specific matching row wins:
 * division → standard → institute.
 */
class ChallengeModeSetting extends Model
{
    protected $table = 'pal_challenge_mode_settings';

    protected $fillable = [
        'sub_institute_id',
        'syear',
        'standard_id',
        'division_id',
        'enabled',
        'updated_by',
        'disabled_reason',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
