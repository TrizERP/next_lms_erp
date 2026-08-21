<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // password was varchar(50), too short for a bcrypt hash (60 chars) —
        // Hash::make() output was being silently truncated on save, corrupting
        // the stored hash and locking students out after their first login.
        DB::statement('ALTER TABLE tblstudent MODIFY password VARCHAR(255) NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement('ALTER TABLE tblstudent MODIFY password VARCHAR(50) NULL');
    }
};
