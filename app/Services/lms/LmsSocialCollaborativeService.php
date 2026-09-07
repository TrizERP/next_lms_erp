<?php

namespace App\Services\lms;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * LMS Engagement -> Social & Collaborative business logic.
 *
 * Re-implementation (NOT a wrapper) of the legacy web module, whose behaviour
 * is spread over three controllers that the K12 API replaces as one feature:
 *
 *   lms\lmsSocialCollabrotiveController        read the doubt feed + conversations
 *   lms\lmsDoubtController@store               create a doubt (+ attachment)
 *   lms\lmsDoubtConversationController@store   post a comment on a doubt
 *
 * Tables: `lms_doubt` (the post) and `lms_doubt_conversation` (its comments).
 * Both are institute + academic-year scoped. An author is EITHER a student
 * (`tblstudent`, shown with class/division) or a staff member (`tbluser`) - the
 * legacy code expressed that as a UNION of two nearly identical queries.
 *
 * Rules preserved from the legacy module
 *   - every read and write is scoped to the caller's institute and syear;
 *   - a STUDENT sees public doubts plus doubts raised inside their own class;
 *   - staff see every doubt in the institute for that year;
 *   - a doubt carries subject/chapter/topic, a title composed as
 *     "Subject / Chapter / Topic", an HTML description, an optional attachment
 *     (DigitalOcean Spaces, `public/lms_doubts/`) and public|private visibility;
 *   - both students and staff may comment; there is no edit or delete path.
 *
 * Deliberate corrections (defects in the legacy version, documented in
 * LMS_LEADERBOARD_SOCIAL_COLLABORATIVE_MIGRATION.md):
 *   1. the student branch built `->where(institute, syear)->where(visibility)
 *      ->orWhere(standard)`, and Laravel's flat precedence let that trailing
 *      orWhere escape the tenant scope entirely - other schools' doubts leaked
 *      into the feed. The visibility test is now a grouped sub-clause nested
 *      inside the institute/year scope.
 *   2. the conversation join compared `se.standard_id` to the STRING
 *      'l.standard_id' - a column that does not exist on
 *      lms_doubt_conversation - so the student half of the union matched
 *      nothing and student comments were invisible.
 *   3. the feed INNER JOINed `tblstudent`, so any doubt raised by a staff
 *      member vanished from the list.
 *   4. the conversation was fetched with one query per doubt (N+1), and the
 *      enrollment joins could duplicate a row for a learner with more than one
 *      enrollment in a year. Authors are now resolved in bulk, per page, from
 *      both tables - no per-row queries and no join fan-out.
 */
class LmsSocialCollaborativeService
{
    private const ATTACHMENT_DIR = 'public/lms_doubts/';

    /**
     * The doubt feed, paginated.
     *
     * @param  array  $ctx     ['sub_institute_id','syear','user_id','is_student']
     * @param  array  $filters ['search','subject_id','chapter_id','visibility','mine']
     */
    public function feed(array $ctx, array $filters, int $page, int $perPage): array
    {
        $query = $this->visibleDoubts($ctx);

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }
        if (! empty($filters['chapter_id'])) {
            $query->where('chapter_id', $filters['chapter_id']);
        }
        if (! empty($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }
        if (! empty($filters['mine'])) {
            $query->where('user_id', (int) $ctx['user_id']);
        }
        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        $total   = (clone $query)->count();
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get($this->doubtColumns())
            ->all();

        $authors  = $this->authors($ctx, array_map(static fn ($row) => (int) $row->user_id, $rows));
        $counts   = $this->commentCounts(array_map(static fn ($row) => (int) $row->id, $rows));

        $items = array_map(function ($row) use ($authors, $counts) {
            $doubt                  = $this->presentDoubt($row, $authors);
            $doubt['comment_count'] = $counts[(int) $row->id] ?? 0;

            return $doubt;
        }, $rows);

        return [
            'items' => $items,
            'meta'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
            ],
        ];
    }

    /** A single doubt with its full conversation, or null when not visible. */
    public function show(array $ctx, int $doubtId): ?array
    {
        $row = $this->visibleDoubts($ctx)->where('id', $doubtId)->first($this->doubtColumns());
        if (! $row) {
            return null;
        }

        $doubt                  = $this->presentDoubt($row, $this->authors($ctx, [(int) $row->user_id]));
        $doubt['comments']      = $this->comments($ctx, $doubtId);
        $doubt['comment_count'] = count($doubt['comments']);

        return $doubt;
    }

    /** Whether the caller may read (and therefore comment on) a doubt. */
    public function canAccess(array $ctx, int $doubtId): bool
    {
        return $this->visibleDoubts($ctx)->where('id', $doubtId)->exists();
    }

    /**
     * Create a doubt. Mirrors lmsDoubtController@store: the attachment goes to
     * DigitalOcean Spaces under public/lms_doubts/ with the same
     * `lms_<Y-m-d_h-i-s>.<ext>` naming, and identity comes from the caller's
     * token, never from the request body.
     */
    public function createDoubt(array $ctx, array $input, ?UploadedFile $file = null): array
    {
        $fileName = '';
        if ($file) {
            $fileName = 'lms_' . date('Y-m-d_h-i-s') . '.' . $file->getClientOriginalExtension();
            Storage::disk('digitalocean')->putFileAs(self::ATTACHMENT_DIR, $file, $fileName, 'public');
        }

        $id = DB::table('lms_doubt')->insertGetId([
            'subject_id'       => $input['subject_id'] ?? null,
            'chapter_id'       => $input['chapter_id'] ?? null,
            'topic_id'         => $input['topic_id'] ?? null,
            'title'            => $input['title'],
            'description'      => $input['description'] ?? '',
            'visibility'       => $input['visibility'],
            'file_name'        => $fileName,
            'user_id'          => (int) $ctx['user_id'],
            'user_profile_id'  => $ctx['user_profile_id'] ?? null,
            'sub_institute_id' => (int) $ctx['sub_institute_id'],
            'syear'            => (int) $ctx['syear'],
            'created_at'       => now(),
        ]);

        return $this->show($ctx, (int) $id) ?? ['id' => (int) $id];
    }

    /** Post a comment on a doubt (lmsDoubtConversationController@store). */
    public function addComment(array $ctx, int $doubtId, string $message): array
    {
        $id = DB::table('lms_doubt_conversation')->insertGetId([
            'doubt_id'         => $doubtId,
            'message'          => $message,
            'user_id'          => (int) $ctx['user_id'],
            'user_profile_id'  => $ctx['user_profile_id'] ?? null,
            'syear'            => (int) $ctx['syear'],
            'sub_institute_id' => (int) $ctx['sub_institute_id'],
            'created_at'       => now(),
        ]);

        foreach ($this->comments($ctx, $doubtId) as $comment) {
            if ($comment['id'] === (int) $id) {
                return $comment;
            }
        }

        return ['id' => (int) $id, 'message' => $message];
    }

    /* ------------------------------------------------------------------ */
    /* Compose-form lookups (legacy: add_doubt Blade + its two AJAX routes) */
    /* ------------------------------------------------------------------ */

    /** Subjects mapped to a standard (`sub_std_map`), as the legacy form did. */
    public function subjects(array $ctx, ?int $standardId = null): array
    {
        $subInstituteId = (int) $ctx['sub_institute_id'];

        if (! $standardId && ($ctx['is_student'] ?? false)) {
            $standardId = $this->learnerStandard($ctx);
        }

        return DB::table('sub_std_map')
            ->where('sub_institute_id', $subInstituteId)
            ->when($standardId, fn ($q) => $q->where('standard_id', $standardId))
            ->orderBy('sort_order')
            ->get(['subject_id', 'standard_id', 'display_name'])
            ->map(static fn ($row) => [
                'subject_id'  => (int) $row->subject_id,
                'standard_id' => (int) $row->standard_id,
                'name'        => (string) $row->display_name,
            ])
            ->unique('subject_id')
            ->values()
            ->all();
    }

    /**
     * Chapters for a subject. Content-sharing rule copied from the legacy
     * ajax_LMS_SubjectwiseChapter: an LMS-enabled institute also sees the
     * shared institute-1 catalogue.
     */
    public function chapters(array $ctx, int $subjectId, ?int $standardId = null): array
    {
        return DB::table('chapter_master')
            ->where('subject_id', $subjectId)
            ->when($standardId, fn ($q) => $q->where('standard_id', $standardId))
            ->whereIn('sub_institute_id', $this->contentInstitutes((int) $ctx['sub_institute_id']))
            ->orderBy('sort_order')
            ->get(['id', 'chapter_name'])
            ->map(static fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->chapter_name])
            ->all();
    }

    /** Topics for a chapter (legacy ajax_LMS_ChapterwiseTopic). */
    public function topics(array $ctx, int $chapterId): array
    {
        return DB::table('topic_master')
            ->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', $this->contentInstitutes((int) $ctx['sub_institute_id']))
            ->orderBy('topic_sort_order')
            ->get(['id', 'name'])
            ->map(static fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** The caller's own standard for the active year (students only). */
    private function learnerStandard(array $ctx)
    {
        return DB::table('tblstudent_enrollment')
            ->where('student_id', (int) $ctx['user_id'])
            ->where('sub_institute_id', (int) $ctx['sub_institute_id'])
            ->where('syear', (int) $ctx['syear'])
            ->orderByDesc('id')
            ->value('standard_id');
    }

    /** @return array<int,int> institute ids whose content the caller may read */
    private function contentInstitutes(int $subInstituteId): array
    {
        $isLms = DB::table('school_setup')->where('Id', $subInstituteId)->value('is_Lms');

        return $isLms === 'Y' ? [1, $subInstituteId] : [$subInstituteId];
    }

    /**
     * Base query for every doubt the caller may see. Deliberately join-free:
     * author metadata is resolved per page in authors(), which keeps the
     * visibility test unambiguous and rules out join fan-out (correction 4).
     */
    private function visibleDoubts(array $ctx): \Illuminate\Database\Query\Builder
    {
        $subInstituteId = (int) $ctx['sub_institute_id'];
        $syear          = (int) $ctx['syear'];
        $userId         = (int) $ctx['user_id'];

        $query = DB::table('lms_doubt')
            ->where('sub_institute_id', $subInstituteId)
            ->where('syear', $syear);

        if ($ctx['is_student'] ?? false) {
            $standardId = $this->learnerStandard($ctx);

            // Grouped so the tenant scope above always holds (correction 1).
            $query->where(function ($q) use ($standardId, $userId, $subInstituteId, $syear) {
                $q->where('visibility', 'public')
                    ->orWhere('user_id', $userId);

                if ($standardId) {
                    $q->orWhereIn('user_id', function ($sub) use ($standardId, $subInstituteId, $syear) {
                        $sub->select('student_id')
                            ->from('tblstudent_enrollment')
                            ->where('sub_institute_id', $subInstituteId)
                            ->where('syear', $syear)
                            ->where('standard_id', $standardId);
                    });
                }
            });
        }

        return $query;
    }

    /** @return array<int,string> */
    private function doubtColumns(): array
    {
        return [
            'id',
            'subject_id',
            'chapter_id',
            'topic_id',
            'title',
            'description',
            'file_name',
            'visibility',
            'user_id',
            'user_profile_id',
            'syear',
            'created_at',
            DB::raw('DATEDIFF(NOW(), created_at) as total_days'),
        ];
    }

    /**
     * Resolve a set of author ids to display names, avatars and (for students)
     * their class - in two queries regardless of page size.
     *
     * @param  array<int,int>  $userIds
     * @return array<int,array>
     */
    private function authors(array $ctx, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if (! $userIds) {
            return [];
        }

        $subInstituteId = (int) $ctx['sub_institute_id'];
        $syear          = (int) $ctx['syear'];
        $authors        = [];

        $students = DB::table('tblstudent as s')
            ->leftJoin('tblstudent_enrollment as se', function ($join) use ($syear, $subInstituteId) {
                $join->on('se.student_id', '=', 's.id')
                    ->where('se.syear', '=', $syear)
                    ->where('se.sub_institute_id', '=', $subInstituteId);
            })
            ->leftJoin('standard as st', 'st.id', '=', 'se.standard_id')
            ->leftJoin('division as dv', 'dv.id', '=', 'se.section_id')
            ->whereIn('s.id', $userIds)
            ->where('s.sub_institute_id', $subInstituteId)
            ->orderBy('se.id')
            ->get([
                's.id',
                's.image',
                DB::raw("TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name)) as name"),
                'st.name as standard_name',
                'dv.name as section_name',
            ]);

        foreach ($students as $student) {
            // orderBy se.id means the latest enrollment wins the class label.
            $authors[(int) $student->id] = $this->presentAuthor(
                (int) $student->id,
                (string) $student->name,
                'student',
                (string) ($student->image ?? ''),
                (string) ($student->standard_name ?? ''),
                (string) ($student->section_name ?? '')
            );
        }

        $missing = array_values(array_diff($userIds, array_keys($authors)));
        if ($missing) {
            $staff = DB::table('tbluser')
                ->whereIn('id', $missing)
                ->get([
                    'id',
                    'image',
                    DB::raw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) as name"),
                ]);

            foreach ($staff as $member) {
                $authors[(int) $member->id] = $this->presentAuthor(
                    (int) $member->id,
                    (string) $member->name,
                    'user',
                    (string) ($member->image ?? ''),
                    '',
                    ''
                );
            }
        }

        return $authors;
    }

    private function presentAuthor(int $userId, string $name, string $type, string $image, string $standard, string $section): array
    {
        $classes = array_values(array_filter([trim($standard), trim($section)], 'strlen'));

        return [
            'user_id'    => $userId,
            'name'       => trim($name) !== '' ? trim($name) : 'Unknown user',
            'type'       => $type,
            'class'      => $classes ? implode('/', $classes) : null,
            'avatar_url' => $this->avatarUrl($type, $image),
        ];
    }

    private function presentDoubt(object $row, array $authors): array
    {
        $userId = (int) $row->user_id;

        return [
            'id'             => (int) $row->id,
            'title'          => (string) $row->title,
            'description'    => (string) $row->description,
            'visibility'     => (string) ($row->visibility ?? ''),
            'subject_id'     => $row->subject_id !== null ? (int) $row->subject_id : null,
            'chapter_id'     => $row->chapter_id !== null ? (int) $row->chapter_id : null,
            'topic_id'       => $row->topic_id !== null ? (int) $row->topic_id : null,
            'attachment_url' => $this->attachmentUrl((string) ($row->file_name ?? '')),
            'created_at'     => (string) $row->created_at,
            'total_days'     => (int) ($row->total_days ?? 0),
            'comment_count'  => 0,
            'author'         => $authors[$userId] ?? $this->presentAuthor($userId, '', 'user', '', '', ''),
        ];
    }

    /**
     * Comment counts for a whole page of doubts in one grouped query.
     *
     * @param  array<int,int>  $doubtIds
     * @return array<int,int>
     */
    private function commentCounts(array $doubtIds): array
    {
        if (! $doubtIds) {
            return [];
        }

        return DB::table('lms_doubt_conversation')
            ->whereIn('doubt_id', $doubtIds)
            ->select('doubt_id', DB::raw('COUNT(*) as total'))
            ->groupBy('doubt_id')
            ->pluck('total', 'doubt_id')
            ->map(static fn ($total) => (int) $total)
            ->all();
    }

    /** A doubt's conversation, oldest first, with authors resolved in bulk. */
    private function comments(array $ctx, int $doubtId): array
    {
        $rows = DB::table('lms_doubt_conversation')
            ->where('sub_institute_id', (int) $ctx['sub_institute_id'])
            ->where('doubt_id', $doubtId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'doubt_id', 'message', 'user_id', 'created_at'])
            ->all();

        $authors = $this->authors($ctx, array_map(static fn ($row) => (int) $row->user_id, $rows));

        return array_map(function ($row) use ($authors) {
            $userId = (int) $row->user_id;

            return [
                'id'         => (int) $row->id,
                'doubt_id'   => (int) $row->doubt_id,
                'message'    => (string) $row->message,
                'created_at' => (string) $row->created_at,
                'author'     => $authors[$userId] ?? $this->presentAuthor($userId, '', 'user', '', '', ''),
            ];
        }, $rows);
    }

    private function avatarUrl(string $type, string $image): string
    {
        $folder = $type === 'user' ? 'user' : 'student';

        return url('/storage/' . $folder . '/' . ($image !== '' ? $image : 'no-image.jpg'));
    }

    private function attachmentUrl(string $fileName): ?string
    {
        if ($fileName === '') {
            return null;
        }

        return Storage::disk('digitalocean')->url(self::ATTACHMENT_DIR . $fileName);
    }
}
