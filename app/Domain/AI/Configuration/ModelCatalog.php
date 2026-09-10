<?php

namespace App\Domain\AI\Configuration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The models each provider offers, as one editable list.
 *
 * This is the "centralized model-management data" the provider screen's model
 * dropdown reads. There is deliberately no second list anywhere: the dropdown, the
 * Model Management screen and the runtime resolver all come through here, so a model
 * added once is offered everywhere and a model retired once disappears everywhere.
 *
 * SCOPE
 *
 * A row with `sub_institute_id NULL` is the platform catalogue and every school sees
 * it. A row with an institute belongs to that school alone. `forProvider()` returns
 * both for the school asking, platform rows first, which is the same precedence
 * `ProviderKeyResolver` and `TemplateRegistry` already use — one convention across
 * the three tables an administrator touches.
 *
 * DEGRADES RATHER THAN FAILS
 *
 * Every read is guarded on the table existing. On an estate that has not run the
 * catalogue migration the methods return empty, the dropdown falls back to the
 * provider's configured default, and nothing that works today stops working.
 */
final class ModelCatalog
{
    /**
     * Models for one provider, for one school.
     *
     * @return array<int, array{id:int, provider:string, model_id:string, label:string, max_output_tokens:int|null, input_cost_per_1k:float|null, output_cost_per_1k:float|null, status:int, scope:string}>
     */
    public function forProvider(string $provider, int|string|null $subInstituteId = null, bool $includeRetired = false): array
    {
        if (! Schema::hasTable('ai_models')) {
            return [];
        }

        $query = DB::table('ai_models')->where('provider', $provider);

        if (! $includeRetired) {
            $query->where('status', 1);
        }

        $this->scopeTo($query, $subInstituteId);

        $rows = $query
            ->orderByRaw('sub_institute_id IS NULL DESC')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return $rows->map(fn ($row) => $this->present($row))->all();
    }

    /**
     * The whole catalogue for a school, grouped by provider.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function grouped(int|string|null $subInstituteId = null, bool $includeRetired = true): array
    {
        if (! Schema::hasTable('ai_models')) {
            return [];
        }

        $query = DB::table('ai_models');

        if (! $includeRetired) {
            $query->where('status', 1);
        }

        $this->scopeTo($query, $subInstituteId);

        $rows = $query
            ->orderBy('provider')
            ->orderByRaw('sub_institute_id IS NULL DESC')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->provider][] = $this->present($row);
        }

        return $grouped;
    }

    /** One model, by id, but only if the asking school is allowed to see it. */
    public function find(int $id, int|string|null $subInstituteId = null): ?array
    {
        if (! Schema::hasTable('ai_models')) {
            return null;
        }

        $query = DB::table('ai_models')->where('id', $id);
        $this->scopeTo($query, $subInstituteId);

        $row = $query->first();

        return $row ? $this->present($row) : null;
    }

    /**
     * Whether this provider/model pairing is one the catalogue offers.
     *
     * Used to refuse a configuration naming a model its provider does not have — the
     * mistake that produces a 404 from the vendor at the moment a user asks a
     * question, rather than at the moment an administrator pressed Save.
     */
    public function offers(string $provider, string $modelId, int|string|null $subInstituteId = null): bool
    {
        if (! Schema::hasTable('ai_models')) {
            // No catalogue on this estate: nothing to contradict, so nothing to refuse.
            return true;
        }

        $query = DB::table('ai_models')
            ->where('provider', $provider)
            ->where('model_id', $modelId)
            ->where('status', 1);

        $this->scopeTo($query, $subInstituteId);

        return $query->exists();
    }

    /**
     * The model a provider should use when a configuration does not name one.
     *
     * Catalogue first, because that is what an administrator can see and change;
     * config only when the catalogue has nothing for this provider.
     */
    public function defaultFor(string $provider, int|string|null $subInstituteId = null): ?string
    {
        $models = $this->forProvider($provider, $subInstituteId);

        return $models[0]['model_id'] ?? null;
    }

    /**
     * Platform rows plus this school's own; platform only when no school is named.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function scopeTo($query, int|string|null $subInstituteId): void
    {
        $institute = $subInstituteId === null ? null : trim((string) $subInstituteId);

        if ($institute === null || $institute === '') {
            $query->whereNull('sub_institute_id');

            return;
        }

        $query->where(function ($inner) use ($institute) {
            $inner->whereNull('sub_institute_id')->orWhere('sub_institute_id', $institute);
        });
    }

    private function present(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'provider' => (string) $row->provider,
            'model_id' => (string) $row->model_id,
            'label' => (string) $row->label,
            'max_output_tokens' => $row->max_output_tokens === null ? null : (int) $row->max_output_tokens,
            'input_cost_per_1k' => $row->input_cost_per_1k === null ? null : (float) $row->input_cost_per_1k,
            'output_cost_per_1k' => $row->output_cost_per_1k === null ? null : (float) $row->output_cost_per_1k,
            'sort_order' => (int) ($row->sort_order ?? 0),
            'status' => (int) $row->status,
            // Tells the screen whether this row can be edited by this school: a
            // platform row is shared, and letting one school rename it would rename it
            // for every other school too.
            'scope' => ($row->sub_institute_id ?? null) === null ? 'platform' : 'institute',
        ];
    }
}
