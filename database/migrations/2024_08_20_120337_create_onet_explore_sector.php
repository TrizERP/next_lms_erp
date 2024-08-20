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
        Schema::create('onet_explore_sector', function (Blueprint $table) {
            $table->id(); // Creates an auto-incrementing UNSIGNED BIGINT primary key column
            $table->string('title', 150); // Creates a VARCHAR column with a length of 150
            $table->string('image', 150); // Creates a VARCHAR column with a length of 150
            $table->char('key', 10); // Creates a CHAR column with a fixed length of 10
            $table->string('value', 10); // Creates a VARCHAR column with a length of 10
            $table->text('html'); // Creates a TEXT column for longer content
            $table->timestamps(); // Creates 'created_at' and 'updated_at' TIMESTAMP columns
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('onet_explore_sector');
    }
};
