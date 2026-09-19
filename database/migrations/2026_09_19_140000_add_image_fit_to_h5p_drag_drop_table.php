<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the background image meets the canvas: contain | cover | original | stretch.
 *
 * Added because the canvas rendered its background cover-style, which fills the
 * box by cropping whatever does not fit -- a portrait diagram dropped into the
 * 620x310 default lost its top and bottom, taking any label the author had
 * drawn a drop zone over with it.
 *
 * `contain` is the default and the only value safe for a labelled diagram: the
 * canvas takes the image's own aspect ratio, so the whole image is visible and
 * one percent of the canvas is one percent of the image -- which is what the
 * zone and element geometry is stored as. The other three are author choices
 * that each give something up (crop, overflow, distortion).
 *
 * Existing rows default to `contain`, so every task authored before this
 * column becomes fully visible rather than cropped. Their stored canvas size
 * may not match their background's proportions; the editor offers a one-click
 * "Fit canvas to image" for that, which is safe to apply precisely because the
 * geometry is percentages rather than pixels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('h5p_drag_drop', function (Blueprint $table) {
            $table->string('image_fit', 16)->default('contain')->after('background_image');
        });
    }

    public function down(): void
    {
        Schema::table('h5p_drag_drop', function (Blueprint $table) {
            $table->dropColumn('image_fit');
        });
    }
};
