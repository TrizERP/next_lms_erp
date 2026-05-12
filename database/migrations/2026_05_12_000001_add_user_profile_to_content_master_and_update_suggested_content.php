<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('content_master', function (Blueprint $table) {
            if (!Schema::hasColumn('content_master', 'user_profile')) {
                $table->string('user_profile', 50)->nullable()->after('basic_advance');
            }
        });

        if (Schema::hasColumn('suggested_content', 'type') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE suggested_content MODIFY type ENUM('exam_content','pal_content','question','misconception') NULL");
        }

        Schema::table('suggested_content', function (Blueprint $table) {
            if (!Schema::hasColumn('suggested_content', 'content_visited')) {
                $table->integer('content_visited')->default(0)->after('student_level');
            }
        });
    }

    public function down()
    {
        Schema::table('content_master', function (Blueprint $table) {
            if (Schema::hasColumn('content_master', 'user_profile')) {
                $table->dropColumn('user_profile');
            }
        });

        Schema::table('suggested_content', function (Blueprint $table) {
            if (Schema::hasColumn('suggested_content', 'content_visited')) {
                $table->dropColumn('content_visited');
            }
        });

        if (Schema::hasColumn('suggested_content', 'type') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE suggested_content MODIFY type ENUM('exam_content','pal_content','question') NULL");
        }
    }
};
