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

    /**
     * `created_at` is fillable deliberately, even though $timestamps is false.
     *
     * The column carries DEFAULT current_timestamp(), so a row that omits it is
     * stamped by the DATABASE's clock — which on this estate runs 2.5 hours
     * behind the application's (PHP is Asia/Kolkata; the MariaDB host is not).
     * Every other time in the engine, taught_at and next_review_at included, is
     * written by PHP, and practiceComplete() compares this column directly
     * against taught_at. Mixing the two clocks made that comparison always
     * false, so the practice phase could never end.
     *
     * Writers must therefore pass created_at explicitly. See logResponse().
     */
    protected $fillable = [
        'student_id', 'concept_id', 'node_id', 'sub_institute_id',
        'question_id', 'correct', 'hint_used', 'mode', 'created_at',
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

    public function scopeForNode($query, int $nodeId)
    {
        return $query->where('node_id', $nodeId);
    }
}
