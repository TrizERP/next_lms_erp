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
        Schema::table('tblcustom_fields', function (Blueprint $table) {
            //
            $table->string('user_type',50)->comment('student and staff')->nullable()->after('field_label');
            $table->string('column_header',50)->nullable()->after('field_name');
            $table->string('table_alias',10)->nullable()->after('table_name');
            $table->integer('tab_sort_order')->nullable()->after('table_alias');
            $table->char('is_deleted',1)->comment('N/Y')->nullable()->after('required');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tblcustom_fields', function (Blueprint $table) {
            //
            $table->dropColumn('user_type');
            $table->dropColumn('column_header');
            $table->dropColumn('table_alias');
            $table->dropColumn('tab_sort_order');
            $table->dropColumn('is_deleted');
        });
    }
};
