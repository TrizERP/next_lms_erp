<?php

namespace App\Models\library;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class itemStatus extends Model
{
    use HasFactory,SoftDeletes;
    protected $table="mst_item_status";
    protected $softDelete = true;
}
