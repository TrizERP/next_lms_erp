<?php

namespace App\Services\Ceds;

use App\Services\ContentGenerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads, and only reads, what is stored behind a chapter's existing resources so a lesson flow can be
 * lined up with it: the study deck's slides, the activities document, the revision notes and the topic
 * names.
 *
 * It is handed the ResourceResolver's result, so it can only ever open a row the resolver already
 * found visible, in the right chapter and readable by this school; it never searches for a file on its
 * own. The files are fetched the way StudyDeckApiController fetches them (the existing sidecar path
 * helpers on ContentGenerationService, disk `digitalocean`). A sidecar that cannot be read is reported,
 * never thrown: the lesson flow then says so instead of failing.
 */
class TopicResourceReader
{
    /**
     * @param array<string,array<string,mixed>> $resolved ResourceResolver::resolve() output
     * @param list<int> $tenants sub_institute_ids this school may read
     * @return array<string,mixed> material for LessonFlowBuilder
     */
    public function read(array $resolved, int $chapterId, array $tenants): array
    {
        $deckItem = $this->first($resolved['D2']['items'] ?? [], 'study_deck');
        $activityItem = $this->first($resolved['D3']['items'] ?? [], 'study_document');
        $notesItem = $this->first($resolved['D5']['items'] ?? [], 'study_document');

        $ids = array_values(array_filter([$deckItem['id'] ?? null, $activityItem['id'] ?? null, $notesItem['id'] ?? null]));
        $files = $ids === [] ? [] : DB::table('content_master')
            ->whereIn('id', $ids)
            ->where('chapter_id', $chapterId)
            ->whereIn('sub_institute_id', $tenants)
            ->pluck('filename', 'id')
            ->all();

        $unreadable = [];
        $deck = $this->json($deckItem, $files, fn (string $f) => ContentGenerationService::studyDeckSidecarPath($f), 'presentation', $unreadable);
        $activities = $this->json($activityItem, $files, fn (string $f) => ContentGenerationService::studyDocumentSidecarPath($f), 'classroom activities', $unreadable);
        $notes = $this->json($notesItem, $files, fn (string $f) => ContentGenerationService::studyDocumentSidecarPath($f), 'revision notes', $unreadable);

        $labs = array_values(array_map(
            fn (array $i) => ['id' => $i['id'], 'topic_id' => $i['topic_id'], 'title' => $i['title'], 'status' => $i['status'] === ResourceResolver::PENDING_REVIEW ? 'review' : 'published'],
            array_filter($resolved['D4']['items'] ?? [], fn (array $i) => !empty($i['topic_id']))
        ));

        return [
            'deck' => $deck, 'deck_id' => $deck !== null ? (int) $deckItem['id'] : null,
            'activities' => $activities, 'activities_id' => $activities !== null ? (int) $activityItem['id'] : null,
            'notes' => $notes, 'notes_id' => $notes !== null ? (int) $notesItem['id'] : null,
            'labs' => $labs,
            'unreadable' => $unreadable,
        ];
    }

    /**
     * Names of topics, by id. Only ids that came out of a verified chapter resource are asked for.
     *
     * @param list<int> $topicIds
     * @return array<int,string>
     */
    public function topicNames(array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        return DB::table('topic_master')->whereIn('id', $topicIds)->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    /** @param list<array<string,mixed>> $items @return array<string,mixed>|null */
    private function first(array $items, string $kind): ?array
    {
        foreach ($items as $item) {
            if (($item['kind'] ?? null) === $kind) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed>|null $item
     * @param array<int|string,mixed> $files
     * @param list<string> $unreadable
     * @return array<string,mixed>|null
     */
    private function json(?array $item, array $files, callable $path, string $label, array &$unreadable): ?array
    {
        if ($item === null) {
            return null;
        }
        $filename = (string) ($files[$item['id']] ?? '');
        if ($filename === '') {
            $unreadable[] = $label;

            return null;
        }
        try {
            $data = json_decode((string) Storage::disk('digitalocean')->get($path($filename)), true);
        } catch (Throwable) {
            $data = null;
        }
        if (!is_array($data)) {
            $unreadable[] = $label;

            return null;
        }

        return $data;
    }
}
