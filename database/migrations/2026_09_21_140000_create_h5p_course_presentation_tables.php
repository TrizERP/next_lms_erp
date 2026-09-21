<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Course Presentation (H5P.CoursePresentation).
 *
 * THREE TABLES, BECAUSE A DECK IS GENUINELY THREE LEVELS.
 *
 * Every other type in this family is two: an item and its children. A
 * presentation is a deck, of slides, of positioned elements -- and the middle
 * level carries real state of its own (a background, speaker notes, a keyword,
 * a branching target), so collapsing it into a `slide_index` column on the
 * elements would lose every slide that has no elements on it yet. An author
 * building a deck creates empty slides constantly.
 *
 *   h5p_course_presentation   the deck: theme, navigation, scoring
 *   h5p_presentation_slides   one slide, in order
 *   h5p_slide_elements        one positioned thing on a slide
 *
 * ELEMENTS ARE A TAGGED UNION, NOT A TABLE PER KIND. An element is a rectangle
 * with a type and a payload. Nine kinds would be nine near-identical tables
 * joined nine ways to render one slide; instead the geometry and the common
 * columns are real columns, and the part that differs per kind lives in
 * `options` as JSON. Nothing queries into `options` -- it is read whole, by
 * the builder and the player -- which is the test for when JSON is right.
 *
 * WHAT `element_type = 'drag_drop'` DOES. It does not re-author a drag
 * question inside a slide. It REFERENCES an existing published Drag and Drop
 * activity from this chapter by id (`ref_content_id`), and the builder inlines
 * that activity's params when it exports. One drag question, authored in the
 * editor built for it, usable in a deck -- rather than a second, worse drag
 * editor living inside the slide editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_course_presentation', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();

            /*
             * Presentation theme. A named token set, not a colour: the design
             * system owns the palette, and a hex column here would be the
             * first hardcoded colour in the product.
             */
            $table->string('theme', 32)->default('default');
            // none | fade | slide. Collapses to none under prefers-reduced-motion.
            $table->string('slide_transition', 16)->default('fade');

            $table->boolean('show_progress_bar')->default(true);
            // The keyword rail down the left: slide titles as a jump list.
            $table->boolean('show_keywords')->default(true);
            $table->boolean('show_summary_slide')->default(true);
            $table->boolean('enable_print')->default(false);
            /*
             * H5P `activeSurface`: hide the navigation chrome so the only way
             * forward is something on the slide. This is what makes branching
             * meaningful -- with the arrows visible a learner simply walks past
             * the choice.
             */
            $table->boolean('active_surface')->default(false);

            $table->boolean('enable_retry')->default(true);
            $table->boolean('enable_show_solution')->default(true);
            $table->unsignedTinyInteger('pass_percentage')->default(60);

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

            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_course_presentation_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_course_presentation_standard_subject_idx');
            $table->index('status', 'h5p_course_presentation_status_idx');
        });

        Schema::create('h5p_presentation_slides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('presentation_id');

            // 0-based position in the deck. Rewritten on every save, so it is
            // always dense and always matches the author's order.
            $table->unsignedInteger('slide_index')->default(0);

            // The keyword shown in the rail and read out as the slide's name.
            $table->string('title')->nullable();

            $table->text('background_image')->nullable();
            // A design-system token name (e.g. surface-canvas), not a hex.
            $table->string('background_token', 32)->nullable();

            // Speaker notes. Author- and teacher-facing; never shown to a
            // learner, and never exported into the package's content.
            $table->longText('notes')->nullable();

            /*
             * BRANCHING.
             *
             * Null means "the next slide", which is what almost every slide
             * does. A value is an id in this table and overrides the sequence,
             * so a deck can fork and rejoin. The controller validates that the
             * target belongs to the same presentation, because a cross-deck
             * jump is a dead end a learner cannot get out of.
             */
            $table->unsignedBigInteger('next_slide_id')->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('presentation_id', 'h5p_presentation_slides_parent_idx');
            $table->index(['presentation_id', 'slide_index'], 'h5p_presentation_slides_order_idx');
        });

        Schema::create('h5p_slide_elements', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Denormalised parent, so "every element in this deck" is one
            // query rather than a join through slides on every read path.
            $table->unsignedBigInteger('presentation_id');
            $table->unsignedBigInteger('slide_id');

            /*
             * text | image | video | audio | multiple_choice | true_false |
             * blanks | drag_drop | goto_slide
             *
             * The first four are static; the next four are scored; goto_slide
             * is a navigation button. The set is closed and validated in the
             * controller -- an unknown type would render as nothing and score
             * as nothing, silently.
             */
            $table->string('element_type', 32);

            // Geometry as a percentage of the slide, same as every other
            // positioned thing in this family.
            $table->decimal('position_x', 8, 4)->default(10);
            $table->decimal('position_y', 8, 4)->default(10);
            $table->decimal('width', 8, 4)->default(40);
            $table->decimal('height', 8, 4)->default(20);

            // Rich HTML for `text`; the question stem for the scored kinds.
            $table->longText('content_text')->nullable();

            $table->text('media_path')->nullable();
            $table->string('media_alt')->nullable();

            /*
             * The kind-specific payload. Read whole, never queried into:
             *
             *   multiple_choice  {answers:[{text,correct,feedback}], single}
             *   true_false       {correct: bool, feedback: {true,false}}
             *   blanks           {passage: "... *answer* ...", case_sensitive}
             *   drag_drop        {} -- the content is ref_content_id
             *   goto_slide       {target_slide_id, label}
             *   video / audio    {autoplay, loop, controls}
             */
            $table->json('options')->nullable();

            /*
             * The id of another H5P item this element embeds.
             *
             * Only `drag_drop` uses it today. It is a plain id rather than a
             * foreign key because the target table depends on element_type,
             * and a constraint that only applies to one value of a
             * discriminator is a constraint the database cannot express.
             * The controller checks it on save; the player degrades to a
             * "content unavailable" note if it has since been deleted.
             */
            $table->unsignedBigInteger('ref_content_id')->nullable();

            // What a correct answer is worth. 0 on the static kinds, which is
            // also how max score knows to skip them.
            $table->unsignedSmallInteger('points')->default(1);

            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('slide_id', 'h5p_slide_elements_slide_idx');
            $table->index('presentation_id', 'h5p_slide_elements_deck_idx');
            $table->index(['slide_id', 'sort_order'], 'h5p_slide_elements_order_idx');
            $table->index(['presentation_id', 'element_type'], 'h5p_slide_elements_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_slide_elements');
        Schema::dropIfExists('h5p_presentation_slides');
        Schema::dropIfExists('h5p_course_presentation');
    }
};
