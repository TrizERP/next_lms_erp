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
        Schema::table('custom_module_tables', function (Blueprint $table) {
            $table->string('module_name');
            $table->string('module_type');
            $table->string('display_under');
            $table->string('relational_table');
            $table->string('migration')->nullable();
            $table->string('seeder')->nullable();
            $table->string('model')->nullable();
            $table->string('controller')->nullable();
            $table->string('route')->nullable();
            $table->string('view')->nullable();
            $table->string('storage')->nullable();
            $table->string('validation')->nullable();
            $table->string('access_link')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('custom_module_tables', function (Blueprint $table) {
            $table->dropColumn('module_name');
            $table->dropColumn('module_type');
            $table->dropColumn('display_under');
            $table->dropColumn('relational_table');
            $table->dropColumn('migration');
            $table->dropColumn('seeder');
            $table->dropColumn('model');
            $table->dropColumn('controller');
            $table->dropColumn('route');
            $table->dropColumn('view');
            $table->dropColumn('storage');
            $table->dropColumn('validation');
            $table->dropColumn('access_link');
            $table->dropColumn('created_by');
            $table->dropColumn('updated_by');
        });
    }
};
