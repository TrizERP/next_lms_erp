<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Memory Game (H5P.MemoryGame).
 *
 * ONE ROW IS ONE PAIR, NOT ONE CARD.
 *
 * H5P.MemoryGame's params hold a list of `cards`, where each entry carries an
 * `image` and an optional `matchImage`: one entry, two faces. Storing two rows
 * per pair would mean every read had to re-pair them, every write had to keep
 * them adjacent, and a half-deleted pair would be representable. So a row here
 * is a pair, with a `front_*` and a `back_*` side, and the deck the player
 * shuffles is built from it -- two tiles per row, always.
 *
 * "Mixed content cards" falls out of that for free: the two sides are typed
 * independently, so an image front with a text back (picture -> word) is a
 * normal row rather than a special case.
 *
 * PAIR SETS. `pair_set` groups rows so one authored item can hold, say, four
 * sets of six pairs and play a chosen subset. It is an integer on the row
 * rather than its own table because a set has no properties of its own -- it
 * is a label on a pair, and a table for it would be a table of integers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_memory_game', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('task_description')->nullable();

            /*
             * How many PAIRS to deal, 0 meaning every pair in the chosen sets.
             *
             * H5P calls this `numCardsToUse` and counts tiles; this counts
             * pairs, because that is what an author authored and an odd tile
             * count is not a game. The controller converts.
             */
            $table->unsignedSmallInteger('pairs_to_use')->default(0);

            // Which pair sets are in play. Null means all of them. A JSON list
            // of the integers in h5p_memory_game_cards.pair_set.
            $table->json('active_pair_sets')->nullable();

            $table->boolean('allow_retry')->default(true);
            // H5P `useGrid`: lay the deck out as a square grid rather than
            // flowing it. Square reads better on a projector, flow on a phone.
            $table->boolean('use_grid')->default(false);
            /*
             * Shuffle on every attempt.
             *
             * H5P always shuffles and gives the author no say. Teachers using
             * this for early-years recall asked for a fixed layout so a class
             * can be walked through the same board twice, so it is a setting.
             * On by default, which is the H5P behaviour.
             */
            $table->boolean('shuffle_cards')->default(true);

            $table->boolean('show_completion_screen')->default(true);
            $table->text('completion_message')->nullable();

            /*
             * SCORING.
             *
             *   pairs  one point per matched pair, regardless of how many
             *          turns it took. Measures recall of the content.
             *   moves  points scaled by how close the learner came to the
             *          perfect number of flips. Measures recall of the BOARD.
             *
             * Both are real memory-game scorings and they answer different
             * questions, so the author picks rather than the code assuming.
             */
            $table->string('scoring_mode', 16)->default('pairs');
            $table->unsignedSmallInteger('points_per_pair')->default(1);
            $table->unsignedTinyInteger('pass_percentage')->default(100);

            /*
             * Time.
             *
             * `track_time` records how long an attempt took and reports it in
             * the xAPI result duration -- it does not end the game.
             * `time_limit_seconds` does, and 0 means untimed. They are
             * separate because a teacher who wants the number very often does
             * not want the pressure.
             */
            $table->boolean('track_time')->default(true);
            $table->unsignedInteger('time_limit_seconds')->default(0);

            // H5P `lookNFeel`.
            $table->string('theme_color', 16)->default('#4f46e5');
            $table->text('card_back_image')->nullable();

            $table->json('feedback_bands')->nullable();

            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->longText('content_json')->nullable();
            $table->string('library', 64)->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('syear', 16)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_memory_game_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_memory_game_standard_subject_idx');
            $table->index('status', 'h5p_memory_game_status_idx');
        });

        Schema::create('h5p_memory_game_cards', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('memory_game_id');

            // Which set this pair belongs to. 1 unless the author made more.
            $table->unsignedSmallInteger('pair_set')->default(1);

            // Each side is typed independently: text | image.
            $table->string('front_type', 16)->default('image');
            $table->text('front_text')->nullable();
            $table->text('front_image')->nullable();
            $table->string('front_alt')->nullable();

            $table->string('back_type', 16)->default('image');
            $table->text('back_text')->nullable();
            $table->text('back_image')->nullable();
            $table->string('back_alt')->nullable();

            /*
             * H5P `description`: what is shown when this pair is matched.
             *
             * It is the teaching moment of the whole type -- "Ganges: longest
             * river in India" the instant the two tiles turn over -- so it is
             * its own column rather than reused alt text.
             */
            $table->text('match_description')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('memory_game_id', 'h5p_memory_game_cards_parent_idx');
            $table->index(['memory_game_id', 'pair_set', 'sort_order'], 'h5p_memory_game_cards_set_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_memory_game_cards');
        Schema::dropIfExists('h5p_memory_game');
    }
};
