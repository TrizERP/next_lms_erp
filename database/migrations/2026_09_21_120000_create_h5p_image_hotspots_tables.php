<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Image Hotspots (H5P.ImageHotspots).
 *
 * WHY THIS IS NOT h5p_scenarios.
 *
 * The registry already has a native `image_hotspot` type over `h5p_scenarios`,
 * surfaced as the "Scenario" card. That type is a title, a picture and points
 * that each carry a title and a paragraph -- four columns of content and
 * nothing else. It has no draft state, no package exchange, and no column for
 * any of what H5P.ImageHotspots actually is: a popup whose body may be text,
 * an image or rich content; a per-hotspot icon, colour and tooltip; an
 * accessible name distinct from the visible header.
 *
 * Widening h5p_scenarios to cover that would mean adding eleven columns to a
 * table 56 tenants already read through a Blade UI and an API contract, and
 * rewriting its controller -- which is modifying existing functionality, not
 * extending the framework. So this is its own type with its own registry code
 * (`image_hotspots`), and Scenario keeps working exactly as it does today.
 *
 * Column names for id / title / body / media / chapter / subject / standard /
 * tenant / created_by / created_at / deleted_at are the ones the PAL H5P
 * registry binds to in config/pal_h5p.php `implementation.columns` -- renaming
 * one here means editing that block too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_image_hotspots', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            // H5P.ImageHotspots `header`: the line shown above the image.
            $table->text('task_description')->nullable();

            // The background. A URL written by the media() endpoint, rewritten
            // to a package-relative path on export.
            $table->text('background_image')->nullable();
            $table->string('background_alt')->nullable();

            /*
             * Natural pixel size of the background, recorded at upload.
             *
             * Hotspot coordinates are percentages, so the player never needs
             * these to place a hotspot. What they are for is the ASPECT BOX:
             * the editor and the player reserve height from this ratio before
             * the image has loaded, which is what stops every hotspot on the
             * page jumping once it does. Nullable because an image imported
             * from a package has no upload to measure at.
             */
            $table->unsignedInteger('image_width')->nullable();
            $table->unsignedInteger('image_height')->nullable();

            // Default icon for hotspots that do not override it, so an author
            // sets the look once rather than per hotspot.
            $table->string('default_icon', 32)->default('plus');
            $table->string('default_icon_color', 16)->default('#4f46e5');

            // H5P.ImageHotspots `hotspotNumberLabel` / `closeButtonLabel`:
            // numbering the hotspots gives a screen reader and a teacher the
            // same order to refer to.
            $table->boolean('show_hotspot_numbers')->default(true);

            /*
             * SCORING, FOR A TYPE H5P DOES NOT SCORE.
             *
             * H5P.ImageHotspots reports `progressed` and `completed`; it has no
             * score. This platform's analytics contract (attempt, score, max
             * score, percentage) needs one all the same, so an item is scored
             * on COVERAGE: one point per hotspot opened, out of the number of
             * hotspots. That is a real measurement of whether a learner read
             * the diagram, and it is stated here rather than inferred anywhere
             * downstream. `pass_percentage` is how much of it counts as done.
             */
            $table->unsignedSmallInteger('points_per_hotspot')->default(1);
            $table->unsignedTinyInteger('pass_percentage')->default(100);

            $table->boolean('enable_retry')->default(true);
            // Close one popup when another opens. Off means several can stand
            // open at once, which authors of comparison diagrams ask for.
            $table->boolean('single_popup_open')->default(true);

            // H5P `overallFeedback`: [{from, to, feedback}].
            $table->json('feedback_bands')->nullable();

            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            // Derived cache of the built H5P params. Never the source of truth
            // -- rows are. Rebuilt on every write, ignored if stale.
            $table->longText('content_json')->nullable();
            $table->string('library', 64)->nullable();

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('syear', 16)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_image_hotspots_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_image_hotspots_standard_subject_idx');
            $table->index('status', 'h5p_image_hotspots_status_idx');
        });

        Schema::create('h5p_image_hotspot_points', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('image_hotspots_id');

            /*
             * Position as a PERCENTAGE of the background, 0-100, to four
             * decimal places -- the same geometry H5P.ImageHotspots uses and
             * the same the drag-and-drop type uses. Percentages are what make
             * one authored item render correctly on a phone and a projector
             * without a second layout.
             */
            $table->decimal('position_x', 8, 4)->default(50);
            $table->decimal('position_y', 8, 4)->default(50);

            // The popup's own title.
            $table->string('header')->nullable();

            /*
             * What the popup contains.
             *
             *   text   a paragraph                      -> H5P.AdvancedText
             *   image  a picture with a caption         -> H5P.Image
             *   rich   sanitised HTML: headings, lists,
             *          links, an inline image           -> H5P.AdvancedText
             *
             * `rich` is a separate value from `text` rather than "text that
             * happens to contain tags" so the editor knows which control to
             * open and the renderer knows whether escaping is correct.
             */
            $table->string('popup_type', 16)->default('text');

            $table->longText('body_text')->nullable();
            $table->text('popup_image')->nullable();
            $table->string('popup_image_alt')->nullable();

            // Per-hotspot overrides of the item defaults. Null means inherit,
            // which is why these are nullable rather than defaulted.
            $table->string('icon_name', 32)->nullable();
            $table->string('icon_color', 16)->nullable();
            // A custom glyph, as a URL. Wins over icon_name when both are set.
            $table->text('icon_image')->nullable();

            // Shown on hover and focus. Short by design: a tooltip that needs
            // a scrollbar is a popup.
            $table->string('tooltip', 255)->nullable();

            /*
             * The accessible name, when the visible header is not one.
             *
             * A hotspot headed "3" or "A" tells a screen reader nothing. This
             * is what `aria-label` gets; it falls back to the header, then to
             * "Hotspot <n>", so a hotspot is never unnamed.
             */
            $table->string('aria_label', 255)->nullable();

            // H5P `popupWidth`: percentage of the image the popup spans.
            $table->unsignedTinyInteger('popup_width')->default(40);

            $table->unsignedInteger('sort_order')->default(0);

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('image_hotspots_id', 'h5p_image_hotspot_points_parent_idx');
            $table->index(['image_hotspots_id', 'sort_order'], 'h5p_image_hotspot_points_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_image_hotspot_points');
        Schema::dropIfExists('h5p_image_hotspots');
    }
};
