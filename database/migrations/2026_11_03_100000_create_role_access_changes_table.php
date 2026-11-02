<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change history for role (group-wise) menu rights.
 *
 * One row per menu changed, grouped into a batch (one save, one copy, one undo). `before_rights`
 * and `after_rights` hold {view,add,edit,delete,dashboard} or NULL for "no row", so a batch can be
 * replayed backwards to undo it. The actor is written explicitly: the older audit helper reads the
 * web session, which is empty on the JWT API, so it recorded changes with no actor at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_access_changes')) {
            return;
        }

        Schema::create('role_access_changes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('batch_id', 36)->index();
            $table->unsignedInteger('sub_institute_id')->index();
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('actor_name', 150)->nullable();
            $table->unsignedInteger('profile_id')->index();
            $table->unsignedInteger('menu_id');
            $table->json('before_rights')->nullable();
            $table->json('after_rights')->nullable();
            // manual | copy | undo | ancestor (a parent menu granted so the child can render)
            $table->string('source', 20)->default('manual');
            $table->unsignedInteger('related_profile_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->char('undone_by_batch', 36)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sub_institute_id', 'created_at']);
            $table->index(['profile_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_access_changes');
    }
};
