<?php

namespace App\Models\lms;

use App\Models\school_setup\standardModel;
use App\Models\school_setup\subjectModel;
use Illuminate\Database\Eloquent\Model;

class LmsLessonPlan extends Model
{
    protected $table = 'lms_lesson_plan';
    public $timestamps = false;

    public function standard()
    {
        return $this->belongsTo(standardModel::class, 'standard_id', 'id');
    }

    public function subject()
    {
        return $this->belongsTo(subjectModel::class, 'subject_id', 'id');
    }

    public function chapter()
    {
        return $this->belongsTo(chapterModel::class, 'chapter_id', 'id');
    }

    public function topic()
    {
        return $this->belongsTo(topicModel::class, 'topic_id', 'id');
    }

    public function lessonDays()
    {
        return $this->hasMany(LmsLessonPlanDayWise::class, 'lpid', 'id');
    }
}