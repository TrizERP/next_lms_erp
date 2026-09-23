<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Native, server-driven mobile pages -- the alternative to a
 * render_type = 'webview' menu row for a page whose fields and layout should
 * be configured on the web and rendered as real Flutter widgets, not loaded
 * as a browser view.
 *
 * `mobile_dynamic_page` is one configured page (a title plus which existing
 * Laravel API supplies its data); `mobile_dynamic_page_field` is the ordered
 * list of tiles it shows, one row per tile -- the same row-per-field shape
 * `tblcustom_fields` already uses for admin-configured fields elsewhere in
 * this codebase, rather than a JSON blob column.
 *
 * Deliberately scoped to reading an EXISTING, already-permission-gated
 * Laravel API (fees-dashboard/summary for the first configured page) rather
 * than a new generic query engine: this describes presentation (which
 * fields, what label, what order, what format) over data that endpoint
 * already computes and already guards, not a new way to reach the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_dynamic_page')) {
            Schema::create('mobile_dynamic_page', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sub_institute_id')->index();

                // What a menu row's web_url holds when render_type is
                // 'native_dynamic' (see homescreen@renderKeys) -- unique per
                // tenant so one config can't shadow another's.
                $table->string('page_key', 100);

                $table->string('title', 150);

                // The Laravel API this page's tiles read their live values
                // from, relative to /api/ (e.g. "fees-dashboard/summary").
                // Configurable rather than hardcoded so a second dynamic page
                // can point at a different endpoint without a migration --
                // but still only ever an EXISTING, already-authorized route;
                // nothing here executes arbitrary queries.
                $table->string('data_endpoint', 200);

                $table->string('status', 5)->default('Yes');

                $table->timestamp('created_on')->nullable()->useCurrent();
                $table->dateTime('updated_on')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();

                $table->unique(['sub_institute_id', 'page_key']);
            });
        }

        if (! Schema::hasTable('mobile_dynamic_page_field')) {
            Schema::create('mobile_dynamic_page_field', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('page_id')->index();

                // The key this tile reads from the data endpoint's JSON
                // response, e.g. "collected_amount". Dot notation reaches one
                // level of nesting (e.g. "summary.collected_amount"), which
                // is as far as the first configured page's data needs to go.
                $table->string('field_key', 150);

                // An already-formatted display string the same response may
                // carry alongside the raw number (e.g. "collected_display"
                // next to "collected_amount"). Shown in place of `field_key`
                // formatted client-side when present, so currency/percent
                // formatting stays in the one place the ERP already does it
                // rather than being re-implemented per client.
                $table->string('display_key', 150)->nullable();

                $table->string('label', 100);

                // number | currency | percent | text -- how to format
                // field_key when display_key is absent.
                $table->string('field_type', 20)->default('text');

                $table->integer('sort_order')->default(0);
                $table->string('status', 5)->default('Yes');

                $table->timestamp('created_on')->nullable()->useCurrent();
                $table->dateTime('updated_on')->nullable();

                $table->foreign('page_id')->references('id')->on('mobile_dynamic_page')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_dynamic_page_field');
        Schema::dropIfExists('mobile_dynamic_page');
    }
};
