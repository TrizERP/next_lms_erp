<?php

namespace App\Console\Commands\LMS;

use App\Services\lms\Content\VocabularyClassDeck;
use App\Services\StudyDeck\StudyDeckImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Store a Vocabulary Class (a whole flashcard deck) as ONE content_master row.
 *
 *   php artisan lms:store-vocabulary-class content_bundles/vocabulary-class/g4-water.json --images=storage/app/vocabulary-class/g4-water/images --dry-run
 *   php artisan lms:store-vocabulary-class content_bundles/vocabulary-class/g4-water.json --images=...
 *
 * The bundle is the reviewed deck; each picture is named by `image.file` and found in --images (a .jpg of the same
 * stem). PICTURES GO INTO THE DATABASE, in `study_deck_images` (the table the study decks already use, one copy per
 * school, found again by checksum), and the deck holds `study-deck-image:<id>` references. Nothing is left in a folder
 * or a bucket. The pictures, the row and the picture links are written in ONE transaction, so a failure leaves nothing.
 *
 * ONE ROW, NEVER TWO. A deck is identified by its school, standard, subject and file name (`vocabulary_class__<key>.json`).
 * If one is already stored the command writes nothing, unless --update is passed, which changes that row in place (its
 * id, chapter and visibility stay). After writing, the saved row and every picture are read back and compared with the
 * bundle.
 */
class StoreVocabularyClassCommand extends Command
{
    protected $signature = 'lms:store-vocabulary-class
        {bundle : path to the reviewed deck (.json)}
        {--images= : folder holding the pictures the deck names}
        {--tenant= : sub_institute_id (default: the bundle\'s scope)}
        {--user=1 : content_master.created_by}
        {--syear=2026 : academic year}
        {--update : change the deck already stored for this book, in place}
        {--dry-run : check everything and report, write nothing}';

    protected $description = 'Store a Vocabulary Class flashcard deck as a single content_master row, its pictures in the database';

    public function handle(): int
    {
        $path = (string) $this->argument('bundle');
        $bundle = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($bundle)) {
            $this->error('Not a readable JSON bundle: ' . $path);

            return self::FAILURE;
        }

        $scope = (array) ($bundle['scope'] ?? []);
        $tenant = (int) ($this->option('tenant') ?: ($scope['sub_institute_id'] ?? 0));
        $standardId = (int) ($scope['standard_id'] ?? 0);
        $subjectId = (int) ($scope['subject_id'] ?? 0);
        $key = (string) ($bundle['key'] ?? '');
        if ($tenant < 1 || $standardId < 1 || $subjectId < 1 || $key === '') {
            $this->error('The bundle needs key and scope (sub_institute_id, standard_id, subject_id).');

            return self::FAILURE;
        }

        // Every chapter the deck names must be a chapter of this book and this school: that is what keeps a deck out of
        // another school's or another book's list.
        $chapterIds = VocabularyClassDeck::chapterIds($bundle);
        $chapters = DB::table('chapter_master')->whereIn('id', $chapterIds)->get(['id', 'chapter_name', 'standard_id', 'subject_id', 'sub_institute_id', 'grade_id'])->keyBy('id');
        foreach ($chapterIds as $id) {
            $c = $chapters->get($id);
            if (!$c || (int) $c->standard_id !== $standardId || (int) $c->subject_id !== $subjectId || (int) $c->sub_institute_id !== $tenant) {
                $this->error("Chapter $id is not a chapter of standard $standardId, subject $subjectId, school $tenant.");

                return self::FAILURE;
            }
        }
        $home = $chapters->get($chapterIds[0] ?? 0);
        if (!$home) {
            $this->error('The deck names no chapter.');

            return self::FAILURE;
        }
        $gradeId = $home->grade_id ?: DB::table('standard')->where('id', $standardId)->value('grade_id');
        if (!$gradeId) {
            $this->error('Grade could not be resolved for standard ' . $standardId . '.');

            return self::FAILURE;
        }

        // Pictures: each named file must exist and be an acceptable picture; they are written below, in the transaction.
        $imagesDir = rtrim((string) $this->option('images'), '/\\');
        $store = new StudyDeckImages();
        $pictures = [];   // "t.c" => [bytes, chapter id]
        $deck = $bundle;
        foreach ($deck['topics'] as $t => $topic) {
            foreach ($topic['cards'] as $c => $card) {
                $image = $card['image'] ?? null;
                if ($image === null) {
                    continue;
                }
                $stem = pathinfo((string) ($image['file'] ?? ''), PATHINFO_FILENAME);
                $file = $imagesDir . DIRECTORY_SEPARATOR . $stem . '.jpg';
                if ($stem === '' || $imagesDir === '' || !is_file($file)) {
                    $this->error('Picture not found for "' . $card['term'] . '": ' . ($file ?: $stem));

                    return self::FAILURE;
                }
                $bytes = (string) file_get_contents($file);
                try {
                    $store->inspect($bytes);
                } catch (\InvalidArgumentException $e) {
                    $this->error('Picture for "' . $card['term'] . '" is not acceptable: ' . $e->getMessage());

                    return self::FAILURE;
                }
                $pictures["$t.$c"] = [$bytes, (int) $topic['chapter_id']];
                // A placeholder reference stands in until the picture has an id; it makes the deck checkable now.
                $deck['topics'][$t]['cards'][$c]['image'] = $this->imageEntry($image, StudyDeckImages::ref(1));
            }
        }
        $problems = VocabularyClassDeck::problems($deck);
        if ($problems !== []) {
            $this->error('The deck is not valid:');
            foreach ($problems as $p) {
                $this->line('  - ' . $p);
            }

            return self::FAILURE;
        }

        $filename = VocabularyClassDeck::filenameFor($key);
        $existing = DB::table('content_master')
            ->where('sub_institute_id', $tenant)->where('standard_id', $standardId)->where('subject_id', $subjectId)
            ->where('content_category', VocabularyClassDeck::CATEGORY)->where('filename', $filename)
            ->get(['id', 'chapter_id', 'show_hide', 'description']);
        if ($existing->count() > 1) {
            $this->error('More than one row already stores this deck (' . $existing->pluck('id')->implode(', ') . '). Resolve that first; nothing written.');

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%sDeck "%s": %d cards in %d topics, %d pictures (into the database), school %d, standard %d, subject %d | home chapter %d "%s"',
            $this->option('dry-run') ? 'DRY RUN. ' : '',
            $deck['title'], VocabularyClassDeck::cardCount($deck), count($deck['topics']), count($pictures),
            $tenant, $standardId, $subjectId, $home->id, $home->chapter_name
        ));
        foreach ($chapterIds as $id) {
            $this->line('  lists under chapter ' . $id . ' "' . $chapters->get($id)->chapter_name . '"');
        }

        $row = $existing->first();
        if ($row && !$this->option('update')) {
            $this->warn('Already stored as content_master ' . $row->id . '. Nothing written; pass --update to change it in place.');

            return self::SUCCESS;
        }
        $this->line($row ? '  would UPDATE content_master ' . $row->id . ' in place' : '  would INSERT one new content_master row');
        if ($this->option('dry-run')) {
            $this->info('Dry run - nothing written.');

            return self::SUCCESS;
        }

        // The pictures a previous version of this row held in the object store (an earlier run kept them there).
        $oldBucketFiles = [];
        if ($row) {
            $old = json_decode((string) $row->description, true);
            foreach ((array) ($old['topics'] ?? []) as $topic) {
                foreach ((array) ($topic['cards'] ?? []) as $card) {
                    $src = (string) ($card['image']['src'] ?? '');
                    if (preg_match('#/public/(lms_content_file/vocabulary_class_[A-Za-z0-9_.-]+\.jpg)$#', $src, $m)) {
                        $oldBucketFiles[] = 'public/' . $m[1];
                    }
                }
            }
        }

        try {
            $id = DB::transaction(function () use ($store, $pictures, &$deck, $row, $tenant, $standardId, $subjectId, $home, $gradeId, $filename) {
                $imageIds = [];
                foreach ($pictures as $at => [$bytes, $chapterId]) {
                    [$t, $c] = array_map('intval', explode('.', $at));
                    $put = $store->put($bytes, $tenant, $chapterId);
                    $imageIds[] = $put['id'];
                    $deck['topics'][$t]['cards'][$c]['image']['src'] = $put['ref'];
                }
                $problems = VocabularyClassDeck::problems($deck);
                if ($problems !== []) {
                    throw new \RuntimeException('The deck is not valid: ' . implode(' ', $problems));
                }
                $json = json_encode($deck, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $fields = ['title' => 'Vocabulary class: ' . $deck['title'], 'description' => $json, 'file_size' => (string) strlen($json), 'content_category' => VocabularyClassDeck::CATEGORY];
                if ($row) {
                    DB::table('content_master')->where('id', $row->id)->update($fields);
                    $contentId = (int) $row->id;
                } else {
                    $contentId = (int) DB::table('content_master')->insertGetId($fields + [
                        'grade_id' => $gradeId,
                        'standard_id' => $standardId,
                        'subject_id' => $subjectId,
                        'chapter_id' => $home->id,
                        'topic_id' => null,
                        'concept_id' => null,
                        'file_folder' => '/lms_content_file',
                        'filename' => $filename,
                        'url' => null,
                        'file_type' => VocabularyClassDeck::FILE_TYPE,
                        'show_hide' => 1,
                        'content_category' => VocabularyClassDeck::CATEGORY,
                        'source' => VocabularyClassDeck::SOURCE,
                        'created_by' => (int) $this->option('user'),
                        'sub_institute_id' => $tenant,
                        'basic_advance' => '1',
                        'syear' => (int) $this->option('syear'),
                    ]);
                }

                // Which row uses which picture: what keeps a shared picture safe to remove later.
                foreach (array_unique($imageIds) as $imageId) {
                    DB::table('study_deck_image_links')->updateOrInsert(
                        ['image_id' => $imageId, 'content_id' => $contentId],
                        ['chapter_id' => $home->id]
                    );
                }

                return $contentId;
            });
        } catch (Throwable $e) {
            $this->error('Nothing stored: ' . $e->getMessage());

            return self::FAILURE;
        }

        // Read the saved row back: it must hold the whole deck, and every picture it names must be in the database.
        $saved = DB::table('content_master')->where('id', $id)->first();
        $back = VocabularyClassDeck::decode($saved->description ?? null);
        $want = collect($deck['topics'])->flatMap(fn ($t) => collect($t['cards'])->pluck('id'))->all();
        $have = $back ? collect($back['topics'])->flatMap(fn ($t) => collect($t['cards'])->pluck('id'))->all() : [];
        $missing = [];
        foreach ($back['topics'] ?? [] as $topic) {
            foreach ($topic['cards'] as $card) {
                $imageId = StudyDeckImages::idFromRef($card['image']['src'] ?? null);
                if ($imageId !== null && ($store->bytes($imageId) === null || $store->meta($imageId)['tenant'] !== $tenant)) {
                    $missing[] = $card['term'];
                }
            }
        }
        if ($want !== $have || $missing !== []) {
            $this->error("Content_master $id was written but does not read back as the same deck" . ($missing ? ' (pictures missing for: ' . implode(', ', $missing) . ')' : '') . '. Check it before using it.');

            return self::FAILURE;
        }

        // Nothing points at the old bucket copies any more.
        foreach (array_unique($oldBucketFiles) as $file) {
            try {
                Storage::disk('digitalocean')->delete($file);
            } catch (Throwable $e) {
                $this->warn('Could not remove the old object-store copy ' . $file . ': ' . $e->getMessage());
            }
        }
        $this->info(sprintf('Stored content_master #%d: %d cards read back from one row, %d pictures in the database.', $id, count($have), count($pictures)));

        return self::SUCCESS;
    }

    /** @return array<string,mixed> */
    private function imageEntry(array $image, string $src): array
    {
        return array_filter([
            'src' => $src,
            'alt' => $image['alt'] ?? '',
            'caption' => $image['caption'] ?? null,
            'credit' => 'Std 4 Water textbook' . (isset($image['page']) ? ', page ' . $image['page'] : ''),
        ], fn ($v) => $v !== null);
    }
}
