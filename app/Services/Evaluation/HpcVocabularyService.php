<?php

namespace App\Services\Evaluation;

use App\Domain\Exam\HpcBlueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The option lists one school's HPC blueprints may choose from.
 *
 * Resolution is per option type, not per school: a school with no rows for
 * `assessor` gets the published NCERT list, and saving its own replaces the
 * published one for that type alone. Its activity approaches carry on using
 * the default until it customises those too.
 *
 * That is what makes this safe to ship without seeding anything: every school
 * already works, corrections to a published list still reach everyone who has
 * not overridden it, and a school's customisation reads as the short list of
 * things it actually decided rather than a copy of defaults it never saw.
 *
 * Nothing here is cached. The lists are tiny, read once per blueprint request,
 * and a stale vocabulary would show a coordinator a picker that disagrees with
 * what the server will accept.
 */
class HpcVocabularyService
{
    public const TYPE_ASSESSOR = 'assessor';
    public const TYPE_ACTIVITY_APPROACH = 'activity_approach';
    public const TYPE_EVIDENCE_MODE = 'evidence_mode';
    public const TYPE_PART_A_ELEMENT = 'part_a_element';

    /**
     * Option type => the definition key it governs, and its published default.
     *
     * The stage list is deliberately absent. NEP 2020's 5+3+3+4 is a national
     * framework, not a school preference — a school inventing a fifth stage
     * would produce a card no board could read.
     */
    public const TYPES = [
        self::TYPE_ASSESSOR => 'assessors',
        self::TYPE_ACTIVITY_APPROACH => 'activity_approaches',
        self::TYPE_EVIDENCE_MODE => 'evidence_modes',
        self::TYPE_PART_A_ELEMENT => 'part_a',
    ];

    /** @return array<string,array<string,string>> published defaults, code => label */
    public static function publishedDefaults(): array
    {
        return [
            self::TYPE_ASSESSOR => HpcBlueprint::ASSESSORS,
            self::TYPE_ACTIVITY_APPROACH => HpcBlueprint::ACTIVITY_APPROACHES,
            self::TYPE_EVIDENCE_MODE => HpcBlueprint::EVIDENCE_MODES,
            self::TYPE_PART_A_ELEMENT => HpcBlueprint::PART_A_ELEMENTS,
        ];
    }

    /**
     * One school's effective vocabulary: its own rows where it has them, the
     * published list everywhere else.
     *
     * @return array<string,array<int,array{code:string,label:string,description:string,is_custom:bool,is_default:bool}>>
     */
    public function forTenant(int $tenantId): array
    {
        $overrides = $this->overridesFor($tenantId);
        $vocabulary = [];

        foreach (self::publishedDefaults() as $type => $defaults) {
            if (isset($overrides[$type])) {
                $vocabulary[$type] = $overrides[$type];

                continue;
            }

            $vocabulary[$type] = [];

            foreach ($defaults as $code => $label) {
                $vocabulary[$type][] = [
                    'code' => $code,
                    'label' => $label,
                    'description' => '',
                    'is_custom' => false,
                    'is_default' => true,
                ];
            }
        }

        return $vocabulary;
    }

    /**
     * The same thing as plain code => label maps, which is the shape
     * HpcBlueprint::normalize() validates against.
     *
     * @return array<string,array<string,string>>
     */
    public function codeMapsFor(int $tenantId): array
    {
        $maps = [];

        foreach ($this->forTenant($tenantId) as $type => $options) {
            $maps[$type] = [];

            foreach ($options as $option) {
                $maps[$type][$option['code']] = $option['label'];
            }
        }

        return $maps;
    }

    /** Which option types this school has taken over from the published list. */
    public function customisedTypes(int $tenantId): array
    {
        return array_keys($this->overridesFor($tenantId));
    }

    /**
     * Replaces one school's list for one option type.
     *
     * Replace-all rather than row-by-row edits because the list IS the unit a
     * coordinator thinks in: they open "Who can assess", arrange it, and save.
     * Codes are preserved across a save wherever the caller sends them back, so
     * a blueprint that already selected `peer` keeps working when the label is
     * changed to "Classmate".
     *
     * @param  array<int,array{code?:string,label?:string,description?:string,status?:bool}>  $options
     * @return array<int,array<string,mixed>> the school's list as it now stands
     */
    public function replace(int $tenantId, string $type, array $options, ?int $userId = null): array
    {
        if (! isset(self::TYPES[$type]) || ! Schema::hasTable('hpc_school_option')) {
            return [];
        }

        $defaults = self::publishedDefaults()[$type];
        $now = now();
        $rows = [];
        $seen = [];
        $position = 0;

        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }

            $label = trim((string) ($option['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $code = $this->normalizeCode((string) ($option['code'] ?? ''), $label);

            // A duplicated code would violate the unique index and, worse, make
            // two visibly different options the same option to every blueprint.
            if (isset($seen[$code])) {
                $code = $code.'_'.(++$position);
            }

            $seen[$code] = true;

            $rows[] = [
                'sub_institute_id' => $tenantId,
                'option_type' => $type,
                'code' => $code,
                'label' => mb_substr($label, 0, 191),
                'description' => mb_substr(trim((string) ($option['description'] ?? '')), 0, 500) ?: null,
                'sort_order' => $position++,
                'status' => ! array_key_exists('status', $option) || (bool) $option['status'],
                // A code the published list also has is the school keeping a
                // standard option, however they have relabelled it.
                'is_custom' => ! isset($defaults[$code]),
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($tenantId, $type, $rows) {
            DB::table('hpc_school_option')
                ->where('sub_institute_id', $tenantId)
                ->where('option_type', $type)
                ->delete();

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('hpc_school_option')->insert($chunk);
            }
        });

        return $this->forTenant($tenantId)[$type] ?? [];
    }

    /**
     * Drops a school's list for one type, so it follows the published one again.
     *
     * Blueprints are untouched: a blueprint holding a code the school invented
     * keeps it in storage, and simply stops offering it in the picker. That is
     * the right way round — losing a coordinator's selections because somebody
     * reset a list would be the worse failure.
     */
    public function resetToDefault(int $tenantId, string $type): void
    {
        if (! isset(self::TYPES[$type]) || ! Schema::hasTable('hpc_school_option')) {
            return;
        }

        DB::table('hpc_school_option')
            ->where('sub_institute_id', $tenantId)
            ->where('option_type', $type)
            ->delete();
    }

    /**
     * A school's stored rows, grouped by type. A type with no rows is absent
     * from the result, which is what makes the fallback per type.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function overridesFor(int $tenantId): array
    {
        if (! $tenantId || ! Schema::hasTable('hpc_school_option')) {
            return [];
        }

        $grouped = [];

        $rows = DB::table('hpc_school_option')
            ->where('sub_institute_id', $tenantId)
            ->orderBy('option_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            // A retired option stays out of the picker but its type still counts
            // as customised -- otherwise disabling every option in a list would
            // silently restore the published one.
            $grouped[$row->option_type] ??= [];

            if (! $row->status) {
                continue;
            }

            $grouped[$row->option_type][] = [
                'code' => (string) $row->code,
                'label' => (string) $row->label,
                'description' => (string) ($row->description ?? ''),
                'is_custom' => (bool) $row->is_custom,
                'is_default' => false,
            ];
        }

        return $grouped;
    }

    /**
     * A stable machine name.
     *
     * Derived from the label only when the caller sends no code — an existing
     * option keeps the code it already has, so relabelling never breaks the
     * blueprints that selected it.
     */
    private function normalizeCode(string $code, string $label): string
    {
        $source = $code !== '' ? $code : $label;
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $source) ?? $source);
        $slug = trim($slug, '_');

        return mb_substr($slug !== '' ? $slug : 'option', 0, 60);
    }
}
