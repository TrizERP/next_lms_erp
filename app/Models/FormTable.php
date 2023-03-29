<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormTable extends Model
{
    protected $table = 'form_builder';
      protected $fillable = [
          'id',
          'form_name',
          'form_xml',
          'form_json',
          'form_active',
          'created_at',
          'updated_at'
      ];
    public $timestamps = false;

}
