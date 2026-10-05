<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\user\tbluserModel;

class DocumentHistory extends Model
{
    use HasFactory;

    protected $table = 'document_history';
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'entry_type',
        'action',
        'user_id',
        'ip_address',
        'version_number',
        'storage_path',
        'checksum_sha256',
        'size',
        'change_note',
        'details',
        'created_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'size' => 'integer',
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function document()
    {
        return $this->belongsTo(DocumentMaster::class, 'document_id');
    }

    public function user()
    {
        return $this->belongsTo(tbluserModel::class, 'user_id');
    }
}
