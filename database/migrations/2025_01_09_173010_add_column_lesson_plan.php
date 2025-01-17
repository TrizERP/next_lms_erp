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
        Schema::table('lessonplan', function (Blueprint $table) {
            $table->renameColumn('total_marks', 'updated_by');
            $table->renameColumn('book_link', 'completion_status');
            $table->dateTime('completion_date')->nullable(); 
            $table->mediumText('reasons')->nullable();   
            $table->biginteger('created_by')->after('description')->nullable();   
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lessonplan', function (Blueprint $table) {
            $table->renameColumn('updated_by', 'total_marks'); 
            $table->renameColumn('completion_status', 'book_link'); 
            $table->dropColumn('completion_date');
            $table->dropColumn('reasons');
            $table->dropColumn('created_by');  
        });
    }
};
