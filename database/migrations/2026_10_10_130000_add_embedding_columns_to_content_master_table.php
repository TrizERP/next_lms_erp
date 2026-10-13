<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Storage for the Content Authoring RAG row — auto-tagging an uploaded
 * curriculum document (`content_master`) against the concept graph by
 * embedding + cosine similarity.
 *
 * Mirrors hpbrain_backend's own pattern exactly (migration
 * `2026_10_10_120000_add_embedding_to_knowledge_assets.php` there, read
 * before writing this): one JSON column holding one entry PER CHUNK
 * (`[{index, hash, text, vector}, ...]`), not a vector-typed column — MariaDB
 * here has no vector extension, and `Embeddings::cosineSimilarity()`'s own
 * design (ported to `ContentEmbeddingService`) scores in PHP, so there is
 * nothing a native vector column would buy today. `embedding_model`/
 * `embedding_version` are separate scalar columns, matching hpbrain's split,
 * so a later re-embed with a different model never silently mixes vectors
 * from two incompatible spaces under one column.
 *
 * `content_master` is a live, 30,792-row production table — see
 * config/neo4j.php's projections docblock — so this only ever ADDS nullable
 * columns, never touches existing data, and is guarded both by
 * `Schema::hasTable` (absent in some environments, same guard the
 * `2026_10_10_120000_create_neo4j_sync_trigger_content_master` migration
 * right before this one uses) and by `Schema::hasColumn` per column, so
 * re-running this migration — or a future migration adding the same column
 * under a different name — can never collide or error.
 */
return new class extends Migration
{
    private const TABLE = 'content_master';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function ($table) {
            if (! Schema::hasColumn(self::TABLE, 'embedding')) {
                $table->json('embedding')->nullable()->after('meta_tags');
            }
            if (! Schema::hasColumn(self::TABLE, 'embedding_model')) {
                $table->string('embedding_model', 100)->nullable()->after('embedding');
            }
            if (! Schema::hasColumn(self::TABLE, 'embedding_version')) {
                $table->string('embedding_version', 20)->nullable()->after('embedding_model');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function ($table) {
            foreach (['embedding_version', 'embedding_model', 'embedding'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
