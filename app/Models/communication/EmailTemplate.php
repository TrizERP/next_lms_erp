<?php

namespace App\Models\communication;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'email_templates';

    protected $fillable = [
        'sub_institute_id',
        'module',
        'event_key',
        'name',
        'subject',
        'html_content',
        'attach_as_pdf',
        'is_letter',
        'pdf_template_id',
        'pdf_filename',
        'standard_ids',
        'status_code',
        'remarks',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Standard ids this template is restricted to. Empty = applies to all.
     *
     * @return array<int,int>
     */
    public function standardIdList(): array
    {
        if (empty($this->standard_ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $this->standard_ids))));
    }
}
