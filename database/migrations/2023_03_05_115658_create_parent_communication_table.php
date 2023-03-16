<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('parent_communication', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->integer('syear')->default(0);
            $table->date('date_');
            $table->integer('student_id')->default(0);
            $table->string('title', 250)->nullable();
            $table->text('message');
            $table->text('reply');
            $table->integer('reply_by')->nullable();
            $table->dateTime('reply_on')->nullable();
            $table->integer('sub_institute_id')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('parent_communication');
    }
};
