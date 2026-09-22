<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * H5P Drag and Drop (H5P.DragQuestion) storage.
 *
 * Three tables, mirroring how the type is actually authored rather than
 * flattening it into one blob:
 *
 *   h5p_drag_drop            one task: background image, canvas size, scoring
 *   h5p_drag_drop_zones      the drop targets drawn on that background
 *   h5p_drag_drop_elements   the draggables (text or image) placed on it
 *
 * The mapping between the two children is many-to-many in both directions, so
 * it is held as JSON id lists on each side (`correct_element_ids` on a zone,
 * `drop_zone_ids` on an element). That is the shape H5P.DragQuestion's own
 * semantics use (`dropZones[].correctElements`, `elements[].dropZones`), which
 * keeps the export a projection rather than a translation.
 *
 * `content_json` on the parent caches the last built H5P.DragQuestion `params`
 * object. It is derived data, never the source of truth -- the rows above are.
 * It exists so export and the player can read one column instead of
 * re-deriving, and so an imported package keeps any field this schema does not
 * model yet instead of losing it on the round trip.
 *
 * Column names for id / title / body / chapter / subject / standard / tenant /
 * created_by / created_at / deleted_at are the ones the PAL H5P registry binds
 * to in config/pal_h5p.php `implementation.columns` -- renaming one here means
 * editing that block too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_drag_drop', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('standard_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('chapter_id')->nullable();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('task_description')->nullable();

            // Background image + the canvas the author placed everything on.
            // Zone/element geometry is stored as a percentage of these, so the
            // task reflows correctly at any render width.
            $table->text('background_image')->nullable();
            $table->unsignedInteger('canvas_width')->default(620);
            $table->unsignedInteger('canvas_height')->default(310);

            // Scoring + behaviour (H5P.DragQuestion `behaviour`).
            $table->unsignedTinyInteger('pass_percentage')->default(100);
            $table->boolean('enable_retry')->default(true);
            $table->boolean('enable_show_solution')->default(true);
            $table->boolean('enable_check')->default(true);
            $table->boolean('single_point')->default(false);
            $table->boolean('apply_penalties')->default(true);
            $table->boolean('background_opacity_full')->default(true);

            // draft | published -- `published` is what a student surface reads.
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();

            // Derived cache of the H5P.DragQuestion params (see class docblock).
            $table->longText('content_json')->nullable();
            // Machine name + version the params were built for, so an upgrade
            // can find rows written against an older library.
            $table->string('library', 64)->default('H5P.DragQuestion 1.14');

            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('syear', 16)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The registry, the content adapter and the list page all filter on
            // exactly this tuple.
            $table->index(['sub_institute_id', 'chapter_id'], 'h5p_drag_drop_tenant_chapter_idx');
            $table->index(['standard_id', 'subject_id'], 'h5p_drag_drop_standard_subject_idx');
        });

        Schema::create('h5p_drag_drop_zones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('drag_drop_id');

            $table->string('label')->nullable();
            $table->text('tip')->nullable();

            // Percentages of the parent canvas (0-100), not pixels.
            $table->decimal('position_x', 8, 4)->default(0);
            $table->decimal('position_y', 8, 4)->default(0);
            $table->decimal('width', 8, 4)->default(20);
            $table->decimal('height', 8, 4)->default(20);

            // single = one-to-one (this zone accepts a single draggable).
            // Unset = one-to-many (it accepts every element mapped to it).
            $table->boolean('single')->default(true);
            $table->boolean('auto_align')->default(true);
            $table->boolean('show_label')->default(true);

            // Element ids accepted here. JSON rather than a pivot because the
            // export format is itself a list on this side.
            $table->json('correct_element_ids')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('drag_drop_id', 'h5p_drag_drop_zones_parent_idx');
        });

        Schema::create('h5p_drag_drop_elements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('drag_drop_id');

            // text | image
            $table->string('element_type', 16)->default('text');
            $table->text('text')->nullable();
            $table->text('image_path')->nullable();
            $table->string('image_alt')->nullable();

            // Percentages of the parent canvas (0-100), not pixels.
            $table->decimal('position_x', 8, 4)->default(0);
            $table->decimal('position_y', 8, 4)->default(0);
            $table->decimal('width', 8, 4)->default(15);
            $table->decimal('height', 8, 4)->default(10);

            // multiple = this draggable may be dropped into more than one zone
            // (one-to-many from the element side).
            $table->boolean('multiple')->default(false);
            $table->boolean('infinite')->default(false);

            // Zone ids this element may be dropped into.
            $table->json('drop_zone_ids')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('sub_institute_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('drag_drop_id', 'h5p_drag_drop_elements_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('h5p_drag_drop_elements');
        Schema::dropIfExists('h5p_drag_drop_zones');
        Schema::dropIfExists('h5p_drag_drop');
    }
};
