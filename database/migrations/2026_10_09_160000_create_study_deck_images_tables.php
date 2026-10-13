<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where study-deck pictures live: in the database.
 *
 * There was no media table in this application. A study deck's pictures used to be written to disk
 * (the review bundle, the player's public folder, a download cache) and, once published, to the shared
 * object store. That left files accumulating with every generation run, so the pictures now live here
 * and a deck refers to one by a stable reference, `study-deck-image:<id>`, never by a path.
 *
 * study_deck_images
 *   One row per distinct picture per school. The bytes are the picture itself (MEDIUMBLOB: the largest
 *   accepted picture is 8 MiB, the column holds 16 MiB). (sub_institute_id, sha256) is unique, so the same
 *   picture is kept once however many runs or decks use it, and one school's rows are never another's.
 *   `data` is the only large column: every query that does not serve the picture leaves it out.
 *
 * study_deck_image_links
 *   Which stored decks (content_master rows) use a picture. It is what makes a deduplicated picture safe to
 *   remove (nothing links to it any more) and what separates pictures in use from the leftovers of runs that
 *   were never published. content_id is deliberately NOT a foreign key: content_master is a shared legacy
 *   table whose rows are deleted by many other code paths; the application removes a deck's links when it
 *   removes the deck (lms:unstore-study-deck). image_id IS a foreign key and cascades.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_deck_images', function (Blueprint $table) {
            $table->id();
            // content_master.sub_institute_id / chapter_master are signed integer columns on this schema.
            $table->integer('sub_institute_id');
            $table->integer('chapter_id')->nullable();
            $table->char('sha256', 64);
            $table->string('mime_type', 32);
            $table->string('format', 8);
            $table->unsignedInteger('byte_size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            // The address a downloaded picture came from, so a re-run does not fetch it from a third party again.
            $table->char('source_url_hash', 40)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->binary('data');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['sub_institute_id', 'sha256'], 'uq_study_deck_images_school_sha256');
            $table->index('chapter_id', 'idx_study_deck_images_chapter');
            $table->index(['sub_institute_id', 'source_url_hash'], 'idx_study_deck_images_source');
            $table->index('created_at', 'idx_study_deck_images_created');
        });

        // Laravel's binary() is a 64 KiB BLOB on MySQL/MariaDB; pictures are far larger.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE study_deck_images MODIFY data MEDIUMBLOB NOT NULL');
        }

        Schema::create('study_deck_image_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_id');
            $table->integer('content_id');
            $table->integer('chapter_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['image_id', 'content_id'], 'uq_study_deck_image_links_pair');
            $table->index('content_id', 'idx_study_deck_image_links_content');
            $table->foreign('image_id', 'fk_study_deck_image_links_image')
                ->references('id')->on('study_deck_images')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_deck_image_links');
        Schema::dropIfExists('study_deck_images');
    }
};
