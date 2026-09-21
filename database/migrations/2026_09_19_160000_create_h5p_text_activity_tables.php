<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P text-passage activities: Drag the Words, Fill in the Blanks, Mark the
 * Words (H5P.DragText, H5P.Blanks, H5P.MarkTheWords).
 *
 * ONE TABLE FAMILY FOR THREE TYPES, ON PURPOSE.
 *
 * Drag and Drop got three tables of its own because it is genuinely its own
 * shape: a canvas, positioned zones, positioned draggables. These three are
 * not. H5P itself stores all three as ONE STRING -- a passage carrying inline
 * `*answer*` markup:
 *
 *   H5P.Blanks        "Oslo is the capital of *Norway/Noreg:It is Nordic*."
 *   H5P.DragText      "Oslo is the capital of *Norway*."   (+ distractors)
 *   H5P.MarkTheWords  "The *dog* ran after the *cat*."
 *
 * Same markup, same answer semantics, three renderers. Giving each its own
 * table would be three copies of one schema kept in step by hand, and the
 * anti-duplication rule in CLAUDE.md says reuse the variant rather than fork
 * it. So the discriminator is a column, and `config/pal_h5p.php` gives each
 * type its own registry row pointing at this table with a `where` filter --
 * exactly how `multiple_choice` already shares `lms_question_master`.
 *
 *   h5p_text_activity          the passage, behaviour, feedback, scoring
 *   h5p_text_activity_blanks   one row per answer slot, parsed out of it
 *
 * WHY THE CHILD TABLE EXISTS WHEN THE PASSAGE ALREADY HOLDS THE ANSWERS.
 *
 * The passage is the source of truth for the AUTHOR -- it is what they type
 * and what round-trips to a .h5p package without loss. The child rows are the
 * answer key made queryable: they are what scoring reads, what the analytics
 * pipeline counts a max score from, and what publish validates against. They
 * are derived on every save (H5PTextActivityBuilder::parsePassage), never
 * edited independently, so they cannot drift from the passage.
 *
 * Column names for id / title / body / chapter / subject / standard / tenant /
 * created_by / created_at / deleted_at are the ones the PAL H5P registry binds
 * to in config/pal_h5p.php `implementation.columns` -- renaming one here means
 * editing those three blocks too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_text_activity', function (Blueprint $table) {
            $table->bigIncrements('id');

            // drag_text | fill_in_the_blanks | mark_the_words.
            // Matches the PAL registry code so a row's PAL identity and its
            // H5P identity resolve from the same value.
            $table->string('content_type', 32)->index();

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // Shown above the activity. H5P calls this `taskDescription` on
            // DragText/MarkTheWords and `text` on Blanks.
            $table->text('task_description')->nullable();

            // THE PASSAGE, with `*answer*` markup intact. Verbatim what the
            // author typed and verbatim what goes into the package.
            $table->longText('passage')->nullable();

            // Drag the Words only: extra draggable words that match no blank.
            // Stored in the same markup H5P.DragText's `distractors` uses.
            $table->text('distractors')->nullable();

            // Optional illustration shown above the task. All three libraries
            // accept it as their `media.type` sub-content; here it is a URL
            // written by the media() upload endpoint, rewritten to a
            // package-relative path on export. Nullable because the great
            // majority of these activities are text only.
            $table->text('media_image')->nullable();
            $table->string('media_alt')->nullable();

            // Behaviour (the `behaviour` object all three libraries share).
            $table->boolean('enable_retry')->default(true);
            $table->boolean('enable_show_solution')->default(true);
            $table->boolean('enable_check')->default(true);
            // Blanks: `caseSensitive`. Ignored by the other two, which compare
            // by word identity rather than typed input.
            $table->boolean('case_sensitive')->default(false);
            // Blanks: `acceptSpellingErrors` -- a one-character typo still
            // scores. Off by default; a spelling task must not forgive spelling.
            $table->boolean('accept_spelling_errors')->default(false);
            // DragText: `instantFeedback`. Blanks: `autoCheck`. Same idea, so
            // one column drives whichever the built params need.
            $table->boolean('instant_feedback')->default(false);
            $table->boolean('show_score_points')->default(true);
            // Blanks: `separateLines` -- render each blank on its own line.
            $table->boolean('separate_lines')->default(false);
            // Blanks: `showSolutionsRequiresInput`.
            $table->boolean('solution_requires_input')->default(true);

            // Scoring. `points_per_blank` is what one correct slot is worth;
            // max score is that times the number of child rows that are not
            // distractors, which is why the child table has to exist.
            $table->unsignedSmallInteger('points_per_blank')->default(1);
            $table->unsignedTinyInteger('pass_percentage')->default(100);

            // H5P `overallFeedback`: score bands with a message each, as
            // [{from, to, feedback}]. JSON because it is a list the author
            // edits as a whole and nothing ever queries into it.
            $table->json('feedback_bands')->nullable();

            // draft | published -- `published` is what a student surface reads.
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            // Derived cache of the built H5P params (see the drag-drop
            // migration's note: derived data, never the source of truth).
            $table->longText('content_json')->nullable();
            // Machine name + version the params were built for, so an upgrade
            // can find rows written against an older library.
            $table->string('library', 64)->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('syear', 16)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The registry, the content adapter and every list page filter on
            // exactly this tuple, with content_type leading because it is the
            // discriminator three types share.
            $table->index(['content_type', 'sub_institute_id', 'chapter_id'], 'h5p_text_activity_type_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_text_activity_standard_subject_idx');
            $table->index(['content_type', 'status'], 'h5p_text_activity_type_status_idx');
        });

        Schema::create('h5p_text_activity_blanks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('text_activity_id');

            // Position of this slot in the passage, 0-based, in reading order.
            // It is the only thing tying a row back to the markup it came
            // from, so scoring can report per-blank results in author order.
            $table->unsignedInteger('blank_index')->default(0);

            // The canonical correct answer -- the first term inside `*...*`.
            $table->string('solution', 500)->nullable();

            // The rest of the `/`-separated terms. All three types accept
            // several correct answers for one slot; Blanks is where it matters
            // most (spelling variants), but DragText uses it for synonymous
            // draggables and MarkTheWords for inflected forms.
            $table->json('alternatives')->nullable();

            // The `:tip` suffix, shown on request while answering.
            $table->string('tip', 500)->nullable();

            // A distractor is a draggable word that belongs to no blank
            // (Drag the Words only). It is stored here rather than only in the
            // parent's `distractors` string so max score can be computed as
            // "child rows where is_distractor = 0" in one query.
            $table->boolean('is_distractor')->default(false);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('text_activity_id', 'h5p_text_activity_blanks_parent_idx');
            $table->index(['text_activity_id', 'blank_index'], 'h5p_text_activity_blanks_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_text_activity_blanks');
        Schema::dropIfExists('h5p_text_activity');
    }
};
