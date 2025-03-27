<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomModuleTable extends Model
{
    use HasFactory;

    public function columns() {
        return $this->hasMany(CustomModuleTableColumn::class,'table_id');
    }

    public function whereColumns() {
        return $this->hasMany(CustomModuleTableColumn::class,'table_id');
    }
}
