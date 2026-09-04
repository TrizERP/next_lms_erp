<?php

namespace App\Models\Eso;

use Illuminate\Database\Eloquent\Model;

/**
 * Adaptive Learning Engine — eso_response_log.
 *
 * Append-only per-response history — one row per scored diagnostic item,
 * practice attempt, or retrieval-check item. Written once via
 * EsoPolicyService::logResponse(); never updated after creation.
 */
class ResponseLog extends Model
{
    protected $table = 'eso_response_log';

    public $timestamps = false;

    protected $fillable = [
        'student_id', 'concept_id', 'node_id', 'sub_institute_id',
        'question_id', 'correct', 'hint_used', 'mode',
    ];

    protected $casts = [
        'correct' => 'boolean',
        'hint_used' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForConcept($query, int $conceptId)
    {
        return $query->where('concept_id', $conceptId);
    }
}
