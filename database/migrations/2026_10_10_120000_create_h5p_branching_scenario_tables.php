<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Branching Scenario (H5P.BranchingScenario).
 *
 * TWO TABLES: the scenario, and its nodes.
 *
 *   h5p_branching_scenario   the scenario: start screen, scoring, navigation
 *   h5p_branching_nodes      one node of the decision tree, in authoring order
 *
 * A NODE IS A TAGGED UNION, NOT A TABLE PER KIND. The three kinds a learner
 * meets -- a screen of content (`text`), a decision (`branching_question`) and
 * an ending (`ending`) -- share most of what they are (a title, a body, an
 * optional picture) and differ in where they lead. The shared part is real
 * columns; the part that differs lives in `options` (the choices of a decision)
 * and `next_node_id` (the one way on from a screen of content). Nothing queries
 * into `options`: it is read whole by the builder, the player and the graph
 * check, which is the test for when JSON is the right column.
 *
 * ENDINGS ARE NODES HERE, NOT A SEPARATE LIST. H5P keeps its end screens in a
 * list of their own and addresses them with negative ids. That is the right
 * shape for a file format and the wrong one for an authoring table: a branch
 * that leads to an ending is an ordinary edge, and a scenario "reaches an
 * ending" the same way it reaches any node. The builder does the translation
 * once, in both directions.
 *
 * EDGES ARE IDS IN THIS TABLE. `next_node_id`, and each choice's `next_node_id`
 * inside `options`, name another node of the SAME scenario. The controller
 * refuses a save in which one does not, because a branch into another scenario
 * (or into nothing) is a dead end a learner cannot get out of. The client sends
 * refs, never ids; see H5PBranchingScenarioController.
 *
 * `generation` RECORDS WHERE A GENERATED SCENARIO CAME FROM -- the concepts and
 * bank questions it was written from and when -- so the next generation for the
 * same chapter can avoid repeating it, and a reviewer can see what a scenario
 * rests on. It is null for a scenario a teacher wrote by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_branching_scenario', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // The start screen H5P shows before the first node.
            $table->string('start_title')->nullable();
            $table->text('start_subtitle')->nullable();
            $table->text('start_image')->nullable();
            $table->string('start_image_alt')->nullable();

            // What the learner will be able to do. Shown on the start screen
            // and used by the generator; a JSON list of sentences.
            $table->json('learning_objectives')->nullable();

            // no-score | static-end-score. H5P also has `dynamic-score`
            // (points gathered along the path); it is not modelled here, so a
            // scenario cannot claim a score this player does not compute.
            $table->string('scoring_option', 24)->default('static-end-score');
            $table->unsignedTinyInteger('pass_percentage')->default(60);

            $table->boolean('enable_backwards_navigation')->default(true);
            // Shuffle the order a decision's choices are shown in.
            $table->boolean('randomize_alternatives')->default(false);

            $table->json('generation')->nullable();

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

            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_branching_scenario_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_branching_scenario_standard_subject_idx');
            $table->index('status', 'h5p_branching_scenario_status_idx');
        });

        Schema::create('h5p_branching_nodes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scenario_id');

            // 0-based position. The node at 0 is where the scenario starts, as
            // the first entry of `content` is in H5P. Rewritten on every save,
            // so it is always dense.
            $table->unsignedInteger('node_index')->default(0);

            // text | branching_question | ending. Closed and validated in the
            // controller: an unknown kind would render as nothing.
            $table->string('node_type', 24)->default('text');

            /*
             * The teaching job of this node, e.g. situation | explanation |
             * investigate | consequence | remedial | check | reflection.
             * Free-standing metadata: it drives a badge in the editor and the
             * generator's completeness checks, never behaviour.
             */
            $table->string('purpose', 32)->nullable();

            $table->string('title')->nullable();
            $table->boolean('show_title')->default(true);

            // HTML. The content of a `text` node, the question of a
            // `branching_question`, the closing words of an `ending`.
            $table->longText('body')->nullable();

            $table->text('media_path')->nullable();
            $table->string('media_alt')->nullable();

            $table->string('proceed_button_text', 50)->nullable();

            // `text` only: the one way on. Null on the other two kinds.
            $table->unsignedBigInteger('next_node_id')->nullable();

            /*
             * `branching_question` only. A list of choices:
             *   {text, next_node_id, quality: best|partial|misstep,
             *    feedback_title, feedback_text, feedback_image, feedback_image_alt}
             * What a learner is told after choosing is the consequence and the
             * reason for it, which is why feedback is not optional in the
             * graph check.
             */
            $table->json('options')->nullable();

            // `ending` only: what reaching it is worth (H5P endScreenScore).
            $table->unsignedSmallInteger('score')->default(0);

            // Where this node's teaching comes from. Plain ids, not foreign
            // keys: both rows can be soft-deleted or re-extracted, and a
            // dangling reference is reported by the graph check, not refused.
            $table->unsignedBigInteger('concept_id')->nullable();
            $table->unsignedBigInteger('source_question_id')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('scenario_id', 'h5p_branching_nodes_scenario_idx');
            $table->index(['scenario_id', 'node_index'], 'h5p_branching_nodes_order_idx');
            $table->index(['scenario_id', 'node_type'], 'h5p_branching_nodes_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_branching_nodes');
        Schema::dropIfExists('h5p_branching_scenario');
    }
};
