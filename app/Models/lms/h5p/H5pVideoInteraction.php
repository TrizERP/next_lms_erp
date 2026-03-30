<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class H5pVideoInteraction extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'h5p_video_interactions';
    protected $guarded = [];
}
