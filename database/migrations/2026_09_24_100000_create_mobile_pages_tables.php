<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom Mobile Pages -- a render_type = 'webview' menu row whose page is a
 * JSON layout designed in the lms_k12 Next.js "Mobile Page Builder" and
 * rendered dynamically there, instead of an admin-typed URL to an existing
 * page. See MobileAppMenuRightsApiController's page_source column (added by
 * the sibling migration in this same batch) for how a menu row points at one
 * of these.
 *
 * `mobile_pages` is the page's identity (name, slug, which version is live);
 * `mobile_page_versions` is one row per saved snapshot of its layout_json.
 * Split the same way for the same reason mobile_dynamic_page/
 * mobile_dynamic_page_field are split: editing a draft must never be able to
 * change what is currently published. "Save Draft" updates the version row
 * `current_draft_version_id` points at in place; "Publish" INSERTS a new
 * version row (a snapshot of the draft's current content) and only then
 * repoints `published_version_id` -- so a half-finished edit can never leak
 * to mobile users, and the previously-published version stays intact and
 * inspectable (status flips to 'archived') until the next explicit publish.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_pages')) {
            Schema::create('mobile_pages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();

                $table->string('name', 150);

                // What the runtime URL Flutter opens is built from:
                // {frontend_url}/mobile/custom/{slug}. Unique per tenant, not
                // globally, the same as mobile_dynamic_page.page_key.
                $table->string('slug', 150);

                $table->text('description')->nullable();

                // draft | published | inactive -- your own vocabulary for
                // this screen (see the builder's Draft/Publish workflow).
                // Kept as a plain string rather than a DB enum for the same
                // reason render_type is a plain string: a fourth state later
                // is a code change, not an ALTER on a live tenant's table.
                $table->string('status', 20)->default('draft');

                // The version currently open in the editor.
                $table->unsignedBigInteger('current_draft_version_id')->nullable();

                // The version the runtime endpoint serves to mobile users.
                // Null until the first Publish.
                $table->unsignedBigInteger('published_version_id')->nullable();

                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('created_on')->nullable()->useCurrent();
                $table->dateTime('updated_on')->nullable();

                $table->unique(['sub_institute_id', 'slug']);
            });
        }

        if (! Schema::hasTable('mobile_page_versions')) {
            Schema::create('mobile_page_versions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('page_id')->index();

                // Increments per page, independent of the row's own id, so
                // the admin UI can show "Version 3" rather than a raw row id.
                $table->unsignedInteger('version_number');

                // The full {page:{...,background},components:[...]} blob
                // from the builder. A JSON blob column here -- not
                // mobile_dynamic_page_field's row-per-field shape -- because
                // a page's layout is a tree with per-component style/position/
                // action config, not a flat list of presentation options.
                $table->longText('layout_json');

                // draft | published | archived. A page can have many
                // archived versions, at most one draft, at most one
                // published, at a time.
                $table->string('status', 20)->default('draft');

                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_on')->nullable()->useCurrent();

                // Bumped each time "Save Draft" overwrites this row in place
                // (only meaningful while status = draft).
                $table->dateTime('updated_on')->nullable();

                $table->dateTime('published_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();

                $table->foreign('page_id')->references('id')->on('mobile_pages')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_page_versions');
        Schema::dropIfExists('mobile_pages');
    }
};
