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
        Schema::create('tblclient', function (Blueprint $table) {
            $table->comment('');
            $table->bigIncrements('id');
            $table->string('client_name', 250)->nullable();
            $table->string('short_code', 150)->nullable();
            $table->string('logo', 150)->nullable();
            $table->string('address', 50)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('state', 50)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('contact_person', 50)->nullable();
            $table->string('contact_person_mobile', 50)->nullable();
            $table->string('contact_persoon_email', 50)->nullable();
            $table->string('trustee_name', 50)->nullable();
            $table->string('trustee_emai', 50)->nullable();
            $table->string('trustee_mobile', 50)->nullable();
            $table->string('number_of_schools', 50)->nullable();
            $table->string('db_host', 50)->nullable();
            $table->string('db_user', 50)->nullable();
            $table->string('db_password', 50)->nullable();
            $table->string('db_solution', 50)->nullable();
            $table->string('db_cms', 50)->nullable();
            $table->string('db_hrms', 50)->nullable();
            $table->string('db_library', 50)->nullable();
            $table->string('db_lms', 50)->nullable();
            $table->integer('multischool')->nullable();
            $table->integer('total_student')->nullable();
            $table->integer('total_staff')->nullable();
            $table->string('hrms_folder', 50)->nullable();
            $table->string('old_url', 250)->nullable();
            $table->timestamp('created_on')->nullable()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tblclient');
    }
};
