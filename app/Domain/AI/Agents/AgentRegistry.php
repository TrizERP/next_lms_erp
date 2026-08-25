<?php

namespace App\Domain\AI\Agents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loads agent manifests.
 *
 * A tenant row overrides the platform baseline for the same key, which is how one
 * school can tighten an agent (fewer tools, a lower verb ceiling) without forking it.
 * Overrides can only be loaded, never written from here — editing a manifest is an
 * administrative action with its own audited endpoint.
 */
class AgentRegistry
{
    /** @var array<string, AgentManifest|null> */
    private array $memo = [];

    public function find(string $agentKey, ?int $subInstituteId = null): ?AgentManifest
    {
        $memoKey = $agentKey . ':' . ($subInstituteId ?? 'global');

        if (array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey];
        }

        if (! Schema::hasTable('ai_agents')) {
            return $this->memo[$memoKey] = null;
        }

        $row = DB::table('ai_agents')
            ->where('agent_key', $agentKey)
            ->where('status', 1)
            ->where(function ($query) use ($subInstituteId) {
                $query->whereNull('sub_institute_id');
                if ($subInstituteId !== null) {
                    $query->orWhere('sub_institute_id', $subInstituteId);
                }
            })
            // Tenant-specific first: the more specific manifest wins.
            ->orderByRaw('sub_institute_id IS NULL ASC')
            ->first();

        return $this->memo[$memoKey] = $row ? AgentManifest::fromRow($row) : null;
    }

    /**
     * @return array<int, AgentManifest>
     */
    public function all(?int $subInstituteId = null, ?string $domain = null): array
    {
        if (! Schema::hasTable('ai_agents')) {
            return [];
        }

        $query = DB::table('ai_agents')
            ->where('status', 1)
            ->where(function ($inner) use ($subInstituteId) {
                $inner->whereNull('sub_institute_id');
                if ($subInstituteId !== null) {
                    $inner->orWhere('sub_institute_id', $subInstituteId);
                }
            });

        if ($domain !== null) {
            $query->whereIn('domain', [$domain, 'shared']);
        }

        $rows = $query->orderByRaw('sub_institute_id IS NULL ASC')->get();

        // Collapse to one manifest per key, tenant override winning.
        $byKey = [];

        foreach ($rows as $row) {
            if (! isset($byKey[$row->agent_key])) {
                $byKey[$row->agent_key] = AgentManifest::fromRow($row);
            }
        }

        return array_values($byKey);
    }

    /**
     * Agents a given role is allowed to see or run.
     *
     * @return array<int, AgentManifest>
     */
    public function forRole(string $role, ?int $subInstituteId = null, ?string $domain = null): array
    {
        return array_values(array_filter(
            $this->all($subInstituteId, $domain),
            fn (AgentManifest $manifest) => $manifest->permitsRole($role)
        ));
    }

    public function flush(): void
    {
        $this->memo = [];
    }
}
