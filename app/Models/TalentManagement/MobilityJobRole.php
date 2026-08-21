<?php

namespace App\Models\TalentManagement;

use Illuminate\Database\Eloquent\Model;

/**
 * Minimal read model for the `s_user_jobrole` table, added for this port.
 *
 * The source (G2G) `MobilitySuccessionPlan::criticalJobrole()` relation
 * targeted `App\Models\libraries\userJobroleModel` — that model does not
 * exist under this project's `App\Models` tree (confirmed: no
 * `app/Models/libraries` directory here), so this small stand-in was added
 * purely to keep the `->with('criticalJobrole')` eager-load in
 * `MobilitySuccessionController@index` working and the response shape
 * unchanged. It is intentionally not a full port of the G2G model — the
 * controllers only ever read `jobrole`/`id` off this table directly via
 * `DB::table('s_user_jobrole')`.
 */
class MobilityJobRole extends Model
{
    protected $table = 's_user_jobrole';

    protected $guarded = [];
}
