<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ONetDataCategory extends Model
{
    use HasFactory;

    public function sub_categories()
    {
        return $this->hasMany(ONetDataSubCategory::class,'o_net_data_category_id');
    }
}
