<?php

namespace App\Models\school_setup;

use Illuminate\Database\Eloquent\Model;

class SchoolModel extends Model {

    protected $table = "school_setup"; 
    protected $fillable = [
        'SchoolName',
        'ShortCode',
        'ContactPerson',
        'Mobile',
        'Email',
        'ReceiptHeader',
        'ReceiptAddress',
        'FeeEmail',
        'ReceiptContact',
        'SortOrder',
        'Logo',
    ];

}
