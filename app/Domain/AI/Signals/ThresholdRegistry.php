<?php

namespace App\Domain\AI\Signals;

use App\Services\PAL\Intelligence\PredictiveInterventionEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Throwable;

/**
 * Where risk thresholds come from — and where they must not be invented.
 *
 * The estate already had working thresholds inside PredictiveInterventionEngine:
 * disengagement 0.6, failure 0.7, burnout 0.75, and a four-band classification at
 * 0.75 / 0.5 / 0.25. Those are the business rules. Re-declaring them here as fresh
 * constants would have quietly forked them, so this class reads them off the engine
 * by reflection instead: change the engine, and the intelligence layer follows.
 *
 * A tenant may override a band in `ai_signal_definitions.thresholds`; that is the
 * only sanctioned way to diverge, and it is per-school and auditable.
 *
 * The literals below exist solely as a fallback for when reflection cannot read the
 * engine (property renamed, class moved). They are the engine's current values, and
 * `sourceFor()` reports honestly which of the two answered.
 */
class ThresholdRegistry
{
    /** Mirrors PredictiveInterventionEngine::classifyRisk(). */
    private const FALLBACK_BANDS = [
        'critical' => 0.75,
        'high' => 0.5,
        'moderate' => 0.25,
    ];

    /** Mirrors the engine's protected trigger properties. */
    private const FALLBACK_TRIGGERS = [
        'disengagement' => 0.6,
        'failure' => 0.7,
        'burnout' => 0.75,
    ];

    private const ENGINE_PROPERTY = [
        'disengagement' => 'disengagementThreshold',
        'failure' => 'failureThreshold',
        'burnout' => 'burnoutThreshold',
    ];

    /** @var array<string, array{value:float,source:string}> */
    private array $memo = [];

    /**
     * The score above which the engine considers an intervention warranted.
     */
    public function triggerThreshold(string $kind, ?int $subInstituteId = null): float
    {
        return $this->resolve($kind, $subInstituteId)['value'];
    }

    /**
     * Whether the threshold came from the engine or from the mirrored fallback.
     * Recorded on signals so an audit can tell which rule fired.
     */
    public function sourceFor(string $kind, ?int $subInstituteId = null): string
    {
        return $this->resolve($kind, $subInstituteId)['source'];
    }

    /**
     * The severity bands. A tenant override replaces the whole band set, never a
     * single edge, so the bands cannot end up out of order.
     *
     * @return array<string, float>
     */
    public function bands(?int $subInstituteId = null, ?string $signalKey = null): array
    {
        $override = $this->tenantOverride($signalKey, $subInstituteId);

        if (isset($override['bands']) && is_array($override['bands'])) {
            $bands = array_map('floatval', $override['bands']);

            if ($this->bandsAreOrdered($bands)) {
                return $bands;
            }
        }

        return self::FALLBACK_BANDS;
    }

    /**
     * Classify a 0..1 score. Deliberately identical in behaviour to the engine's
     * classifyRisk(), so a signal's severity always agrees with PAL's own reading.
     */
    public function classify(float $score, ?int $subInstituteId = null, ?string $signalKey = null): string
    {
        $bands = $this->bands($subInstituteId, $signalKey);

        arsort($bands);

        foreach ($bands as $label => $floor) {
            if ($score >= $floor) {
                return (string) $label;
            }
        }

        return 'low';
    }

    /**
     * Severity ordering, used to rank cases and to decide whether a signal is worth
     * opening a case for at all.
     */
    public function severityRank(string $severity): int
    {
        return match (strtolower($severity)) {
            'critical' => 4,
            'high' => 3,
            'moderate' => 2,
            default => 1,
        };
    }

    public function isActionable(string $severity): bool
    {
        return $this->severityRank($severity) >= 3;
    }

    /**
     * @return array{value:float,source:string}
     */
    private function resolve(string $kind, ?int $subInstituteId): array
    {
        $memoKey = $kind . ':' . ($subInstituteId ?? 'global');

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $override = $this->tenantOverride($kind, $subInstituteId);

        if (isset($override['trigger']) && is_numeric($override['trigger'])) {
            return $this->memo[$memoKey] = [
                'value' => (float) $override['trigger'],
                'source' => 'tenant_override',
            ];
        }

        $fromEngine = $this->readFromEngine($kind);

        if ($fromEngine !== null) {
            return $this->memo[$memoKey] = [
                'value' => $fromEngine,
                'source' => 'predictive_intervention_engine',
            ];
        }

        return $this->memo[$memoKey] = [
            'value' => self::FALLBACK_TRIGGERS[$kind] ?? 0.5,
            'source' => 'mirrored_fallback',
        ];
    }

    /**
     * Read the engine's protected threshold without modifying it.
     */
    private function readFromEngine(string $kind): ?float
    {
        $property = self::ENGINE_PROPERTY[$kind] ?? null;

        if ($property === null) {
            return null;
        }

        try {
            $reflection = new ReflectionClass(PredictiveInterventionEngine::class);

            if (! $reflection->hasProperty($property)) {
                return null;
            }

            $defaults = $reflection->getDefaultProperties();

            return isset($defaults[$property]) ? (float) $defaults[$property] : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function tenantOverride(?string $signalKey, ?int $subInstituteId): array
    {
        if ($signalKey === null || $subInstituteId === null || ! Schema::hasTable('ai_signal_definitions')) {
            return [];
        }

        $row = DB::table('ai_signal_definitions')
            ->where('signal_key', $signalKey)
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->value('thresholds');

        if (! is_string($row) || $row === '') {
            return [];
        }

        $decoded = json_decode($row, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function bandsAreOrdered(array $bands): bool
    {
        $values = array_values($bands);

        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i] > $values[$i - 1]) {
                return false;
            }
        }

        return $values !== [];
    }
}
