<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per turn number on a thread, enforced by the database.
 *
 * `ConversationStore::recordTurn()` numbers turns with `max(sequence) + 1` — read in one
 * statement, written in another. Two questions asked on one thread in the same instant
 * both read the same maximum and both wrote it, producing two rows numbered "2" and a
 * `turn_count` that disagreed with the rows underneath it.
 *
 * The store now retries on collision, but a retry is only correct if the database is
 * what detects the collision. Without this index the second write simply succeeds and
 * there is nothing to retry — the application-side loop is dead code until this runs.
 *
 * Guarded rather than assumed. An estate that already holds duplicates would fail
 * mid-migration, and a half-applied migration on a shared database is worse than an
 * index added a day later, so the duplicates are named instead and the migration can be
 * re-run once they are reconciled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_conversation_turns') || $this->indexExists()) {
            return;
        }

        $duplicates = DB::table('ai_conversation_turns')
            ->select('conversation_id', 'sequence', DB::raw('count(*) as occurrences'))
            ->groupBy('conversation_id', 'sequence')
            ->havingRaw('count(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'ai_conversation_turns holds %d duplicated (conversation_id, sequence) pair%s, so the '
                . 'unique index cannot be added. Renumber the later row of each pair, then re-run. '
                . 'The pairs are: %s',
                $duplicates->count(),
                $duplicates->count() === 1 ? '' : 's',
                $duplicates
                    ->map(fn ($row) => sprintf(
                        'conversation #%d sequence %d (x%d)',
                        $row->conversation_id,
                        $row->sequence,
                        $row->occurrences
                    ))
                    ->implode(', ')
            ));
        }

        Schema::table('ai_conversation_turns', function ($table) {
            $table->unique(['conversation_id', 'sequence'], 'ai_turn_sequence_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_conversation_turns') || ! $this->indexExists()) {
            return;
        }

        Schema::table('ai_conversation_turns', function ($table) {
            $table->dropUnique('ai_turn_sequence_unique');
        });
    }

    /**
     * Whether the index is already present.
     *
     * Read from information_schema rather than assumed from the migrations table, so
     * this stays correct in an estate where the index was added by hand.
     */
    private function indexExists(): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', 'ai_conversation_turns')
            ->where('index_name', 'ai_turn_sequence_unique')
            ->exists();
    }
};
