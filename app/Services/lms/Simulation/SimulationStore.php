<?php

namespace App\Services\lms\Simulation;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where simulations live. Every read and write of the `lms_simulation*` tables goes through here, so the rules (whose simulation is visible
 * to whom, what a saved specification must look like, how versions are kept) exist once and the HTTP controller and the artisan commands
 * cannot disagree about them.
 *
 * PORTED FROM hp_erp (LmsSimulationController / SimulationStore), with the tenancy of K12 Prayogshala instead of hp_erp's per-organisation
 * gallery: a simulation is OWNED by one tenant (the platform tenant, config lms_content.platform_sub_institute_ids), and is READ by the
 * tenants in PrayogshalaService::visibleTenants(). The controller passes that list in; this class never decides who a caller is.
 *
 * VISIBILITY. A learner sees a simulation when it is `published`, not deleted and owned by a visible tenant. MAPPED means it has at least one link whose
 * review_status is `published` (SimulationLinks::mappedIds): it then shows under the chapters, topics and concepts it is linked to. One that is NOT MAPPED yet
 * shows in every chapter's tab with a "not mapped yet" flag until its links are published. Staff additionally see links in `review`, and authors see drafts.
 *
 * THE SPECIFICATION IS STORED IN ONE CANONICAL FORM. It is decoded to check it and to read the card facts (title, minutes, missions ...), and
 * what is saved and returned is that same document written out compactly (JSON_FLAGS): an empty object stays `{}`, and a file's layout, or
 * its "3.0" where a browser would send "3", makes no difference. Every door (the screen, a script, the artisan command) therefore stores
 * the same text, and so the same sha256, for the same document; "is this what is already current?" is answered on that text.
 * Deep validation (every reference, range and expression) is the runtime's job, with the same code that plays it; here a specification is
 * checked for shape and size, which is what keeps an obviously wrong file from reaching a gallery.
 *
 * ASSETS. A specification may name the 3D models and the images its scene shows: `assets: { models: [ { id, sha256, name?, kind? } ] }`, where
 * `kind` is "model" (a binary glTF, the default), "image" (PNG, JPEG or WebP) or "hdr" (a Radiance .hdr: the light of a place), 40 entries at most. The file is not in the specification - it
 * is a PRIVATE object on the `digitalocean` disk at `k12_sim_assets/{sha256}.{glb|png|jpg|webp}` (a prefix K12 owns: the bucket is shared with hp_erp,
 * which deletes objects it thinks are its own), registered with its licence and credit in `lms_simulation_asset` (added by
 * `lms:simulations-assets-copy` or by POST /lms/prayogshala/simulations/assets, which tell the kind from the file's first bytes, see
 * assetType() and SimulationAssetService) and streamed to the player by GET /lms/prayogshala/simulations/{id}/assets/{sha256}. A
 * simulation can use an asset of its own tenant, or a platform one (tenant NULL); never another tenant's, whatever hash it
 * names (see assetRows()). Saving a simulation REFUSES a hash that is not such a registered asset, naming it (see assertAssetsRegistered()):
 * the register is the one door to the bucket, so a specification cannot point at a file that was never uploaded or is someone else's.
 *
 * TWO SCHEMAS. "g2g.sim/1" names a `family` (explorer or network) and a `lesson`; "g2g.sim/2" names an `engine` (formula, rigid or des), a list
 * of `views` and a `flow` of typed steps. Both are accepted and v1 stays valid for ever (the runtime upgrades it in memory). The coarse checks
 * below are the same kind for both: shapes and sizes, and the counts a card shows. A v2 `formula` or `rigid` engine is an explorer, a `des`
 * engine is a network; that two-valued family is what parse() reports (`facts.family`), whichever schema the document is written in.
 */
final class SimulationStore
{
    public const SPEC_SCHEMA = 'g2g.sim/1';
    public const SPEC_SCHEMA_V2 = 'g2g.sim/2';
    public const SCHEMAS = [self::SPEC_SCHEMA, self::SPEC_SCHEMA_V2];
    public const ENGINES = ['formula', 'rigid', 'des'];
    public const VIEW_KINDS = ['scene3d', 'trajectory', 'network'];
    public const STEP_TYPES = ['intro', 'play', 'predict', 'mission', 'explain', 'info', 'quiz'];
    public const MAX_SPEC_BYTES = 1000000;
    public const JSON_DEPTH = 64;
    /** json_decode counts the document itself as a level, so 64 nested containers (the limit, and what the runtime accepts) need 65. */
    private const PARSE_DEPTH = self::JSON_DEPTH + 1;
    public const MAX_KNOBS = 8;            // an explorer (v1) or a formula / rigid engine (v2)
    public const MAX_KNOBS_NETWORK = 12;   // a network (v1) or a des engine (v2)
    public const MAX_OUTPUTS = 12;
    public const MAX_MISSIONS = 6;
    public const MAX_STEPS = 24;
    public const MAX_VIEWS = 2;
    public const MAX_VIEWS_DES = 1;
    public const ASSET_DISK = 'digitalocean';
    /** The K12-owned prefix on the shared bucket. hp_erp's own objects live under `sim_assets/`; K12 never writes there and never deletes from there. */
    public const ASSET_PREFIX = 'k12_sim_assets';
    /** Where hp_erp keeps the same files; a source for `lms:simulations-assets-copy`, read-only. */
    public const SOURCE_PREFIX = 'sim_assets/platform';
    public const MAX_ASSET_BYTES = 26214400;   // 25 MB, a model
    public const MAX_IMAGE_BYTES = 2097152;    // 2 MB, an image
    public const MAX_HDR_BYTES = 8388608;      // 8 MB, a Radiance HDR sky (a 2K one is about 5 MB)
    public const ASSET_KINDS = ['model', 'image', 'hdr'];
    public const MAX_ASSET_MODELS = 40;
    private const SHA256 = '/^[a-f0-9]{64}$/D';
    /** How a specification is written when it is stored. No JSON_PRESERVE_ZERO_FRACTION: JavaScript cannot tell 3.0 from 3, so neither may this. */
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    public const STATUSES = ['draft', 'published', 'archived'];
    public const FAMILIES = ['network', 'explorer'];
    private const SLUG = '/^[a-z][a-z0-9_-]{0,31}$/D';
    private const DIFFICULTIES = ['intro', 'intermediate', 'advanced'];

    // ---------------------------------------------------------------- the specification

    /**
     * Checks a specification's shape and reads the facts a card shows. `json` is the canonical text (see the class comment): the one to store
     * and to hash, and the one that `spec` was decoded from, so "5.0" is checked as the whole number 5 that the runtime reads it as.
     *
     * @return array{spec: \stdClass, json: string, facts: array<string, mixed>}
     */
    public function parse(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            throw new SimulationException(['The specification is empty.']);
        }
        if (strlen($json) > self::MAX_SPEC_BYTES) {
            throw new SimulationException(['The specification is larger than 1 MB.'], 413);
        }

        // an object with the same key twice is read differently by different parsers (which copy wins), so it is refused before anything reads it
        if (($twice = $this->duplicateKey($json)) !== null) {
            throw new SimulationException(["The specification has the key \"{$twice}\" twice in one object. Parsers disagree about which copy counts, so it is refused: keep one."]);
        }
        try {
            $spec = json_decode($json, false, self::PARSE_DEPTH, JSON_THROW_ON_ERROR);
            if ($spec instanceof \stdClass) {
                $json = $this->canonical($spec);
                $spec = json_decode($json, false, self::PARSE_DEPTH, JSON_THROW_ON_ERROR);
            }
        } catch (\JsonException $e) {
            throw new SimulationException([$e->getCode() === JSON_ERROR_DEPTH
                ? 'The specification is nested deeper than ' . self::JSON_DEPTH . ' levels.'
                : 'The specification is not valid JSON: ' . $e->getMessage()]);
        }
        if (! $spec instanceof \stdClass) {
            throw new SimulationException(['The specification must be a JSON object.']);
        }

        $problems = [];
        $text = function (object $o, string $key, int $max, bool $required, string $where = '') use (&$problems): ?string {
            $v = $o->{$key} ?? null;
            $name = ($where === '' ? '' : $where . '.') . $key;
            if ($v === null) {
                if ($required) {
                    $problems[] = "\"{$name}\" is required.";
                }

                return null;
            }
            if (! is_string($v) || trim($v) === '' || mb_strlen($v) > $max) {
                $problems[] = "\"{$name}\" must be text of 1 to {$max} characters.";

                return null;
            }

            return trim($v);
        };
        $listSize = function (object $o, string $key, int $min, int $max, string $where = '') use (&$problems): int {
            $v = $o->{$key} ?? null;
            $name = ($where === '' ? '' : $where . '.') . $key;
            if (! is_array($v) || count($v) < $min || count($v) > $max) {
                $problems[] = "\"{$name}\" must be a list of {$min} to {$max} items.";

                return 0;
            }

            return count($v);
        };

        $schema = $spec->schema ?? null;
        $v2 = $schema === self::SPEC_SCHEMA_V2;
        if (! in_array($schema, self::SCHEMAS, true)) {
            $problems[] = '"schema" must be "' . self::SPEC_SCHEMA . '" or "' . self::SPEC_SCHEMA_V2 . '".';
        }
        $slug = $text($spec, 'id', 32, true);
        if ($slug !== null && ! preg_match(self::SLUG, $slug)) {
            $problems[] = '"id" is lowercase letters, digits, "-" and "_", starting with a letter (32 characters at most).';
        }
        $version = $spec->version ?? null;
        if (! is_int($version) || $version < 1) {
            $problems[] = '"version" must be a whole number from 1.';
        }
        $title = $text($spec, 'title', 60, true);
        $subtitle = $text($spec, 'subtitle', 60, false);
        $summary = $text($spec, 'summary', 300, false);
        // Which kind of simulation it is. A g2g.sim/1 says `family` (network | explorer); a g2g.sim/2 says `engine` (formula | rigid | des), which
        // says the same and a little more: formula and rigid are explorers, des is a network. `$family` is the two-valued answer for both.
        $engine = null;
        if ($v2) {
            $engine = $spec->engine ?? null;
            if (! in_array($engine, self::ENGINES, true)) {
                $problems[] = '"engine" must be one of: ' . implode(', ', self::ENGINES) . '.';
                $engine = null;
            }
            $family = $engine === null ? null : ($engine === 'des' ? 'network' : 'explorer');
        } else {
            $family = $spec->family ?? null;
            if (! in_array($family, self::FAMILIES, true)) {
                $problems[] = '"family" must be one of: ' . implode(', ', self::FAMILIES) . '.';
            }
            $engine = $family === 'explorer' ? 'formula' : ($family === 'network' ? 'des' : null);
        }

        // what a family is made of; the runtime checks every reference and expression inside. A v2 whose engine is not known gets none of
        // these (the engine problem above is the one to fix first); a v1 whose family is not known has always been held to the network's.
        $explorer = $family === 'explorer';
        $rigid = $engine === 'rigid';
        $typed = ! $v2 || $engine !== null;
        $listSize($spec, 'knobs', 1, $explorer ? self::MAX_KNOBS : self::MAX_KNOBS_NETWORK);
        $model = $spec->model ?? null;
        if ($typed && ! $explorer) {
            $listSize($spec, 'scenarios', 1, 8);
            $listSize($spec, 'targets', 1, 6);
        }
        if (! $model instanceof \stdClass) {
            $problems[] = '"model" is required.';
        } elseif ($typed && $explorer) {
            if (! $rigid) {
                $listSize($model, 'state', 0, 8, 'model'); // none for a static model; a rigid engine has no equations, the world moves
            }
            $listSize($model, 'outputs', 1, self::MAX_OUTPUTS, 'model');
        } elseif ($typed) {
            $listSize($model, 'pools', 1, 10, 'model');
        }
        $missions = 0;
        if ($v2) {
            $missions = $this->flowMissions($spec, $problems, $text, $listSize);
            $this->checkViews($spec, $engine, $problems);
            $this->checkWorld($spec, $engine, $problems);
        } else {
            $lesson = $spec->lesson ?? null;
            if (! $lesson instanceof \stdClass) {
                $problems[] = '"lesson" is required.';
            } else {
                $text($lesson, 'intro', 4000, false, 'lesson');
                $missions = $listSize($lesson, 'missions', 1, self::MAX_MISSIONS, 'lesson');
                // the runtime holds these to tighter limits still; here they only have to be the right kind of thing, so the screen cannot be
                // handed a number where it prints a title, or a string where it walks a list
                if ($missions > 0) {
                    foreach ($lesson->missions as $i => $mission) {
                        if (! $mission instanceof \stdClass) {
                            $problems[] = "\"lesson.missions.{$i}\" must be an object.";

                            continue;
                        }
                        foreach (['id' => 64, 'kind' => 32, 'title' => 120, 'brief' => 1500] as $key => $max) {
                            $text($mission, $key, $max, false, "lesson.missions.{$i}");
                        }
                    }
                }
            }
        }

        // the 3D models and images the scene may show; a file is named by its sha256 and its row says whose it is, so here only the shape is checked
        $assets = $spec->assets ?? null;
        if ($assets !== null) {
            if (! $assets instanceof \stdClass) {
                $problems[] = '"assets" must be an object.';
            } elseif (($models = $assets->models ?? null) === null) {
                // `assets` without models is an empty list of them
            } elseif (! is_array($models) || count($models) > self::MAX_ASSET_MODELS) {
                $problems[] = '"assets.models" must be a list of up to ' . self::MAX_ASSET_MODELS . ' models or images.';
            } else {
                $seen = [];
                foreach ($models as $i => $asset) {
                    $where = "assets.models.{$i}";
                    if (! $asset instanceof \stdClass) {
                        $problems[] = "\"{$where}\" must be an object.";

                        continue;
                    }
                    $id = $text($asset, 'id', 32, true, $where);
                    if ($id !== null && ! preg_match(self::SLUG, $id)) {
                        $problems[] = "\"{$where}.id\" is lowercase letters, digits, \"-\" and \"_\", starting with a letter (32 characters at most).";
                    } elseif ($id !== null && isset($seen[$id])) {
                        $problems[] = "\"{$where}.id\" is \"{$id}\", which another model already uses.";
                    } elseif ($id !== null) {
                        $seen[$id] = true;
                    }
                    if (! is_string($asset->sha256 ?? null) || ! preg_match(self::SHA256, $asset->sha256)) {
                        $problems[] = "\"{$where}.sha256\" must be 64 lowercase hexadecimal characters.";
                    }
                    $text($asset, 'name', 120, false, $where);
                    if (property_exists($asset, 'kind') && ! in_array($asset->kind, self::ASSET_KINDS, true)) {
                        $problems[] = "\"{$where}.kind\" must be one of: " . implode(', ', self::ASSET_KINDS) . '.';
                    }
                }
            }
        }

        $meta = $spec->meta ?? null;
        $facts = ['minutes' => null, 'audience' => null, 'category' => 'General', 'tags' => null, 'difficulty' => null];
        if ($meta !== null) {
            if (! $meta instanceof \stdClass) {
                $problems[] = '"meta" must be an object.';
            } else {
                $minutes = $meta->minutes ?? null;
                if ($minutes !== null && (! is_int($minutes) || $minutes < 1 || $minutes > 120)) {
                    $problems[] = '"meta.minutes" must be a whole number from 1 to 120.';
                } else {
                    $facts['minutes'] = $minutes;
                }
                $facts['audience'] = $text($meta, 'audience', 80, false, 'meta');
                $facts['category'] = $text($meta, 'category', 40, false, 'meta') ?? 'General';
                $tags = $meta->tags ?? null;
                if ($tags !== null) {
                    if (! is_array($tags) || count($tags) > 8 || array_filter($tags, fn ($t) => ! is_string($t) || trim($t) === '' || mb_strlen($t) > 24)) {
                        $problems[] = '"meta.tags" must be up to 8 short words.';
                    } else {
                        $facts['tags'] = implode(',', array_map('trim', $tags));
                    }
                }
                $authors = $meta->authors ?? null;
                if ($authors !== null && (! is_array($authors) || count($authors) > 10 || array_filter($authors, fn ($a) => ! is_string($a) || trim($a) === '' || mb_strlen($a) > 80))) {
                    $problems[] = '"meta.authors" must be up to 10 names of 80 characters at most.';
                }
                $difficulty = $meta->difficulty ?? null;
                if ($difficulty !== null && ! in_array($difficulty, self::DIFFICULTIES, true)) {
                    $problems[] = '"meta.difficulty" must be one of: ' . implode(', ', self::DIFFICULTIES) . '.';
                } else {
                    $facts['difficulty'] = $difficulty;
                }
            }
        }

        if ($problems !== []) {
            throw new SimulationException($problems);
        }

        return [
            'spec' => $spec,
            'json' => $json,
            'facts' => $facts + [
                'slug' => $slug, 'title' => $title, 'subtitle' => $subtitle, 'summary' => $summary, 'missions' => $missions, 'assets' => $this->assetHashes($spec),
                'schema' => $schema, 'engine' => $engine, 'family' => $family,
            ],
        ];
    }

    /**
     * The first key that appears twice in one object of a JSON text, or null. Reads the raw text (json_decode keeps only the last copy and would
     * never tell): a port of the runtime's loader check (`scanJson` of its corpus test), so both refuse the same documents. A key is a string
     * followed by a colon inside an object; strings are skipped whole (a brace or colon inside one means nothing), and two spellings of one
     * name ("a" and "a") are one key. Nesting deeper than the limit stops the scan: json_decode reports that.
     */
    private function duplicateKey(string $json): ?string
    {
        $n = strlen($json);
        $open = [];   // one entry per open container: a list of the keys seen so far in an object, or null for an array
        for ($i = 0; $i < $n;) {
            $i += strcspn($json, '"{}[]', $i);
            if ($i >= $n) {
                break;
            }
            $ch = $json[$i];
            if ($ch === '"') {
                $j = $i + 1;
                while (($j += strcspn($json, '"\\', $j)) < $n && $json[$j] === '\\') {
                    $j += 2;   // an escaped character, which may be a quote
                }
                $k = $j + 1;
                $k += strspn($json, " \t\r\n", $k);
                if ($open !== [] && end($open) !== null && ($json[$k] ?? '') === ':') {
                    $name = json_decode(substr($json, $i, $j - $i + 1));
                    if (is_string($name)) {
                        if (isset($open[array_key_last($open)][$name])) {
                            return $name;
                        }
                        $open[array_key_last($open)][$name] = true;
                    }
                }
                $i = $j + 1;

                continue;
            }
            if ($ch === '{' || $ch === '[') {
                if (count($open) >= self::JSON_DEPTH) {
                    return null;
                }
                $open[] = $ch === '{' ? [] : null;
            } else {
                array_pop($open);
            }
            $i++;
        }

        return null;
    }

    /**
     * The lesson of a g2g.sim/2: `flow.steps`, 1 to 24 typed steps, of which 1 to 6 are missions (the number a card shows). Every step has a
     * type the runtime knows and an id that is its own; a mission also has the texts a card or a briefing prints. Whether the steps make
     * sense together (an intro first, a prediction about a real mission, a figure that is an image) is the runtime's check.
     *
     * @param  list<string>  $problems
     */
    private function flowMissions(\stdClass $spec, array &$problems, \Closure $text, \Closure $listSize): int
    {
        $flow = $spec->flow ?? null;
        if (! $flow instanceof \stdClass) {
            $problems[] = '"flow" is required.';

            return 0;
        }
        if ($listSize($flow, 'steps', 1, self::MAX_STEPS, 'flow') === 0) {
            return 0;
        }

        $missions = 0;
        $seen = [];
        foreach ($flow->steps as $i => $step) {
            $where = "flow.steps.{$i}";
            if (! $step instanceof \stdClass) {
                $problems[] = "\"{$where}\" must be an object.";

                continue;
            }
            $type = $step->type ?? null;
            if (! in_array($type, self::STEP_TYPES, true)) {
                $problems[] = "\"{$where}.type\" must be one of: " . implode(', ', self::STEP_TYPES) . '.';
            }
            $id = $text($step, 'id', 32, true, $where);
            if ($id !== null && ! preg_match(self::SLUG, $id)) {
                $problems[] = "\"{$where}.id\" is lowercase letters, digits, \"-\" and \"_\", starting with a letter (32 characters at most).";
            } elseif ($id !== null && isset($seen[$id])) {
                $problems[] = "\"{$where}.id\" is \"{$id}\", which another step already uses.";
            } elseif ($id !== null) {
                $seen[$id] = true;
            }
            if ($type === 'mission') {
                $missions++;
                foreach (['kind' => 32, 'title' => 120, 'brief' => 1500] as $key => $max) {
                    $text($step, $key, $max, false, $where);
                }
            }
        }
        if ($missions === 0) {
            $problems[] = '"flow.steps" needs at least one step of type "mission".';
        } elseif ($missions > self::MAX_MISSIONS) {
            $problems[] = "\"flow.steps\" has {$missions} steps of type \"mission\"; the most is " . self::MAX_MISSIONS . '.';
        }

        return $missions;
    }

    /**
     * The `views` of a g2g.sim/2: up to two (one for a des engine), each of a kind the runtime draws, no kind twice. None is allowed here
     * because a v1 document upgraded in memory may have had no scene; the runtime says when an engine needs one.
     *
     * @param  list<string>  $problems
     */
    private function checkViews(\stdClass $spec, ?string $engine, array &$problems): void
    {
        $views = $spec->views ?? null;
        if ($views === null) {
            return;
        }
        $max = $engine === 'des' ? self::MAX_VIEWS_DES : self::MAX_VIEWS;
        if (! is_array($views) || count($views) > $max) {
            $problems[] = "\"views\" must be a list of 0 to {$max} items.";

            return;
        }
        $seen = [];
        foreach ($views as $i => $view) {
            if (! $view instanceof \stdClass) {
                $problems[] = "\"views.{$i}\" must be an object.";

                continue;
            }
            $kind = $view->kind ?? null;
            if (! in_array($kind, self::VIEW_KINDS, true)) {
                $problems[] = "\"views.{$i}.kind\" must be one of: " . implode(', ', self::VIEW_KINDS) . '.';
            } elseif (isset($seen[$kind])) {
                $problems[] = "\"views.{$i}.kind\" is \"{$kind}\", which another view already uses.";
            } else {
                $seen[$kind] = true;
            }
        }
    }

    /**
     * The `world` of a rigid engine (its bodies, joints and forces) is there for that engine and no other. What is inside it is the runtime's.
     *
     * @param  list<string>  $problems
     */
    private function checkWorld(\stdClass $spec, ?string $engine, array &$problems): void
    {
        $world = $spec->world ?? null;
        if ($engine === 'rigid' && ! $world instanceof \stdClass) {
            $problems[] = '"world" is required for the "rigid" engine: its bodies, joints and forces.';
        } elseif ($engine !== null && $engine !== 'rigid' && $world !== null) {
            $problems[] = '"world" belongs to the "rigid" engine only.';
        }
    }

    /**
     * The sha256 of every model a decoded specification names in `assets.models`, in order and without repeats. Reads what is there and skips
     * anything malformed (parse() is what refuses it), so a version saved before this was checked can still be shown.
     *
     * @return list<string>
     */
    public function assetHashes(mixed $spec): array
    {
        $models = $spec instanceof \stdClass && ($spec->assets ?? null) instanceof \stdClass ? ($spec->assets->models ?? null) : null;
        $hashes = [];
        foreach (is_array($models) ? $models : [] as $model) {
            $hash = $model instanceof \stdClass ? ($model->sha256 ?? null) : null;
            if (is_string($hash) && preg_match(self::SHA256, $hash)) {
                $hashes[$hash] = $hash;
            }
        }

        return array_values($hashes);
    }

    // ---------------------------------------------------------------- reads

    /** The link columns a list may be filtered by; all of them must hold on ONE link of the simulation. */
    public const LINK_FILTERS = ['chapter_id', 'topic_id', 'concept_id', 'standard_id', 'subject_id'];

    /**
     * The simulations a caller can see, in gallery order.
     *
     * `$tenants` is PrayogshalaService::visibleTenants() of the caller's tenant (the controller decides it; it never comes from a request body).
     * Every caller gets the published, undeleted simulations of those tenants. With no filter that is all of them. A filter (`chapter_id`, `topic_id`,
     * `concept_id`, `standard_id`, `subject_id`) is matched against the simulation's links: published links only for a learner (`$staff` false), links of
     * any review status for staff. A simulation NOT MAPPED anywhere yet (no published link at all) also matches a chapter, standard or subject filter,
     * so it is visible in every chapter's tab, flagged "not mapped yet"; a topic or concept filter never matches it. Only an AUTHOR (`$drafts`: a platform-tenant staff
     * member) sees draft and archived simulations; staff of another tenant see published ones, so unfinished work stays with its authors.
     *
     * @param  list<int>  $tenants
     * @param  array<string, int|null>  $filters  any of LINK_FILTERS
     * @return Collection<int, object>
     */
    public function visible(array $tenants, bool $staff = false, ?string $search = null, array $filters = [], bool $drafts = false): Collection
    {
        return $this->visibleQuery($tenants, $staff, $search, $filters, $drafts)->orderBy('s.sort_order')->orderBy('s.title')->orderBy('s.id')->select('s.*')->get();
    }

    /**
     * @param  list<int>  $tenants
     * @param  array<string, int|null>  $filters
     */
    private function visibleQuery(array $tenants, bool $staff, ?string $search, array $filters, bool $drafts): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('lms_simulation as s')->whereNull('s.deleted_at')->whereIn('s.sub_institute_id', $tenants === [] ? [0] : $tenants);

        if (! $drafts) {
            $q->where('s.status', 'published');
        }
        $linkFilters = array_filter(array_intersect_key($filters, array_flip(self::LINK_FILTERS)), fn ($v) => $v !== null);
        if ($linkFilters !== []) {
            // A topic or a concept is a precise place: only a simulation linked THERE matches it. A chapter, standard or subject is a broad place: a simulation that
            // is not mapped anywhere yet (no published link at all) also shows there, flagged "not mapped yet", so it is not hidden while its mapping is reviewed.
            $preciseOnly = isset($linkFilters['topic_id']) || isset($linkFilters['concept_id']);
            $q->where(function ($w) use ($staff, $linkFilters, $preciseOnly) {
                $w->whereExists(function ($e) use ($staff, $linkFilters) {
                    $e->select(DB::raw(1))->from('lms_simulation_concept as l')->whereColumn('l.simulation_id', 's.id');
                    if (! $staff) {
                        $e->where('l.review_status', 'published');
                    }
                    foreach ($linkFilters as $column => $value) {   // $column is one of LINK_FILTERS, never request text
                        $e->where('l.' . $column, (int) $value);
                    }
                });
                if (! $preciseOnly) {
                    $w->orWhereNotExists(function ($e) {
                        $e->select(DB::raw(1))->from('lms_simulation_concept as p')->whereColumn('p.simulation_id', 's.id')->where('p.review_status', 'published');
                    });
                }
            });
        }
        if ($search !== null && trim($search) !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], trim($search)) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('s.title', 'like', $like)->orWhere('s.subtitle', 'like', $like)->orWhere('s.summary', 'like', $like)
                    ->orWhere('s.tags', 'like', $like)->orWhere('s.category', 'like', $like);
            });
        }

        return $q;
    }

    /**
     * One simulation, if the caller may see it (see visible()).
     *
     * @param  list<int>  $tenants
     */
    public function find(int $id, array $tenants, bool $staff = false, bool $drafts = false): ?object
    {
        return $this->visibleQuery($tenants, $staff, null, [], $drafts)->where('s.id', $id)->select('s.*')->first();
    }

    /** One simulation this TENANT owns (the only kind it may edit or delete), deleted or not when `$withTrashed`. The caller also checks the tenant may author. */
    public function owned(int $id, int $tenant, bool $withTrashed = false): ?object
    {
        $q = DB::table('lms_simulation')->where('id', $id)->where('sub_institute_id', $tenant);
        if (! $withTrashed) {
            $q->whereNull('deleted_at');
        }

        return $q->first();
    }

    /** The specification text of a version (the current one unless named). */
    public function specJson(object $sim, ?int $version = null): ?string
    {
        $v = $version ?? (int) $sim->current_version;

        return DB::table('lms_simulation_version')->where('simulation_id', $sim->id)->where('version', $v)->value('spec');
    }

    /** @return Collection<int, object> */
    public function versions(int $simulationId): Collection
    {
        return DB::table('lms_simulation_version')->where('simulation_id', $simulationId)->orderByDesc('version')
            ->select('version', 'sha256', 'note', 'created_by', 'created_at')->get();
    }

    // ---------------------------------------------------------------- assets

    /**
     * The registered model assets a simulation may use among `$hashes`, keyed by sha256: its owner's own, or a platform one (a platform
     * simulation therefore uses platform assets only). Removed assets do not count. The file behind a hash that is NOT among these is never
     * served to this simulation, which is what stops one tenant fetching another's model by naming its hash. Where an organisation
     * and the platform both hold the same file, the organisation's row (its own licence and credit) is the one used.
     *
     * @param  list<string>  $hashes
     * @return Collection<string, object>
     */
    public function assetRows(object $sim, array $hashes): Collection
    {
        if ($hashes === []) {
            return collect();
        }
        $owner = $sim->sub_institute_id === null ? null : (int) $sim->sub_institute_id;

        return $this->visibleAssets($owner, $hashes)
            ->get()->sortBy(fn ($r) => $r->sub_institute_id === null ? 0 : 1)->keyBy('sha256');   // keyBy keeps the last of a hash: the organisation's
    }

    /**
     * The registry rows among `$hashes` that a simulation of `$owner` (an organisation, or null for a platform simulation) may use: its own and
     * the platform's, or the platform's alone. Removed assets are not there. The one rule behind both what is served and what may be saved.
     *
     * @param  list<string>  $hashes
     */
    private function visibleAssets(?int $owner, array $hashes): \Illuminate\Database\Query\Builder
    {
        return DB::table('lms_simulation_asset')->whereNull('deleted_at')->whereIn('sha256', $hashes)
            ->where(fn ($w) => $owner === null ? $w->whereNull('sub_institute_id') : $w->whereNull('sub_institute_id')->orWhere('sub_institute_id', $owner));
    }

    /**
     * REFERENCE INTEGRITY. Every model or image a specification names must be a registered asset its owner may use (its own, or the
     * platform's); the first one that is not stops the save with a sentence that names it. Without this a specification could point at a file
     * that was never uploaded (the player would show an empty scene) or at another organisation's (the file would never be served, but the
     * mistake would not be found until a learner opened it).
     *
     * @throws SimulationException
     */
    private function assertAssetsRegistered(?int $owner, \stdClass $spec): void
    {
        $problems = $this->assetProblems($owner, $spec);
        if ($problems !== []) {
            throw new SimulationException($problems);
        }
    }

    /**
     * The sentences that say which files a specification names that its owner may not use (never registered, removed, or another tenant's). Empty
     * when every file is fine. Read-only: the importer's dry run asks this without writing anything.
     *
     * @return list<string>
     */
    public function assetProblems(?int $owner, \stdClass $spec): array
    {
        $hashes = $this->assetHashes($spec);
        if ($hashes === []) {
            return [];
        }
        $known = array_flip($this->visibleAssets($owner, $hashes)->pluck('sha256')->all());
        $problems = [];
        foreach ($spec->assets->models as $i => $asset) {
            if (! isset($known[$asset->sha256])) {
                $problems[] = "The file \"{$asset->id}\" (assets.models.{$i}) has not been uploaded for this tenant, or it has been removed. Register it first (lms:simulations-assets-copy, or an upload), and use its sha256.";
            }
        }

        return $problems;
    }

    /**
     * What the player shows as credits for the models and images a specification names: one entry per registered asset, in the order the
     * specification names them. A name with no registered asset is left out (there is nothing to credit); the storage path is never part of it.
     *
     * @param  list<string>  $hashes
     * @return list<array{sha256: string, kind: string, name: string, bytes: int, licence: ?string, credit: ?string, source_url: ?string}>
     */
    public function assetCredits(object $sim, array $hashes): array
    {
        $rows = $this->assetRows($sim, $hashes);
        $out = [];
        foreach ($hashes as $hash) {
            if ($r = $rows->get($hash)) {
                $out[] = ['sha256' => $r->sha256, 'kind' => $r->kind, 'name' => $r->name, 'bytes' => (int) $r->bytes, 'licence' => $r->licence, 'credit' => $r->credit, 'source_url' => $r->source_url];
            }
        }

        return $out;
    }

    /** Where an asset's file is kept on the disk: `k12_sim_assets/{sha256}.{glb|png|jpg|webp|hdr}` (see assetType()). Never under hp_erp's `sim_assets/`. */
    public function assetPath(string $sha256, string $extension = 'glb'): string
    {
        return self::ASSET_PREFIX . '/' . $sha256 . '.' . $extension;
    }

    /**
     * What a file is, read from its FIRST BYTES and never from its name: a binary glTF, a PNG, a JPEG, a WebP (RIFF, then WEBP) or a Radiance HDR
     * ("#?RADIANCE" or "#?RGBE" and a newline). Anything
     * else - SVG, GIF, HTML, a file that merely carries an image extension - is null. The answer is what the file may be stored as: its
     * kind and mime, the extension it is stored under (`ext`) and the extensions a file of this kind is allowed to be named with (`exts`),
     * the size limit and a label. At least 12 bytes are needed, the length of the glTF header.
     *
     * @return array{kind: string, mime: string, ext: string, exts: list<string>, max: int, label: string}|null
     */
    public function assetType(string $head): ?array
    {
        if (strlen($head) < 12) {
            return null;
        }
        if (str_starts_with($head, 'glTF')) {
            return ['kind' => 'model', 'mime' => 'model/gltf-binary', 'ext' => 'glb', 'exts' => ['glb'], 'max' => self::MAX_ASSET_BYTES, 'label' => 'a binary glTF model'];
        }
        if (str_starts_with($head, "#?RADIANCE\n") || str_starts_with($head, "#?RGBE\n")) {
            return ['kind' => 'hdr', 'mime' => 'image/vnd.radiance', 'ext' => 'hdr', 'exts' => ['hdr'], 'max' => self::MAX_HDR_BYTES, 'label' => 'a Radiance HDR image'];
        }
        $image = ['kind' => 'image', 'max' => self::MAX_IMAGE_BYTES];
        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            return $image + ['mime' => 'image/png', 'ext' => 'png', 'exts' => ['png'], 'label' => 'a PNG image'];
        }
        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return $image + ['mime' => 'image/jpeg', 'ext' => 'jpg', 'exts' => ['jpg', 'jpeg'], 'label' => 'a JPEG image'];
        }
        if (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
            return $image + ['mime' => 'image/webp', 'ext' => 'webp', 'exts' => ['webp'], 'label' => 'a WebP image'];
        }

        return null;
    }

    // ---------------------------------------------------------------- writes

    /**
     * Saves a new simulation owned by `$tenant` (the platform tenant: the controller and the importer check that) as version 1.
     *
     * @param  array{status?: string, category?: string, tags?: array|string|null, cover_url?: string|null, difficulty?: string|null, sort_order?: int, note?: string|null}  $options
     */
    public function create(int $tenant, ?int $userId, string $json, array $options = []): object
    {
        $parsed = $this->parse($json);
        $facts = $parsed['facts'];
        $status = $options['status'] ?? 'published';
        if (! in_array($status, self::STATUSES, true)) {
            throw new SimulationException(['"status" must be one of: ' . implode(', ', self::STATUSES) . '.']);
        }

        $clash = DB::table('lms_simulation')->where('slug', $facts['slug'])->whereNull('deleted_at')
            ->where('sub_institute_id', $tenant)->first();
        if ($clash) {
            throw new SimulationException(["A simulation with the id \"{$facts['slug']}\" already exists here. Open it and save a new version instead."], 409);
        }
        $this->assertAssetsRegistered($tenant, $parsed['spec']);

        $now = now();

        return DB::transaction(function () use ($tenant, $userId, $parsed, $facts, $status, $options, $now) {
            $id = DB::table('lms_simulation')->insertGetId([
                'sub_institute_id' => $tenant,
                'slug' => $facts['slug'],
                'title' => $facts['title'],
                'subtitle' => $facts['subtitle'],
                'summary' => $facts['summary'],
                'category' => $this->category($options['category'] ?? null) ?? $facts['category'],
                'tags' => $this->tags($options['tags'] ?? null) ?? $facts['tags'],
                'audience' => $facts['audience'],
                'minutes' => $facts['minutes'],
                'difficulty' => $this->difficulty($options['difficulty'] ?? null) ?? $facts['difficulty'],
                'cover_url' => $this->coverUrl($options['cover_url'] ?? null),
                'status' => $status,
                'current_version' => 1,
                'missions' => $facts['missions'],
                'sort_order' => $this->sortOrder($options['sort_order'] ?? 0),
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('lms_simulation_version')->insert([
                'simulation_id' => $id,
                'version' => 1,
                'spec' => $parsed['json'],
                'sha256' => hash('sha256', $parsed['json']),
                'note' => $this->note($options['note'] ?? null),
                'created_by' => $userId,
                'created_at' => $now,
            ]);

            return DB::table('lms_simulation')->where('id', $id)->first();
        });
    }

    /**
     * Saves a new version of a simulation's specification. The simulation's own id may not change (it is how progress and links find it), and
     * saving the same document as the one that is already current changes nothing - however either of them was laid out when it was saved.
     *
     * `$makeCurrent = false` only keeps the version: the one that is played and what the card says stay as they are until promote() switches
     * (for a file that needs a newer player than the one deployed). Then the document it is compared with is the newest saved version, so
     * keeping the same file twice changes nothing either.
     */
    public function addVersion(object $sim, ?int $userId, string $json, ?string $note = null, bool $makeCurrent = true): object
    {
        $parsed = $this->parse($json);
        $facts = $parsed['facts'];
        if ($facts['slug'] !== $sim->slug) {
            throw new SimulationException(["The specification's id is \"{$facts['slug']}\" but this simulation is \"{$sim->slug}\". The id cannot change."]);
        }

        // Compared as canonical text, the stored one re-canonicalised: a version saved before specifications were normalised (or imported
        // from a pretty-printed file) holds the file's own text and sha256, and must not get a twin the next time the same document arrives.
        $newest = (int) DB::table('lms_simulation_version')->where('simulation_id', $sim->id)->max('version');
        if ($this->sameDocument($this->specJson($sim, $makeCurrent ? null : $newest), $parsed['json'])) {
            return $sim;
        }
        $this->assertAssetsRegistered($sim->sub_institute_id === null ? null : (int) $sim->sub_institute_id, $parsed['spec']);
        $hash = hash('sha256', $parsed['json']);

        return DB::transaction(function () use ($sim, $userId, $parsed, $facts, $hash, $note, $makeCurrent) {
            $next = (int) DB::table('lms_simulation_version')->where('simulation_id', $sim->id)->max('version') + 1;
            DB::table('lms_simulation_version')->insert([
                'simulation_id' => $sim->id,
                'version' => $next,
                'spec' => $parsed['json'],
                'sha256' => $hash,
                'note' => $this->note($note),
                'created_by' => $userId,
                'created_at' => now(),
            ]);
            if ($makeCurrent) {
                $this->play($sim->id, $userId, $facts, $next);
            }

            return DB::table('lms_simulation')->where('id', $sim->id)->first();
        });
    }

    /**
     * Makes a saved version the one that is played, and the card say what that specification says. It is how a version kept with
     * `addVersion(..., makeCurrent: false)` is switched on once the player that can run it is deployed, and how an earlier version is brought
     * back. The version goes through the checks of a save again, so a file the rules have since refused, or a model that has since been
     * removed, is not switched on.
     *
     * @throws SimulationException
     */
    public function promote(object $sim, ?int $userId, int $version): object
    {
        if ((int) $sim->current_version === $version) {
            return $sim;
        }
        $json = $this->specJson($sim, $version);
        if ($json === null) {
            throw new SimulationException(["\"{$sim->slug}\" has no version {$version}."]);
        }
        $parsed = $this->parse($json);
        $this->assertAssetsRegistered($sim->sub_institute_id === null ? null : (int) $sim->sub_institute_id, $parsed['spec']);

        return DB::transaction(function () use ($sim, $userId, $parsed, $version) {
            $this->play($sim->id, $userId, $parsed['facts'], $version);

            return DB::table('lms_simulation')->where('id', $sim->id)->first();
        });
    }

    /** @param  array<string, mixed>  $facts  what parse() read from the specification */
    private function play(int $simulationId, ?int $userId, array $facts, int $version): void
    {
        DB::table('lms_simulation')->where('id', $simulationId)->update([
            'title' => $facts['title'],
            'subtitle' => $facts['subtitle'],
            'summary' => $facts['summary'],
            'audience' => $facts['audience'],
            'minutes' => $facts['minutes'],
            'missions' => $facts['missions'],
            'current_version' => $version,
            'updated_by' => $userId,
            'updated_at' => now(),
        ]);
    }

    /**
     * Changes what the card says and whether the simulation is offered; never the specification.
     *
     * @param  array<string, mixed>  $fields  any of category, tags, cover_url, difficulty, status, sort_order
     */
    public function updateCard(object $sim, ?int $userId, array $fields): object
    {
        $set = [];
        if (array_key_exists('category', $fields)) {
            $set['category'] = $this->category($fields['category']) ?? 'General';
        }
        if (array_key_exists('tags', $fields)) {
            $set['tags'] = $this->tags($fields['tags']);
        }
        if (array_key_exists('cover_url', $fields)) {
            $set['cover_url'] = $this->coverUrl($fields['cover_url']);
        }
        if (array_key_exists('difficulty', $fields)) {
            $set['difficulty'] = $this->difficulty($fields['difficulty']);
        }
        if (array_key_exists('status', $fields)) {
            if (! in_array($fields['status'], self::STATUSES, true)) {
                throw new SimulationException(['"status" must be one of: ' . implode(', ', self::STATUSES) . '.']);
            }
            $set['status'] = $fields['status'];
        }
        if (array_key_exists('sort_order', $fields)) {
            $set['sort_order'] = $this->sortOrder($fields['sort_order']);
        }
        if ($set !== []) {
            DB::table('lms_simulation')->where('id', $sim->id)->update($set + ['updated_by' => $userId, 'updated_at' => now()]);
        }

        return DB::table('lms_simulation')->where('id', $sim->id)->first();
    }

    public function softDelete(object $sim, ?int $userId): void
    {
        DB::table('lms_simulation')->where('id', $sim->id)->update(['deleted_at' => now(), 'deleted_by' => $userId, 'updated_at' => now()]);
    }

    /** @throws SimulationException  409 when another live simulation of the tenant has taken the id since this one was deleted */
    public function restore(object $sim, ?int $userId): void
    {
        $clash = DB::table('lms_simulation')->where('slug', $sim->slug)->where('sub_institute_id', $sim->sub_institute_id)
            ->where('id', '!=', $sim->id)->whereNull('deleted_at')->first();
        if ($clash) {
            throw new SimulationException(["Another simulation (id {$clash->id}) now uses the id \"{$sim->slug}\", so this one cannot be restored. Delete or rename that one first, or save a new version of it instead."], 409);
        }
        DB::table('lms_simulation')->where('id', $sim->id)->update(['deleted_at' => null, 'deleted_by' => null, 'updated_by' => $userId, 'updated_at' => now()]);
    }

    // ---------------------------------------------------------------- progress

    /**
     * Where one person stands in each of the given simulations.
     *
     * @param  list<int>  $ids
     * @return array<int, object>  keyed by simulation id
     */
    public function progressMap(int $tenant, int $userId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('lms_simulation_progress')->where('sub_institute_id', $tenant)->where('user_id', $userId)
            ->whereIn('simulation_id', $ids)->get()->keyBy('simulation_id')->all();
    }

    /**
     * Records what the runtime reported. Progress only moves forward: a lower percent than the one already held is ignored, and once a
     * simulation is completed it stays completed (replaying it does not un-complete it).
     *
     * @param  array{percent?: int, step?: int, status?: string, result?: mixed}  $report
     */
    public function recordProgress(object $sim, int $tenant, int $userId, array $report): object
    {
        $percent = max(0, min(100, (int) ($report['percent'] ?? 0)));
        $step = max(0, min(1000, (int) ($report['step'] ?? 0)));
        $completed = ($report['status'] ?? '') === 'completed' || $percent >= 100;
        $result = $report['result'] ?? null;
        $resultText = $result === null ? null : mb_substr(is_string($result) ? $result : (string) json_encode($result), 0, 4000);
        $now = now();

        // Two reports from one player can be in flight together (the timed save and the completion). So the row is first created empty if it
        // is not there - the unique index lets exactly one creator win and the others carry on - and then read under a lock, which makes each
        // report apply to what the one before it left, in whatever order they arrive. Without that, both could try to create it (one fails),
        // or a stale read could write "in progress" over a completion. The retries are for the deadlock InnoDB may pick when two reports
        // reach the lock together.
        return DB::transaction(function () use ($sim, $tenant, $userId, $percent, $step, $completed, $resultText, $now) {
            DB::table('lms_simulation_progress')->insertOrIgnore([
                'sub_institute_id' => $tenant,
                'simulation_id' => $sim->id,
                'version' => (int) $sim->current_version,
                'user_id' => $userId,
                'status' => 'in_progress',
                'step' => 0,
                'percent' => 0,
                'result' => null,
                'started_at' => $now,
                'last_seen_at' => $now,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $row = DB::table('lms_simulation_progress')->where('simulation_id', $sim->id)->where('user_id', $userId)->lockForUpdate()->first();

            $stays = $row->status === 'completed';
            DB::table('lms_simulation_progress')->where('id', $row->id)->update([
                'version' => (int) $sim->current_version,
                'status' => $stays || $completed ? 'completed' : 'in_progress',
                'step' => $stays ? $row->step : max((int) $row->step, $step),
                'percent' => $stays ? 100 : max((int) $row->percent, $completed ? 100 : $percent),
                'result' => $resultText ?? $row->result,
                'last_seen_at' => $now,
                'completed_at' => $row->completed_at ?? ($completed ? $now : null),
                'updated_at' => $now,
            ]);

            return DB::table('lms_simulation_progress')->where('id', $row->id)->first();
        }, 3);
    }

    // ---------------------------------------------------------------- shapes

    /** The card of a simulation, as the gallery draws it (no specification). */
    public function card(object $s, ?object $progress = null, bool $withSnapshot = false): array
    {
        return [
            'id' => (int) $s->id,
            'slug' => $s->slug,
            'title' => $s->title,
            'subtitle' => $s->subtitle,
            'summary' => $s->summary,
            'category' => $s->category,
            'category_key' => $this->categoryKey($s->category),
            'tags' => $s->tags === null || $s->tags === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $s->tags)))),
            'audience' => $s->audience,
            'minutes' => $s->minutes === null ? null : (int) $s->minutes,
            'difficulty' => $s->difficulty,
            'cover_url' => $s->cover_url,
            'status' => $s->status,
            'version' => (int) $s->current_version,
            'missions' => (int) $s->missions,
            'owner_tenant' => $s->sub_institute_id === null ? null : (int) $s->sub_institute_id,
            'scope' => $s->sub_institute_id === null || in_array((int) $s->sub_institute_id, array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1])), true) ? 'platform' : 'tenant',
            'updated_at' => $s->updated_at,
            'progress' => $progress ? [
                'status' => $progress->status,
                'percent' => (int) $progress->percent,
                'step' => (int) $progress->step,
                'completed_at' => $progress->completed_at,
            ] + ($withSnapshot ? ['snapshot' => $progress->result === null ? null : json_decode($progress->result)] : []) : null,
        ];
    }

    /**
     * The filter chips of the gallery: one per category in use, counted, in the order they first appear. Made from the data, never from a
     * fixed list, so a new category appears the moment a simulation uses it. A chip's `key` is the `category_key` of the cards it counted.
     *
     * @param  iterable<object>  $rows
     * @return list<array{key: string, label: string, count: int}>
     */
    public function categories(iterable $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $label = trim((string) $r->category) === '' ? 'General' : trim((string) $r->category);
            $key = $this->categoryKey($r->category);
            $out[$key] = ['key' => $key, 'label' => $out[$key]['label'] ?? $label, 'count' => ($out[$key]['count'] ?? 0) + 1];
        }

        return array_values($out);
    }

    // ---------------------------------------------------------------- small cleaners

    /**
     * What a category is filtered by (a blank one is "General"). The one place it is worked out: the chip and every card it counts carry this
     * same value, because PHP's and JavaScript's trim and lower-case disagree on a few characters and the screen must not redo it.
     */
    private function categoryKey(?string $category): string
    {
        $key = mb_strtolower(trim((string) $category));

        return $key === '' ? 'general' : $key;
    }

    /** A decoded specification as canonical text. @throws \JsonException */
    private function canonical(mixed $spec): string
    {
        return json_encode($spec, self::JSON_FLAGS | JSON_THROW_ON_ERROR);
    }

    /** Whether stored specification text is the same document as `$canonical`, however it was laid out when it was stored. */
    private function sameDocument(?string $stored, string $canonical): bool
    {
        if ($stored === null) {
            return false;
        }
        try {
            return $this->canonical(json_decode($stored, false, self::PARSE_DEPTH, JSON_THROW_ON_ERROR)) === $canonical;
        } catch (\JsonException) {
            return false;
        }
    }

    /** One of the known difficulties, or none. The rule for a new simulation and for an edit alike. */
    private function difficulty(mixed $v): ?string
    {
        if ($v !== null && ! in_array($v, self::DIFFICULTIES, true)) {
            throw new SimulationException(['"difficulty" must be one of: ' . implode(', ', self::DIFFICULTIES) . '.']);
        }

        return $v;
    }

    /** Where a card sits in the gallery: a whole number held to what the column stores. The rule for a new simulation and for an edit alike. */
    private function sortOrder(mixed $v): int
    {
        return max(0, min(65000, (int) $v));
    }

    private function category(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === null || $v === '' ? null : mb_substr($v, 0, 60);
    }

    /** @param  array<int, mixed>|string|null  $v */
    private function tags(array|string|null $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $list = is_array($v) ? $v : explode(',', $v);
        $clean = array_slice(array_values(array_filter(array_map(fn ($t) => mb_substr(trim((string) $t), 0, 24), $list))), 0, 8);

        return $clean === [] ? null : implode(',', $clean);
    }

    /** An https address or a path on this site; anything else (javascript:, data:, a file) is refused. */
    private function coverUrl(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);
        if ($v === null || $v === '') {
            return null;
        }
        if (mb_strlen($v) > 500 || str_starts_with($v, '//') || ! preg_match('#^(https://[^\s"\'<>]+|/[A-Za-z0-9._~/%-]+)$#', $v)) {
            throw new SimulationException(['"cover_url" must be an https address or a path on this site.']);
        }

        return $v;
    }

    private function note(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === null || $v === '' ? null : mb_substr($v, 0, 255);
    }
}
