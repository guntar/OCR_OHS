<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOcrTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ocr_form', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string("file_name",255)->unique();
            $table->string('url_file',255)->unique();
            $table->enum('file_type',['jpeg','png','bmp','pdf','tiff']);
            $table->string('result_id_ocr')->unique();
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
        Schema::drop('ocr_form');
    }
}
