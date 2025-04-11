<?php

namespace App\Models\custom_module\customMapModule;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class donationModel extends Model
{
    use HasFactory,SoftDeletes;
    protected $table="donation_collection";
    protected $softDelete = true;
}
