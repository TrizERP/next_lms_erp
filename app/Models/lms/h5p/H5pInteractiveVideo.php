<?php

namespace App\Models\lms\h5p;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class H5pInteractiveVideo extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'h5p_interactive_video';
    protected $guarded = [];
    
    public function interactions()
    {
        return $this->hasMany(H5pVideoInteraction::class, 'video_id');
    }
}
