<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $tableColumns = DB::getSchemaBuilder()->getColumnListing('admission_enquiry');
        $excludedColumns = ['id', 'enquiry_no', 'created_on', 'created_by', 'sub_institute_id', 'syear', 'deleted_at'];
        $excludeColumnsString = implode(',', $excludedColumns);

        DB::table('master_fields_table')->insert([
            'main_menu' => 173,
            'module' => 'admission_enquiry',
            'table_name' => 'admission_enquiry',
            'exclude_columns' => $excludeColumnsString,
            'duplicate_fields' => null,
            'duplicate_status' => 0,
            'sub_institute_id' => 0,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('master_fields_table')->where(['module' => 'admission_enquiry', 'sub_institute_id' => 0])->delete();
    }
};