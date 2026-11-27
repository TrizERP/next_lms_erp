<?php

namespace App\Services\PAL\LabSpec;

use Illuminate\Support\Facades\DB;

/**
 * What a chapter is about, for generating a lab for it and for checking that a lab's concepts belong to it.
 * No student is involved: this is the chapter as the curriculum defines it.
 */
class LabChapterContext
{
    /**
     * @return array{chapter:string,subject:?string,standard:?string,summary:string,key_concepts:array<int,array{name:string,description:string}>,concepts:array<int,array{name:string,description:string}>}|null
     */
    public static function for(int $chapterId, int $subInstituteId): ?array
    {
        $chapter = DB::table('chapter_master')->where('id', $chapterId)->first();
        if ($chapter === null) {
            return null;
        }
        $subject = ! empty($chapter->subject_id) ? DB::table('subject')->where('id', $chapter->subject_id)->value('subject_name') : null;
        $standard = ! empty($chapter->standard_id) ? DB::table('standard')->where('id', $chapter->standard_id)->value('name') : null;

        $key = [];
        foreach ((array) json_decode((string) ($chapter->key_concepts ?? ''), true) as $k) {
            if (is_array($k) && ! empty($k['name'])) {
                $key[] = ['name' => mb_substr((string) $k['name'], 0, 120), 'description' => mb_substr((string) ($k['description'] ?? ''), 0, 260)];
            }
        }
        $concepts = DB::table('lms_concept')->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', array_unique([$subInstituteId, 0]))->orderBy('id')->limit(40)
            ->get(['name', 'description', 'definition'])->map(fn ($c) => [
                'name' => (string) $c->name,
                'description' => mb_substr(trim((string) ($c->description ?: $c->definition)), 0, 220),
            ])->all();

        return [
            'chapter' => (string) ($chapter->chapter_name ?? ''),
            'subject' => $subject,
            'standard' => $standard,
            'summary' => mb_substr(trim((string) ($chapter->chapter_desc ?? '')), 0, 1500),
            'key_concepts' => array_slice($key, 0, 12),
            'concepts' => $concepts,
        ];
    }

    /** @return array<int,string> */
    public static function conceptNames(int $chapterId, int $subInstituteId): array
    {
        return array_column(self::for($chapterId, $subInstituteId)['concepts'] ?? [], 'name');
    }
}
