<?php

namespace App\Models\settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class mastreFieldsTable extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = "master_fields_table";
}
