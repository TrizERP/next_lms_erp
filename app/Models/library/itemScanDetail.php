<?php

namespace App\Models\library;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class itemScanDetail extends Model
{
    use HasFactory,SoftDeletes;
    protected $table="item_scan_details";
    protected $softDelete = true;
}
