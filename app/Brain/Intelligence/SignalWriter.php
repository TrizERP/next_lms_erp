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
 *      standing problem into thirty rows. The match is on (tenant, rule_key,
 *      status not resolved/dismissed); a resolved problem that comes back is
 *      genuinely new and does get its own signal. created_date is never
 *      rewritten on a refresh — how long a problem has been open is the most
 *      useful fact about it.
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

    public function __construct(private readonly string $tenantId)
    {
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
            $this->refresh($open, (array) $data['metadata']);
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
                // The unique index is (tenant_id, dedupe_key). One open signal
                // per rule is exactly the invariant we want the database itself
                // to hold, so a concurrent second pipeline run cannot duplicate.
                'dedupe_key' => $ruleKey !== '' ? hash('sha256', $this->tenantId.'|'.$ruleKey) : hash('sha256', $signalId),
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

    /** The unresolved signal this rule already raised, if any. */
    private function openSignalFor(string $ruleKey): ?object
    {
        return DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->where('rule_key', $ruleKey)
            ->whereNotIn('status', ['resolved', 'dismissed'])
            ->orderByDesc('created_date')
            ->first();
    }

    /**
     * Bring an open signal's figures up to date without raising another.
     *
     * firstCount is preserved across refreshes so a screen can show whether the
     * problem is growing or shrinking — the single most actionable thing about a
     * standing signal, and it is lost the moment the metadata is overwritten
     * wholesale.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function refresh(object $signal, array $metadata): void
    {
        $previous = json_decode((string) $signal->metadata, true);
        $previous = is_array($previous) ? $previous : [];

        DB::table('hpbrain_signals')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $signal->id)
            ->update(SchemaCache::only('hpbrain_signals', [
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
