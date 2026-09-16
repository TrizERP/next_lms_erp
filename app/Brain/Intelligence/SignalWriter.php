<?php

namespace App\Brain\Intelligence;

use App\Brain\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * The one write path for signals and their evidence.
 *
 * Ported from hp-enterprise-brain/app/Domain/Signals/OperationalSignalWriter.php
 * and kept to the same two guarantees, because everything downstream depends on
 * them:
 *
 *   1. A RULE THAT STILL FIRES REFRESHES ITS OPEN SIGNAL, IT DOES NOT STACK A
 *      SECOND ONE. Re-running the pipeline every night must not turn one
 *      standing problem into thirty rows. The match is on (tenant, ACADEMIC
 *      YEAR, rule_key, status not resolved/dismissed); a resolved problem that
 *      comes back is genuinely new and does get its own signal. created_date is
 *      never rewritten on a refresh — how long a problem has been open is the
 *      most useful fact about it.
 *
 *      THE YEAR IS PART OF THE IDENTITY, not a filter applied afterwards.
 *      "Collection fell 40%" is a claim about one academic year; without the
 *      year in the dedupe key a run for 2020 would find 2021's open signal and
 *      overwrite its figures in place, leaving one row that silently reports
 *      whichever year happened to run last. That is worse than a duplicate,
 *      because it still looks like an answer.
 *
 *   2. THE SIGNAL ROW IS WRITTEN BEFORE ITS EVIDENCE. hpbrain_evidence carries a
 *      foreign key to hpbrain_signals, so the reverse order fails on MySQL. That
 *      is why recordEvidence() only BUFFERS: rules call it while they are still
 *      assembling their finding, and the rows are inserted inside raise() once
 *      the signal they hang off exists.
 *
 * EVERY SIGNAL HERE IS DERIVED FROM A vivek_erp ROW COUNT. Nothing is seeded,
 * randomised or demonstrative — a rule that finds nothing writes nothing, and
 * the screen it feeds stays empty, which is the honest answer.
 */
final class SignalWriter
{
    private const ACTOR = 'brain.pipeline';

    /** @var array<string, array<string, mixed>> evidenceId => content, awaiting a signal */
    private array $pending = [];

    /** Evidence ledger high-water mark, read once and then held for this run. */
    private ?int $ledgerSequence = null;

    /**
     * @param  string  $tenantId  the institute every row is scoped to
     * @param  string|null  $syear  the academic year the figures describe, or
     *                              null when the caller genuinely has not
     *                              resolved one — recorded as NULL rather than
     *                              guessed, so "year unknown" stays visible.
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $syear = null,
    ) {
    }

    /**
     * Hold an evidence row and reserve its id. Nothing is written until raise().
     *
     * @param  array<string, mixed>  $content
     */
    public function recordEvidence(array $content): string
    {
        $id = Uuid::v4();
        $this->pending[$id] = $content;

        return $id;
    }

    /**
     * Raise a signal, or refresh the open one this rule already raised.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $evidenceIds
     * @return array{created: bool, refreshed: bool, signalId: ?string, reason: ?string}
     */
    public function raise(array $data, array $evidenceIds = []): array
    {
        if (! SchemaCache::hasTable('hpbrain_signals')) {
            $this->discard($evidenceIds);

            return ['created' => false, 'refreshed' => false, 'signalId' => null, 'reason' => 'no_signal_table'];
        }

        $ruleKey = (string) ($data['metadata']['rule'] ?? '');
        $open = $ruleKey !== '' ? $this->openSignalFor($ruleKey) : null;

        if ($open !== null) {
            $this->refresh($open, (array) $data['metadata'], $ruleKey);
            $this->discard($evidenceIds);

            return ['created' => false, 'refreshed' => true, 'signalId' => (string) $open->id, 'reason' => null];
        }

        $signalId = Uuid::v4();
        $rows = $this->take($evidenceIds);
        $now = now()->format('Y-m-d H:i:s');

        DB::transaction(function () use ($signalId, $data, $ruleKey, $rows, $now) {
            DB::table('hpbrain_signals')->insert(SchemaCache::only('hpbrain_signals', [
                'id' => $signalId,
                'tenant_id' => $this->tenantId,
                'syear' => $this->syear,
                // The unique index is (tenant_id, dedupe_key). One open signal
                // per rule PER YEAR is exactly the invariant we want the
                // database itself to hold, so a concurrent second pipeline run
                // cannot duplicate and a run for another year cannot collide.
                'dedupe_key' => $ruleKey !== '' ? $this->dedupeKey($ruleKey) : hash('sha256', $signalId),
                'org_id' => 'org-'.$this->tenantId.'-'.$this->tenantId,
                'source' => (string) $data['source'],
                'classification' => (string) $data['classification'],
                'rule_key' => $ruleKey !== '' ? $ruleKey : null,
                'priority' => (string) ($data['priority'] ?? 'normal'),
                'severity' => (string) ($data['severity'] ?? 'low'),
                'confidence' => (float) ($data['confidence'] ?? 0.5),
                'related_entity_type' => $data['relatedEntityType'] ?? null,
                'related_entity_id' => $data['relatedEntityId'] ?? null,
                'department_id' => $data['departmentId'] ?? null,
                'status' => 'new',
                'metadata' => json_encode($data['metadata']),
                'created_by' => self::ACTOR,
                'created_date' => $now,
                'updated_date' => $now,
            ]));

            foreach ($rows as $evidenceId => $content) {
                $this->insertEvidence($signalId, (string) $evidenceId, $content, $now);
            }
        });

        return ['created' => true, 'refreshed' => false, 'signalId' => $signalId, 'reason' => null];
    }

    /**
     * The unresolved signal this rule already raised FOR THIS ACADEMIC YEAR, if
     * any.
     *
     * The ordering of the two candidates matters and is the whole of the
     * migration story:
     *
     *   1. A row already stamped with this year is this rule's signal for this
     *      year, and is refreshed.
     *
     *   2. Failing that, a row with syear NULL is a legacy signal written before
     *      the tables carried a year at all (migration
     *      2026_09_16_000100_brain_year_scope_intelligence deliberately left
     *      those NULL rather than inventing provenance for them). It is adopted
     *      onto this year — which is honest, because refresh() rewrites its
     *      figures from this year's data in the same breath. Adopting rather
     *      than ignoring is also what stops the first year-aware run from
     *      doubling every standing finding on the existing Brain screens.
     *
     * A run for a DIFFERENT year matches neither and raises its own signal,
     * which is the defect this method exists to prevent.
     */
    private function openSignalFor(string $ruleKey): ?object
    {
        $open = DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->where('rule_key', $ruleKey)
            ->whereNotIn('status', ['resolved', 'dismissed']);

        if (! SchemaCache::hasColumn('hpbrain_signals', 'syear')) {
            return $open->orderByDesc('created_date')->first();
        }

        if ($this->syear === null) {
            // A caller with no year must not adopt a year-stamped row and
            // relabel it "year unknown"; it matches only the unstamped ones.
            return $open->whereNull('syear')->orderByDesc('created_date')->first();
        }

        return $open
            ->where(fn ($q) => $q->where('syear', $this->syear)->orWhereNull('syear'))
            // An exact year match wins over a legacy row, whatever their dates.
            ->orderByRaw('syear IS NULL')
            ->orderByDesc('created_date')
            ->first();
    }

    /**
     * The identity of "this rule's open finding", as the unique index sees it.
     *
     * The year is in the hash, so two years of the same rule are two rows.
     */
    private function dedupeKey(string $ruleKey): string
    {
        return hash('sha256', $this->tenantId.'|'.($this->syear ?? '-').'|'.$ruleKey);
    }

    /**
     * Bring an open signal's figures up to date without raising another.
     *
     * firstCount is preserved across refreshes so a screen can show whether the
     * problem is growing or shrinking — the single most actionable thing about a
     * standing signal, and it is lost the moment the metadata is overwritten
     * wholesale.
     *
     * A legacy row picked up by openSignalFor() is also STAMPED with the year
     * here, together with the dedupe key that year implies. Doing both in the
     * same update is what keeps the claim true: the row is labelled 2021 in the
     * same statement that replaces its figures with 2021's.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function refresh(object $signal, array $metadata, string $ruleKey): void
    {
        $previous = json_decode((string) $signal->metadata, true);
        $previous = is_array($previous) ? $previous : [];

        $adoption = [];
        if ($this->syear !== null
            && SchemaCache::hasColumn('hpbrain_signals', 'syear')
            && $signal->syear === null) {
            $adoption['syear'] = $this->syear;
            if ($ruleKey !== '') {
                $adoption['dedupe_key'] = $this->dedupeKey($ruleKey);
            }
        }

        DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $signal->id)
            ->update(SchemaCache::only('hpbrain_signals', $adoption + [
                'metadata' => json_encode(array_merge($previous, $metadata, [
                    'firstCount' => $previous['firstCount'] ?? ($previous['affectedCount'] ?? null),
                    'firstSeenAt' => $previous['firstSeenAt'] ?? (string) $signal->created_date,
                    'lastSeenAt' => now()->format('Y-m-d H:i:s'),
                ])),
                'updated_date' => now()->format('Y-m-d H:i:s'),
            ]));
    }

    /** @param array<string, mixed> $content */
    private function insertEvidence(string $signalId, string $evidenceId, array $content, string $now): void
    {
        if (! SchemaCache::hasTable('hpbrain_evidence')) {
            return;
        }

        $contentJson = json_encode($content);
        $provenanceJson = json_encode([
            'source' => $content['source'] ?? 'vivek_erp',
            'database' => DB::connection()->getDatabaseName(),
            'ts' => now()->format('Y-m-d\TH:i:s\Z'),
            'confidence' => 1.0,
        ]);

        DB::table('hpbrain_evidence')->insert(SchemaCache::only('hpbrain_evidence', [
            'id' => $evidenceId,
            'tenant_id' => $this->tenantId,
            'syear' => $this->syear,
            'signal_id' => $signalId,
            'source' => (string) ($content['source'] ?? 'vivek_erp'),
            'evidence_type' => 'observation',
            'content' => $contentJson,
            'provenance' => $provenanceJson,
            // 1.0 because this is a direct read of the system of record, not an
            // inference about it. The uncertainty in this pipeline lives in the
            // reasoning step, not in whether the row says what it says.
            'confidence' => 1.0,
            'hash' => hash('sha256', $contentJson.'|'.$provenanceJson),
            'version' => 1,
            'status' => 'active',
            'created_by' => self::ACTOR,
            'created_date' => $now,
            'observed_date' => $now,
            'ledger_sequence' => $this->nextLedgerSequence(),
        ]));
    }

    /**
     * hpbrain_evidence.ledger_sequence is NOT NULL with no default and is not
     * auto-increment, so it has to be supplied.
     *
     * THE HIGH-WATER MARK IS READ ONCE PER SIGNAL, NOT ONCE PER ROW. A MAX()
     * against a remote database per evidence row turned a twenty-five rule run
     * into a round trip per sample; the counter is held for the rest of the
     * transaction instead. It is allocated inside the same transaction as the
     * inserts, which is what keeps the ledger dense and ordered.
     */
    private function nextLedgerSequence(): int
    {
        if (! SchemaCache::hasColumn('hpbrain_evidence', 'ledger_sequence')) {
            return 0;
        }

        if ($this->ledgerSequence === null) {
            $this->ledgerSequence = (int) DB::table('hpbrain_evidence')->max('ledger_sequence');
        }

        return ++$this->ledgerSequence;
    }

    /** @return array<string, array<string, mixed>> */
    private function take(array $evidenceIds): array
    {
        $rows = [];

        foreach ($evidenceIds as $id) {
            if (isset($this->pending[$id])) {
                $rows[$id] = $this->pending[$id];
                unset($this->pending[$id]);
            }
        }

        return $rows;
    }

    /**
     * Drop buffered content for a signal that was refreshed rather than raised.
     * Without this the buffer grows for the life of the process and one rule can
     * flush another rule's abandoned evidence.
     */
    private function discard(array $evidenceIds): void
    {
        foreach ($evidenceIds as $id) {
            unset($this->pending[$id]);
        }
    }
}
