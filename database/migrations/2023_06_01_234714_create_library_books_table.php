<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->integer('material_resource_id')->default(0);
            $table->string('title', 200)->nullable();
            $table->text('sub_title')->nullable();
            $table->text('tags')->nullable();
            $table->string('edition', 50)->nullable();
            $table->string('isbn_issn', 100)->nullable();
            $table->string('publisher_name', 100)->nullable();
            $table->string('author_name', 100)->nullable();
            $table->integer('publish_year')->default(0);
            $table->string('collation', 50)->nullable();
            $table->string('series_title', 200)->nullable();
            $table->string('serial_no', 40)->nullable();
            $table->string('call_number', 50)->nullable();
            $table->string('language', 50)->nullable();
            $table->string('source', 3)->nullable();
            $table->string('publish_place', 50)->nullable();
            $table->string('classification', 40)->nullable();
            $table->text('review')->nullable();
            $table->text('notes')->nullable();
            $table->text('image')->nullable();
            $table->string('file_att', 255)->nullable();
            $table->tinyInteger('promoted')->default(0);
            $table->float('price')->default(0);
            $table->string('price_currency', 10)->nullable();
            $table->text('city')->nullable();
            $table->text('state')->nullable();
            $table->text('country')->nullable();
            $table->string('vol_no', 20)->nullable();
            $table->date('publication_date')->nullable();
            $table->string('company', 20)->nullable();
            $table->string('actors', 20)->nullable();
            $table->text('doc_type')->nullable();
            $table->text('editorial')->nullable();
            $table->text('subject')->nullable();
            $table->text('standard')->nullable();
            $table->string('publication', 200)->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->string('pages', 40)->nullable();
            $table->dateTime('input_date')->nullable();
            $table->dateTime('last_update')->nullable();
            $table->integer('like_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('library_books');
    }
};
