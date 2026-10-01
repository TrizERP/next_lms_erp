<?php

namespace App\Services;

use App\Services\lms\H5P\GenerationFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

trait GeneratesH5PQuestions
{
    protected function generateH5P(array $input): array
    {
        $conceptId = (int) ($input['concept_id'] ?? 0);
        $tenant = $this->nullableInt($input['sub_institute_id'] ?? null);
        if (!$tenant) {
            return $this->fail('Authenticated institute required.', 'forbidden');
        }
        $slice = $this->loadConceptSlice($conceptId, $tenant, $input['chapter_id'] ?? null, $input['subject_id'] ?? null, $input['standard_id'] ?? null);
        if (!$slice['found']) {
            return $this->fail('Concept not found.');
        }
        if ((int) ($slice['concept']->sub_institute_id ?? 0) !== $tenant) {
            return $this->fail('Concept does not belong to your institute.', 'forbidden');
        }
        if (!Schema::hasTable('question_type_catalog') || !Schema::hasColumn('lms_question_master', 'question_format_code')) {
            return $this->fail('Question catalog and question_format_code migration are required.');
        }
        $count = (int) ($input['questions_per_type'] ?? 5);
        if ($count < 4 || $count > 5) {
            return $this->fail('questions_per_type must be 4 or 5.');
        }
        $catalog = DB::table('question_type_catalog')->where('status', 1)->orderBy('id')->get()->unique('code');
        $formats = new GenerationFormat();
        $intelligence = $this->buildConceptIntelligence($slice);
        $grounding = $this->buildConceptSlice($slice, $intelligence);
        $key = $this->semanticConceptKey($conceptId, $slice['concept']->name);
        $types = $skipped = $ids = $questions = [];
        $requestedCode = strtolower((string) ($input['question_type'] ?? 'all'));
        // These are grading engine IDs, never catalogue IDs.
        $grading = DB::table('question_type_master')->where('status', 1)->get();
        foreach ($catalog as $entry) {
            $code = (string) $entry->code;
            if ($requestedCode !== 'all' && $requestedCode !== '' && $requestedCode !== $code) {
                continue;
            }
            $player = $formats->playerFor($code);
            if (!$player) {
                $skipped[$code] = 'No scored generation contract for the mapped H5P player.';
                continue;
            }
            $choice = $player === 'h5p_single_choice_set';
            $master = $grading->first(fn ($row) => in_array(strtolower(trim($row->question_type)), $choice
                ? ['mcq', 'multiple', 'multiple choice', 'multiple_choice'] : ['narrative'], true));
            if (!$master) {
                $skipped[$code] = 'No compatible grading-engine type.';
                continue;
            }
            $ctx = $this->buildPersistenceContext($slice, $input, (int) $master->id, $key);
            $quota = $this->buildQuota($choice ? 'mcq' : 'narrative', $count, [], $slice, $intelligence);
            $stems = $this->buildDedupCorpus($conceptId, (int) $master->id);
            $rows = $errors = [];
            foreach ($quota as $slot) {
                for ($n = 0; $n < $slot['count']; $n++) {
                    // Retry malformed/duplicate output without changing the requested format.
                    for ($attempt = 0; $attempt < 3; $attempt++) {
                        $prompt = json_encode([
                            'catalog_code' => $code, 'catalog_label' => $entry->label, 'player' => $player,
                            'contract' => GenerationFormat::CONTRACTS[$player], 'concept' => $grounding,
                            'difficulty' => $slot, 'avoid_questions' => $stems,
                            'instruction' => 'Generate one grounded question of exactly this catalog format. Return JSON {"rows":[{"question_title":"...","answer":{...}}]}. Answer fields must satisfy the contract. Plain text only, no HTML. Do not test prerequisites. Do not invent images or media.',
                        ], JSON_UNESCAPED_UNICODE);
                        $call = $this->callDeepSeek('You author valid, scored H5P question-bank activities. Return only JSON.', $prompt, ['model' => $this->model, 'temperature' => $this->temperature]);
                        if (!$call['ok']) {
                            $errors[] = $call['error'];
                            break 3;
                        }
                        $parsed = $this->parseResponse($call['content']);
                        try {
                            $raw = $parsed['data']['rows'][0] ?? null;
                            if (!is_array($raw) || ($call['finish_reason'] ?? '') === 'length') {
                                throw new InvalidArgumentException('Invalid or truncated JSON.');
                            }
                            $row = $formats->normalize($raw, $code, $player);
                            if (in_array(mb_strtolower(trim($row['question_title'])), array_map(fn ($s) => mb_strtolower(trim($s)), $stems), true)) {
                                throw new InvalidArgumentException('Duplicate question.');
                            }
                            $row = array_merge($row, ['description' => '', 'subconcept' => $slice['concept']->name,
                                'points' => $slot['points'], 'multiple_answer' => 0, 'hint_text' => null,
                                'learning_outcome' => [$slice['concept']->name]]);
                            $row['answer'] = array_merge($row['answer'], ['bloom_level' => $slot['level'],
                                'dok_level' => $slot['dok'], 'difficulty' => $slot['difficulty']]);
                            $rows[] = $row;
                            $stems[] = $row['question_title'];
                            break;
                        } catch (InvalidArgumentException $e) {
                            $errors[] = $e->getMessage();
                        }
                    }
                }
            }
            $persist = DB::transaction(fn () => $this->persist(['rows' => $rows, 'semantic_concept_key' => $key], $code, $ctx,
                ['model' => $this->model, 'prompt_version' => 'h5p-generation-1', 'h5p_player' => $player]));
            $ids = array_merge($ids, $persist['ids']);
            $questions = array_merge($questions, $persist['questions']);
            $types[$code] = ['catalog_id' => (int) $entry->id, 'h5p_player' => $player, 'requested' => $count,
                'inserted' => $persist['inserted'], 'underfilled' => $persist['inserted'] < $count, 'errors' => $errors];
        }
        return ['status' => count($ids) > 0, 'message' => count($ids).' H5P-compatible questions inserted.', 'data' => [
            'question_type' => 'all', 'requested' => count($types) * $count, 'generated' => count($ids), 'inserted' => count($ids),
            'question_ids' => $ids, 'questions' => $questions, 'types' => $types, 'skipped_types' => $skipped,
            'underfilled' => count($ids) < count($types) * $count,
        ]];
    }
}
