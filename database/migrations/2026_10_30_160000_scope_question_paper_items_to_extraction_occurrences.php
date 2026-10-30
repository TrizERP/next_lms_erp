<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('question_paper_items') || Schema::hasColumn('question_paper_items', 'source_key')) {
            return;
        }

        Schema::table('question_paper_items', function (Blueprint $table) {
            $table->string('source_key', 64)->nullable();
        });

        DB::table('question_paper_items')->orderBy('id')->each(function (object $item): void {
            DB::table('question_paper_items')->where('id', $item->id)->update([
                'source_key' => 'legacy:' . $item->id,
            ]);
        });

        Schema::table('question_paper_items', function (Blueprint $table) {
            $table->dropUnique('uq_qpi_job_hash');
            $table->unique(['job_id', 'source_key'], 'uq_qpi_job_source');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('question_paper_items') || !Schema::hasColumn('question_paper_items', 'source_key')) {
            return;
        }

        $duplicates = DB::table('question_paper_items')->select('job_id', 'content_hash')
            ->groupBy('job_id', 'content_hash')->havingRaw('COUNT(*) > 1')->first();
        if ($duplicates) {
            throw new \RuntimeException('Cannot restore unique question-paper hashes while duplicate extracted occurrences are recorded.');
        }

        Schema::table('question_paper_items', function (Blueprint $table) {
            $table->dropUnique('uq_qpi_job_source');
            $table->dropColumn('source_key');
            $table->unique(['job_id', 'content_hash'], 'uq_qpi_job_hash');
        });
    }
};
