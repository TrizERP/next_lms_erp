<?php

namespace App\Console\Commands\LMS;

use App\Services\lms\Content\ClassroomActivityDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Store interactive Classroom Activities (one reviewed JSON bundle each) as content_master rows.
 *
 *   php artisan lms:store-classroom-activity content_bundles/classroom-activity/g4-water-a1.json content_bundles/classroom-activity/g4-water-b1.json --dry-run
 *   php artisan lms:store-classroom-activity content_bundles/classroom-activity/g4-water-a1.json content_bundles/classroom-activity/g4-water-b1.json
 *
 * Each bundle is ONE row in the existing 'Classroom Activity' category, identified by its school, standard, subject,
 * chapter and file name (`classroom_activity__<key>.json`). Nothing is deleted and no other row is touched.
 *
 * Before anything is written the command checks, against the database, that the chapter belongs to the bundle's standard,
 * subject and school, and that a linked concept exists in that chapter. It refuses to write when the same file name OR the
 * same activity code is already stored for the chapter, so one activity is never stored twice. If its own row already
 * exists it writes nothing, unless --update is passed, which changes that one row in place (id, chapter and visibility
 * stay). After writing, each saved row is read back and compared with the bundle. All rows are written in ONE transaction.
 *
 * --dry-run reads and reports; it writes nothing.
 */
class StoreClassroomActivityCommand extends Command
{
    protected $signature = 'lms:store-classroom-activity
        {bundles* : paths to the reviewed activities (.json)}
        {--user=1 : content_master.created_by}
        {--syear=2026 : academic year}
        {--update : change an activity already stored under the same file name, in place}
        {--dry-run : check everything and report, write nothing}';

    protected $description = 'Store interactive Classroom Activities as content_master rows (category Classroom Activity), one row each';

    public function handle(): int
    {
        $plans = [];
        $codes = [];
        foreach ((array) $this->argument('bundles') as $path) {
            $plan = $this->plan((string) $path);
            if ($plan === null) {
                return self::FAILURE;
            }
            $codeKey = $plan['chapter']->id . ':' . strtolower($plan['doc']['code']);
            if (isset($codes[$codeKey]) || in_array($plan['filename'], array_column($plans, 'filename'), true)) {
                $this->error('Two bundles in this run are the same activity (' . $plan['doc']['code'] . ' in chapter ' . $plan['chapter']->id . '). Nothing written.');

                return self::FAILURE;
            }
            $codes[$codeKey] = true;
            $plans[] = $plan;
        }

        foreach ($plans as $plan) {
            $this->line(sprintf(
                '%s%s "%s": %d steps, %d worksheet blocks | school %d, standard %d, subject %d | chapter %d "%s"%s',
                $this->option('dry-run') ? 'DRY RUN. ' : '', $plan['doc']['code'], $plan['doc']['title'], count($plan['doc']['steps']),
                count($plan['doc']['blocks'] ?? []), $plan['tenant'], $plan['doc']['scope']['standard_id'], $plan['doc']['scope']['subject_id'],
                $plan['chapter']->id, $plan['chapter']->chapter_name,
                $plan['concept'] ? ' | concept ' . $plan['concept']->id . ' "' . $plan['concept']->name . '"' : ' | no concept linked'
            ));
            $this->line('  ' . ($plan['existing'] ? ($this->option('update') ? 'would UPDATE content_master ' . $plan['existing']->id . ' in place' : 'already stored as content_master ' . $plan['existing']->id . ' - nothing written (pass --update to change it)') : 'would INSERT one new content_master row'));
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run - nothing written.');

            return self::SUCCESS;
        }

        $writes = array_filter($plans, fn ($p) => !$p['existing'] || $this->option('update'));
        if ($writes === []) {
            $this->warn('Nothing to write.');

            return self::SUCCESS;
        }

        try {
            $ids = DB::transaction(function () use ($writes) {
                $ids = [];
                foreach ($writes as $plan) {
                    $ids[$plan['filename']] = $this->write($plan);
                }

                return $ids;
            });
        } catch (Throwable $e) {
            $this->error('Nothing stored: ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($writes as $plan) {
            $id = $ids[$plan['filename']];
            $saved = DB::table('content_master')->where('id', $id)->first();
            $back = ClassroomActivityDocument::decode($saved->description ?? null);
            if ($back === null || $back['steps'] !== $plan['doc']['steps'] || $back['code'] !== $plan['doc']['code'] || (int) $saved->chapter_id !== (int) $plan['chapter']->id) {
                $this->error("Content_master $id was written but does not read back as the same activity. Check it before using it.");

                return self::FAILURE;
            }
            $this->info(sprintf('Stored content_master #%d: %s, %d steps read back from one row.', $id, $back['code'], count($back['steps'])));
        }

        return self::SUCCESS;
    }

    /**
     * Everything that must be true before this bundle may be written, or null (an error has been printed).
     *
     * @return array<string,mixed>|null
     */
    private function plan(string $path): ?array
    {
        $doc = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($doc)) {
            $this->error('Not a readable JSON bundle: ' . $path);

            return null;
        }
        $problems = ClassroomActivityDocument::problems($doc);
        if ($problems !== []) {
            $this->error($path . ' is not a valid activity:');
            foreach ($problems as $p) {
                $this->line('  - ' . $p);
            }

            return null;
        }

        $scope = $doc['scope'];
        $tenant = (int) $scope['sub_institute_id'];
        $chapter = DB::table('chapter_master')->where('id', (int) $scope['chapter_id'])->first(['id', 'chapter_name', 'standard_id', 'subject_id', 'sub_institute_id', 'grade_id']);
        if (!$chapter || (int) $chapter->standard_id !== (int) $scope['standard_id'] || (int) $chapter->subject_id !== (int) $scope['subject_id'] || (int) $chapter->sub_institute_id !== $tenant) {
            $this->error($doc['code'] . ': chapter ' . $scope['chapter_id'] . ' is not a chapter of standard ' . $scope['standard_id'] . ', subject ' . $scope['subject_id'] . ', school ' . $tenant . '.');

            return null;
        }
        $gradeId = $chapter->grade_id ?: DB::table('standard')->where('id', (int) $scope['standard_id'])->value('grade_id');
        if (!$gradeId) {
            $this->error($doc['code'] . ': grade could not be resolved for standard ' . $scope['standard_id'] . '.');

            return null;
        }

        // A linked concept must really be a concept of this chapter and school (never an invented id), and its topic must agree.
        $concept = null;
        $conceptId = (int) ($doc['links']['concept_id'] ?? 0);
        if ($conceptId > 0) {
            $concept = DB::table('lms_concept')->where('id', $conceptId)->where('chapter_id', $chapter->id)->where('sub_institute_id', $tenant)->first(['id', 'name', 'topic_id']);
            if (!$concept) {
                $this->error($doc['code'] . ': concept ' . $conceptId . ' is not a concept of chapter ' . $chapter->id . ' in school ' . $tenant . '.');

                return null;
            }
            $topicId = (int) ($doc['links']['topic_id'] ?? 0);
            if ($topicId > 0 && (int) $concept->topic_id !== $topicId) {
                $this->error($doc['code'] . ': concept ' . $conceptId . ' belongs to topic ' . $concept->topic_id . ', not ' . $topicId . '.');

                return null;
            }
        }

        // One activity, one row: by file name, and by activity code among the chapter's other stored activities.
        $filename = ClassroomActivityDocument::filenameFor($doc['key']);
        $rows = DB::table('content_master')
            ->where('sub_institute_id', $tenant)->where('standard_id', $scope['standard_id'])->where('subject_id', $scope['subject_id'])
            ->where('chapter_id', $chapter->id)->where('content_category', ClassroomActivityDocument::CATEGORY)
            ->where('filename', 'like', ClassroomActivityDocument::FILE_PREFIX . '%')
            ->get(['id', 'filename', 'description']);
        $existing = null;
        foreach ($rows as $row) {
            if (!ClassroomActivityDocument::isActivityFilename($row->filename)) {
                continue;
            }
            if ($row->filename === $filename) {
                if ($existing) {
                    $this->error($doc['code'] . ': more than one row already stores this activity. Resolve that first; nothing written.');

                    return null;
                }
                $existing = $row;
                continue;
            }
            $other = ClassroomActivityDocument::decode($row->description);
            if ($other !== null && strcasecmp($other['code'], $doc['code']) === 0) {
                $this->error($doc['code'] . ': content_master ' . $row->id . ' already stores an activity with this code under another file name. Nothing written.');

                return null;
            }
        }

        return compact('doc', 'tenant', 'chapter', 'gradeId', 'concept', 'filename', 'existing');
    }

    /** @param array<string,mixed> $plan */
    private function write(array $plan): int
    {
        $doc = $plan['doc'];
        $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fields = [
            'title' => $doc['title'],
            'description' => $json,
            'file_size' => (string) strlen($json),
            'content_category' => ClassroomActivityDocument::CATEGORY,
            'concept_id' => $plan['concept']?->id,
        ];
        if ($plan['existing']) {
            DB::table('content_master')->where('id', $plan['existing']->id)->update($fields);

            return (int) $plan['existing']->id;
        }

        return (int) DB::table('content_master')->insertGetId($fields + [
            'grade_id' => $plan['gradeId'],
            'standard_id' => (int) $doc['scope']['standard_id'],
            'subject_id' => (int) $doc['scope']['subject_id'],
            'chapter_id' => (int) $plan['chapter']->id,
            // Left empty on purpose: the chapter's Classroom Resource list only reads rows with no topic.
            'topic_id' => null,
            'file_folder' => '/lms_content_file',
            'filename' => $plan['filename'],
            'url' => null,
            'file_type' => ClassroomActivityDocument::FILE_TYPE,
            'show_hide' => 1,
            'source' => ClassroomActivityDocument::SOURCE,
            'created_by' => (int) $this->option('user'),
            'sub_institute_id' => $plan['tenant'],
            'basic_advance' => '1',
            'syear' => (int) $this->option('syear'),
        ]);
    }
}
